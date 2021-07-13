/*
* editTheme.js - define Heurist color theme
* refer initPageTheme.php for documentaton about heurist color themes
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

function editTheme(current_value, callback){

    var edit_dialog = null;
    
    var popup_dlg = $('#heurist-dialog-editTheme');

    if(popup_dlg.length>0){
        popup_dlg.empty();
    }else{
        popup_dlg = $('<div id="heurist-dialog-editTheme">')
            .appendTo( $(window.hWin.document).find('body') );
    }

    var editForm = $('<div class="ent_content_full editForm" style="top:0">')
    .appendTo($('<div class="ent_wrapper">').appendTo(popup_dlg));

    var _editing_symbology = new hEditing({container:editForm, 
        onchange:
        function(){
            if(edit_dialog){
                var ele = edit_dialog.parent().find('#btnRecSave');
                if(ele){
                    var isChanged = _editing_symbology.isModified();
                    var mode = isChanged?'visible':'hidden';
                    edit_dialog.parent().find('#btnRecSave').css('visibility', mode);
                }
            }
    }});
    
    var recdata = current_value ? new hRecordSet({count:1, order:[1], 
        records:{1:current_value}, 
        fields: {'stub':0}}) :null;
        //Object.getOwnPropertyNames(current_value)

                   
    var editFields = [                
        {"dtID": "name",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName":"Theme name:",
                "rst_Display":"hidden"
        }},
        
        {
        "groupHeader": "Default color theme",
        "groupTitleVisible": true,
        "groupType": "accordion",
            "children":[
            {"dtID": "cd_bg",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Background:",
                    "rst_DisplayHelpText": "Background color for most of widgets",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#e0dfe0"
            }},
            {"dtID": "cl_bg",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Light background:",
                    "rst_DisplayHelpText": "Background color for lists and popups.",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#ffffff"
            }},
            
            {"dtID": "cd_color",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Text:",
                    "rst_DisplayHelpText": "Widgets text color",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#333333"
            }},
            {"dtID": "cd_input",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Inputs BG:",
                    "rst_DisplayHelpText": "Background color for input elements",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#F4F2F4"
            }},
            {"dtID": "cd_border",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Header and borders:",
                    "rst_DisplayHelpText": "Header and border for popup, borders; for menu, accordeon, dividers",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#95A7B7"
            }},
            {"dtID": "cd_corner",
            "dtFields":{
                "dty_Type":"integer",
                "rst_DisplayName": "Corner radius:",
                "rst_DisplayHelpText": "Value from 0 to 16",
                "rst_DefaultValue": "0"
            }}

        ]},
        {
        "groupHeader": "Alternative color theme (Header panels, Popups)",
        "groupTitleVisible": true,
        "groupType": "accordion",
            "children":[
            {"dtID": "ca_bg",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Header background:",
                    "rst_DisplayHelpText": "Background color for header panel (with main and search menues)",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#364050"
            }},
            {"dtID": "ca_color",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Header text:",
                    "rst_DisplayHelpText": "Header text color",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#ffffff"
            }},
            {"dtID": "ca_input",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Header inputs:",
                    "rst_DisplayHelpText": "Background color for input elements on header panels",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#536077"
            }}
        ]},
        {
        "groupHeader": "Editor color theme",
        "groupTitleVisible": true,
        "groupType": "accordion",
            "children":[
            {"dtID": "ce_bg",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Background:",
                    "rst_DisplayHelpText": "Background color for editor form",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#ECF1FB"
            }},
            {"dtID": "ce_color",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Labels text:",
                    "rst_DisplayHelpText": "Color for text and labels in editor",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#6A7C99"
            }},
            {"dtID": "ce_input",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Input elements:",
                    "rst_DisplayHelpText": "Background color for input elements",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#ffffff"
            }},
            {"dtID": "ce_readonly",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Readonly color:",
                    "rst_DisplayHelpText": "Readonly elements",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#999999"
            }},
            {"dtID": "ce_mandatory",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Required entries:",
                    "rst_DisplayHelpText": "Color for required input elements and labels",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#CC0000"
            }},
            {"dtID": "ce_helper",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Helper and prompts:",
                    "rst_DisplayHelpText": "Color for helpers and prompts",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#999999"
            }}
        ]},
        {
        "groupHeader": "Clickable elements (buttons, links, menu items etc)",
        "groupTitleVisible": true,
        "groupType": "accordion",
            "children":[


            
            {"dtID": "sd_bg",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Button BG:",
                    "rst_DisplayHelpText": "Button default background",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#f2f2f2"
            }},
            {"dtID": "sd_color",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Button text:",
                    "rst_DisplayHelpText": "Button default text",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#555555"
            }},
            
            
            {"dtID": "sh_bg",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Hover/focus BG:",
                    "rst_DisplayHelpText": "Button hover/focus background",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#f2f2f2"
            }},
            {"dtID": "sh_color",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Hover Text:",
                    "rst_DisplayHelpText": "Button hover/focus text",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#2b2b2b"
            }},
            {"dtID": "sh_border",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Hover Borders:",
                    "rst_DisplayHelpText": "Hover border color",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#999999"
            }},
            
            {"dtID": "sa_bg",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Active BG:",
                    "rst_DisplayHelpText": "Button active background",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#95A7B7"
            }},
            {"dtID": "sa_color",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Active Text:",
                    "rst_DisplayHelpText": "Button active text",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#212121"
            }},
            {"dtID": "sa_border",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Active Borders:",
                    "rst_DisplayHelpText": "Active border color",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#aaaaaa"
            }},
            
            {"dtID": "sp_bg",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Pressed BG:",
                    "rst_DisplayHelpText": "Button pressed background",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#9CC4D9"
            }},
            {"dtID": "sp_color",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Pressed Text:",
                    "rst_DisplayHelpText": "Button pressed text",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#ffffff"
            }},
            {"dtID": "sp_border",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Pressed Borders:",
                    "rst_DisplayHelpText": "Pressed border color",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#003eff"
            }}
      
        
        ]}
    ];
    
    
    _editing_symbology.initEditForm( editFields, recdata, true );

    var edit_buttons = [
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
                var res = _editing_symbology.getValues(); //all values
                //remove empty values
                var propNames = Object.getOwnPropertyNames(res);
                for (var i = 0; i < propNames.length; i++) {
                    var propName = propNames[i];
                    if (window.hWin.HEURIST4.util.isempty(res[propName])) {
                        delete res[propName];
                    }
                }
                if(res['cd_corner']>16 || res['cd_corner']<0){
                    res['cd_corner']=0;
                }
                
                _editing_symbology.setModified(false);
                edit_dialog.dialog('close');
                
                if($.isFunction(callback)){
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
            //that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
        },
        beforeClose: function(){
            //show warning in case of modification
            if(_editing_symbology.isModified()){
                var $dlg, buttons = {};
                buttons['Save'] = function(){ 
                    //that._saveEditAndClose(null, 'close'); 
                    edit_dialog.parent().find('#btnRecSave').click();
                    $dlg.dialog('close'); 
                }; 
                buttons['Ignore and close'] = function(){ 
                    _editing_symbology.setModified(false);
                    edit_dialog.dialog('close'); 
                    $dlg.dialog('close'); 
                };

                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                    window.hWin.HR('Warn_Lost_Data'),
                    buttons,
                    {title: window.hWin.HR('Confirm'),
                       yes: window.hWin.HR('Save'),
                        no: window.hWin.HR('Ignore and close')},
                    {default_palette_class: 'ui-heurist-design'});
                return false;   
            }
            return true;
        },

        buttons: edit_buttons
    });                

    edit_dialog.parent().addClass('ui-heurist-design');
                
}//end editTheme
