/*
* editTheme.js - Defines Heurist color theme
*
* @description This file provides functionality to define and edit a Heurist color theme using a dialog.
* @see initPageTheme.php for the underlying theme structure and documentation.
* 
* @package     Heurist academic knowledge management system
* @subpackage  hclient\widgets\editing
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       5.0
*/

/* global HEditing, HRecordSet */

/**
 * @global
 * @type {HEditing|undefined}
 * @description Instance of the HEditing class, used to manage the theme editing form.
 *              It is initialized when the editTheme function is called and its configuration is loaded.
 */
let _theme_editing_symbology;

/**
 * Opens a dialog to edit or define a Heurist color theme.
 * The theme properties are loaded from `editTheme.json` and presented in a form
 * managed by the HEditing class.
 *
 * @param {Object<string, any>|null|undefined} current_value - An object containing the current theme properties.
 *                                                            If null or undefined, a new theme is being defined.
 *                                                            Expected properties are keys like 'cd_bk_col', 'cd_corner', etc.
 * @param {function(Object<string, any>): void} callback - A function to be called when the theme is saved.
 *                                                        It receives an object with the saved theme properties as its argument.
 */
function editTheme(current_value, callback){

    let edit_dialog = null; //assigned on popup_dlg.dialog
    let editFields = null;
    
    let popup_dlg = $('#heurist-dialog-editTheme');

    if(popup_dlg.length>0){
        popup_dlg.empty();
    }else{
        popup_dlg = $('<div id="heurist-dialog-editTheme">')
            .appendTo( $(window.hWin.document).find('body') );
    }

    let editForm = $('<div class="ent_content_full editForm" style="top:0">')
    .appendTo($('<div class="ent_wrapper">').appendTo(popup_dlg));
    
    //edit form configuration
    const url = window.hWin.HAPI4.baseURL+'hclient/widgets/editing/editTheme.json';
            
    $.getJSON(url, function(res){
        
            editFields = res;
                    
            _theme_editing_symbology = new HEditing({container:editForm, 
                onchange:
                function(){
                    if(edit_dialog){
                        let ele = edit_dialog.parent().find('#btnRecSave');
                        if(ele){
                            let isChanged = _theme_editing_symbology.isModified();
                            let mode = isChanged?'visible':'hidden';
                            edit_dialog.parent().find('#btnRecSave').css('visibility', mode);
                        }
                    }
                },
                oninit: function(){
                    _theme_editing_symbology = this;
                    __editTheme_continue();
                }
            });
    });
    
    /**
     * @private
     * @function __editTheme_continue
     * @description This function is called after the theme editing form configuration (editTheme.json) has been loaded.
     *              It initializes the HEditing form with the current theme values (if any) and sets up the dialog
     *              with 'Save' and 'Cancel' buttons.
     */
    function __editTheme_continue(){
    
    let recdata = current_value ? new HRecordSet({count:1, order:[1], 
        records:{1:current_value}, 
        fields: {'stub':0}}) :null;
    
    _theme_editing_symbology.initEditForm( editFields, recdata, true );

    let edit_buttons = [
        {text:window.hWin.HR('Cancel'), 
            id:'btnRecCancel',
            css:{'float':'right'}, 
            click: function() { 
                edit_dialog.dialog('close'); 
        }},
        {text:window.hWin.HR('Save'),
            id:'btnRecSave',
            css:{'visibility':'hidden', 'float':'right'},  
            click: function() { 
                let res = _theme_editing_symbology.getValues(); //all values
                //remove empty values
                let propNames = Object.getOwnPropertyNames(res);
                for (let i = 0; i < propNames.length; i++) {
                    let propName = propNames[i];
                    if (window.hWin.HEURIST4.util.isempty(res[propName])) {
                        delete res[propName];
                    }
                }
                if(res['cd_corner']>16 || res['cd_corner']<0){
                    res['cd_corner']=0;
                }
                
                _theme_editing_symbology.setModified(false);
                edit_dialog.dialog('close');
                
                if(window.hWin.HEURIST4.util.isFunction(callback)){
                    callback.call(this, res);
                }

        }}
    ];                

    //
    //
    edit_dialog = popup_dlg.dialog({
        autoOpen: true,
        height: 700,
        width:  740,
        modal:  true,
        title: window.hWin.HR('Define Heurist Theme'),
        resizeStop: function( event, ui ) {//fix bug
           
        },
        beforeClose: function(){
            //show warning in case of modification
            if(_theme_editing_symbology.isModified()){
                
                window.hWin.HEURIST4.msg.showMsgOnExit(window.hWin.HR('Warn_Lost_Data'),
                    ()=>{edit_dialog.parent().find('#btnRecSave').trigger('click');}, //save
                    ()=>{_theme_editing_symbology.setModified(false); edit_dialog.dialog('close'); }); //ignore and close
                return false;   
            }
            return true;
        },

        buttons: edit_buttons
    });                

    edit_dialog.parent().addClass('ui-heurist-design');
                
}
}//end editTheme
