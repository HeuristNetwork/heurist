/**
* @file rectypeTemplate.js
* @brief Provides a popup UI for downloading Record Type templates (XML/JSON).
* @fileOverview This widget allows users to select record types and download their structure as either an XML or JSON template. This is useful for import/export and for understanding record type definitions.
* @project     Heurist academic knowledge management system
* @package  hclient\widgets\entity\popups
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/



/**
 * @widget heurist.rectypeTemplate
 * @brief Popup widget for downloading Record Type templates.
 * @extends $.heurist.baseAction
 * @description This widget provides a dialog interface for users to select one or more
 * record types and choose a format (XML or JSON) to download their definitions as a template file.
 *
 * @property {number} [height=520] The default height of the popup dialog.
 * @property {number} [width=400] The default width of the popup dialog.
 * @property {string} [title='Download XML or JSON template'] The title displayed in the dialog's title bar.
 * @property {string} [default_palette_class='ui-heurist-populate'] The default CSS class for theming the dialog.
 * @property {string} [path='widgets/entity/popups/'] The path to the widget's HTML template file.
 * @property {string} [actionName='rectypeTemplate'] The name of the action, used internally.
 */
$.widget( "heurist.rectypeTemplate", $.heurist.baseAction, {

    // default options
    options: {
        height: 520,
        width:  400,
        title:  'Download XML or JSON template',
        default_palette_class: 'ui-heurist-populate',
        path: 'widgets/entity/popups/', //location of this widget
        actionName: 'rectypeTemplate'
    },
    
    //  
    /**
     * @brief Initializes the controls within the popup dialog.
     * @override
     * @memberof heurist.rectypeTemplate
     * Sets up the "Select record types" button, which opens a `defRecTypes` dialog
     * for multi-selecting record types. Manages the display of selected record types
     * and the "All record types" checkbox.
     * @returns {boolean} Result of the superclass's `_initControls` method.
     */
    _initControls: function(){
        
        this._$('button#rectypes-select').button();

        this._on(this._$('button#rectypes-select, div#rectypes-list'),{click: function(){

            let $selected_rectypes = this._$('div#rectypes-list');

            let popup_options = {
                select_mode: 'select_multi',
                edit_mode: 'popup',
                isdialog: true,
                width: 540,
                title: 'Select record types',
                selection_on_init: $selected_rectypes.attr('data-ids').split(','),
                default_palette_class: 'ui-heurist-publish',

                onselect:function(event, data){

                    let ids = data.selection;

                    if(ids != null && window.hWin.HEURIST4.util.isArrayNotEmpty(ids)){

                        $selected_rectypes.attr('data-ids', data.selection.join(',')).text('');

                        for(let i = 0; i < ids.length; i++){

                            let name = $Db.rty(ids[i], 'rty_Name');

                            $selected_rectypes.append(
                                '<span class="truncate" style="display: inline-block;width: 155px; max-width: 155px;margin: 2.5px 0px" title="'+ name +'">'
                                    + name +
                                '</span>');

                            if((i+1) != ids.length){
                                $selected_rectypes.append('<br>');
                            }
                        }
                    }else{
                        $selected_rectypes.attr('data-ids', '').text('<span style="display: inline-block;margin: 5px 0px;"> None </span>');
                    }
                }
            };

            window.hWin.HEURIST4.ui.showEntityDialog('defRecTypes', popup_options);
        }});

        this._on(this._$('input#rectypes-all'),{change:function(event){
            window.hWin.HEURIST4.util.setDisabled(this._$('button#rectypes-select, div#rectypes-list'), $(event.target).is(':checked'));
        }});
        
        this._$('input#rectypes-all').trigger('change');
        
        return this._super();
    },


    //    
    /**
     * @brief Gets the action buttons for the dialog.
     * @override
     * @memberof heurist.rectypeTemplate
     * @returns {object[]} An array of button definition objects for the dialog.
     * Modifies the default "OK" button text to "Download".
     */
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Download');
        res[1].disabled = null;
        return res;
    },

    //
    /**
     * @brief Performs the download action.
     * @override
     * @memberof heurist.rectypeTemplate
     * Retrieves the selected template type (XML or JSON) and the list of selected record type IDs
     * (or 'y' for all). Constructs the appropriate download URL and initiates the download.
     * Shows a flash message and closes the dialog upon completion.
     */
    doAction: function(){

            let template_type = this._$('input[name="template-type"]:checked').attr('id');
            let rectype_ids = this._$('div#rectypes-list').attr('data-ids');
            let is_all_rectypes = this._$('input#rectypes-all').is(':checked');

            if(is_all_rectypes) { rectype_ids = 'y'; } // get all rectypes

            if(rectype_ids == null){
                window.hWin.HEURIST4.msg.showMsgFlash('Please select some record types...', 2000);
                return;
            }

            if(template_type == 'template-xml'){

                window.hWin.HEURIST4.util.downloadURL(window.hWin.HAPI4.baseURL
                        +'export/xml/flathml.php?file=1&'
                        +'rectype_templates='+ rectype_ids
                        +'&db='+window.hWin.HAPI4.database);
                        
            }else if(template_type == 'template-json'){

                window.hWin.HEURIST4.util.downloadURL(window.hWin.HAPI4.baseURL
                    +'export/json/recordTemplate.php?'
                    +'rectype_ids='+ rectype_ids
                    +'&db='+window.hWin.HAPI4.database);
            }else{
                window.hWin.HEURIST4.msg.showMsgFlash('Please select what type of template you want...', 2000);
                return;
            }

            window.hWin.HEURIST4.msg.showMsgFlash('Downloading File...', 3000);
            
            this.closeDialog();
    }
        
});

