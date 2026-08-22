/**
* @file mapSymbolEditor.js
* @brief Map symbology editor and geographic-field selector.
* @fileOverview Provides the legacy-global map symbol editor used by Heurist map configuration and map-layer editing, plus selection of geographic field paths.
* @project     Heurist academic knowledge management system
* @package     Widgets.Editing
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay <blmckay13@gmail.com>
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/


/* global HEditing */

/**
 * @memberof Widgets.Editing
 * @description  Opens a dialog for editing map symbology properties.
 * The dialog's content and behavior are determined by the `mode_edit` parameter.
 * It uses an `HEditing` instance internally to manage the form fields.
 *
 * @param {Array<Object>} current_value - The current symbology configuration object or an array for thematic maps.
 *                                                  If null, a new symbology is being defined.
 * @param {number} mode_edit - Defines the type of symbology editor to open:
 *                             1: Symbology editor for
 *                             2: Symbology for general map draw style.
 *                             3: Symbology editor from record edit for a map layer.
 *                             4: Symbology editor for a thematic map.
 *                             5: Define symbology ranges (gradient values).
 *                             6: Symbology editor for visualiser [nodes]
 *                             7: Symbology editor for visualiser [edges]
 * @param {function} callback - A function to be called when the symbology is saved.
 *                                                         It receives the updated symbology object or array as its argument.
 */
function editSymbology(current_value, mode_edit, callback, cancelCallback){

    let edit_symb_dialog = null; //assigned on popup_dlg.dialog
    let _saved = false;
    
    let dialog_div_id = 'heurist-dialog-editSymbology'+(mode_edit>=3?mode_edit:'');
    
    let popup_dlg = $('#'+dialog_div_id);
    
    if(popup_dlg.length>0){
        popup_dlg.empty();
    }else{
        popup_dlg = $('<div id="'+dialog_div_id+'">')
            .appendTo( $(window.hWin.document).find('body') );
    }
    
    // Heurist query for a map query layer may be passed temporarily with the
    // symbology value. Clone first so opening the editor never mutates the caller's
    // object while extracting this transient property.
    if(current_value && typeof current_value === 'object'){
        current_value = window.hWin.HEURIST4.util.cloneJSON(current_value);
    }

    let maplayer_rty = null; //record types in mapquery resultset
    let maplayer_query = true;
    if(current_value && current_value.maplayer_query){
        maplayer_query = current_value.maplayer_query;
        delete current_value.maplayer_query;
    }

    // Canonical/legacy vector symbology is interpreted in one place. This editor
    // edits only the layer/base symbol and preserves thematic renderers separately.
    let _thematicRenderers = [];
    if(current_value){
        const symbology = window.hWin.HEURIST4.map.splitSymbology(current_value);
        _thematicRenderers = window.hWin.HEURIST4.util.cloneJSON(symbology.thematic || []);
        current_value = window.hWin.HEURIST4.util.cloneJSON(symbology.symbol || {});
    }

    // Canonical map opacity is 0..1. Accept old 0..100 values at the editor
    // boundary, but keep graph modes 6/7 on their existing independent contract.
    if(mode_edit!==6 && mode_edit!==7 && current_value && $.isPlainObject(current_value)){
        if(Object.hasOwn(current_value, 'opacity')){
            current_value.opacity = window.hWin.HEURIST4.map.normalizeOpacity(current_value.opacity);
        }
        if(Object.hasOwn(current_value, 'fillOpacity')){
            current_value.fillOpacity = window.hWin.HEURIST4.map.normalizeOpacity(current_value.fillOpacity);
        }
    }

    // HRecordSet treats array values as repeatable field values. Map symbol
    // iconSize, however, may arrive from older/runtime styles as [width,height].
    // The symbology editor has one scalar Icon size field, so collapse it before
    // constructing HRecordSet. Radius is a Leaflet output alias, not another
    // editor field; use it only as a fallback value and remove it from recdata.
    if(current_value && $.isPlainObject(current_value)){
        if(Array.isArray(current_value.iconSize)){
            current_value.iconSize = current_value.iconSize.length>0 ? current_value.iconSize[0] : '';
        }
        if(window.hWin.HEURIST4.util.isempty(current_value.iconSize) && current_value.radius>0){
            current_value.iconSize = Number(current_value.radius) * 2;
        }
        delete current_value.radius;
    }

    let _editing_symbology;
    let _refreshSymbolPreviews = null;

    // During the transition to heurist-map, keep the old per-user map style as
    // the canonical fallback for normal MapLayer symbology previews. Modes 1
    // and 3 edit ordinary map symbols; their blank/unset properties should
    // therefore preview exactly as they do in existing Heurist maps.
    let _previewDefaultSymbol = {};
    if(mode_edit===1 || mode_edit===3){
        let defaultStyle = window.hWin.HAPI4.get_prefs_def('map_default_style', {});
        defaultStyle = window.hWin.HEURIST4.util.isJSON(defaultStyle);
        if(defaultStyle && $.isPlainObject(defaultStyle)){
            _previewDefaultSymbol = window.hWin.HEURIST4.util.cloneJSON(defaultStyle);
        }
    }

    /*
     * Create the dialog wrapper before HEditing builds its controls. Widgets such
     * as hSelect and the colour picker inspect their DOM ancestry during
     * initialisation. On the first opening there was previously no .ui-dialog
     * parent yet, while on subsequent openings the wrapper already existed.
     * Keeping the dialog closed while the form is constructed gives first and
     * subsequent openings the same DOM lifecycle.
     */
    edit_symb_dialog = popup_dlg.dialog({
        autoOpen: false,
        height: (mode_edit==2 || mode_edit==6 || mode_edit==7) ? 300 : ((mode_edit==5) ? 500 : 770),
        width:  740,
        modal:  true,
        title: window.hWin.HR((mode_edit==5)?'Define symbology gradient values':((mode_edit==4)?'Symbology for thematic map range/category':'Define Symbology')),
        buttons: [],
        resizeStop: function( event, ui ) {//fix bug

        },
        open: function(){
            // Always give keyboard focus to the dialog itself. This is required
            // on repeated openings as well as on the first one.
            const dialogWidget = $(this).dialog('widget');
            setTimeout(function(){
                dialogWidget.attr('tabindex', '-1').trigger('focus');
                let ui = window.hWin.HEURIST4 && window.hWin.HEURIST4.ui;
                if(ui && typeof ui._raiseMapConfigurationChildDialog === 'function'){
                    ui._raiseMapConfigurationChildDialog(dialogWidget);
                }
            }, 0);
        },
        beforeClose: function(){
            //show warning in case of modification
            if(_editing_symbology && _editing_symbology.isModified()){

                window.hWin.HEURIST4.msg.showMsgOnExit(window.hWin.HR('Warn_Lost_Data'),
                    ()=>{edit_symb_dialog.parent().find('#btnRecSave').trigger('click');}, //save
                    ()=>{_editing_symbology.setModified(false); edit_symb_dialog.dialog('close'); }); //ignore and close
                return false;
            }
            return true;
        },
        close: function(){
            if(!_saved && window.hWin.HEURIST4.util.isFunction(cancelCallback)){
                cancelCallback.call(this);
            }
        }
    });

    edit_symb_dialog.parent().addClass('ui-heurist-design');

    let editForm = $('<div class="ent_content_full editForm" style="top:0">')
    .appendTo($('<div class="ent_wrapper">').appendTo(popup_dlg));
    
    _editing_symbology = new HEditing({container:editForm, 
        onchange:
        function(){
            let isChanged = _editing_symbology.isModified();
            let mode = isChanged?'visible':'hidden';
            edit_symb_dialog.parent().find('#btnRecSave').css('visibility', mode);

            // Icon-dependent fields must follow Icon source on every change. Do not
            // couple visibility to the modified flag: HEditing may refresh field
            // wrappers independently, which previously left the Icon field visible
            // after switching to Record type icon.
            let ele = _editing_symbology.getFieldByName('iconType');
            if(ele!=null){
                let res = ele.editing_input('getValues');
                const iconType = res && res.length ? res[0] : '';
                _editing_symbology.getFieldByName('iconUrl').toggle(iconType==='url');
                _editing_symbology.getFieldByName('iconFont').toggle(iconType==='iconfont');
            }

            if(_refreshSymbolPreviews){
                _refreshSymbolPreviews();
            }

        },
        oninit: function(){
            
            _editing_symbology = this;
            
            if(current_value){

                // mode 3 exposes thematic mapping as a nested block-text editor.
                // It receives the canonical value so mapThemesEditor can edit the same
                // structure used by DT_SYMBOLOGY and the public map API.
                if(mode_edit==3 && maplayer_query && _thematicRenderers.length>0){
                    current_value.thematicMap = JSON.stringify({
                        symbol: window.hWin.HEURIST4.util.cloneJSON(current_value),
                        thematic: window.hWin.HEURIST4.util.cloneJSON(_thematicRenderers)
                    });
                }

                if(mode_edit==4 && window.hWin.HEURIST4.util.isempty(current_value.stroke)){
                    current_value.stroke = ''; //initiallly not defined for ranges
                }else{
                    current_value.stroke = window.hWin.HEURIST4.util.istrue(current_value.stroke)?'1':'0';    
                }
                if(mode_edit==4 && window.hWin.HEURIST4.util.isempty(current_value.fill)){
                    current_value.fill = ''; //initiallly not defined for ranges
                }else{
                    current_value.fill = window.hWin.HEURIST4.util.istrue(current_value.fill)?'1':'0';
                }
                
            }
            
            let recdata = current_value ? new HRecordSet({count:1, order:[1], 
                records:{1:current_value}, 
                fields: {'stub':0}}) :null;
                //Object.getOwnPropertyNames(current_value)

        
    /*
    iconUrl: 'my-icon.png',
    iconSize: [38, 95],
    iconAnchor: [22, 94],
    popupAnchor: [-3, -76],
    shadowUrl: 'my-icon-shadow.png',
    shadowSize: [68, 95],
    shadowAnchor: [22, 94]     

    for divIcon
    color
    fillColor
    animation
    */                    
    let editFields;
    if(mode_edit==2){
        editFields = [
        
        {"dtID": "color",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "Stroke color:",
                "rst_DisplayWidth": 17,
                "rst_DisplayHelpText": "",
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "weight",
            "dtFields":{
                "dty_Type":"integer",
                "rst_DisplayName": "Stroke width:",
                "rst_DisplayHelpText": "Stroke width in pixels"
        }},
        {"dtID": "opacity",
            "dtFields":{
                "dty_Type":"float",
                "rst_DisplayName": "Stroke opacity:",
                "rst_DisplayHelpText": "Value from 0 (transparent) to 1 (opaque)",
                "rst_Spinner": "1",
                "rst_SpinnerStep": "0.1",
                "rst_MinValue": "0",
                "rst_MaxValue": "1"
        }},
        
        {"dtID": "fillColor",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "Fill color:",
                "rst_DisplayWidth": 17,
                "rst_DisplayHelpText": "Fill color. Defaults to the value of the color option",
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "fillOpacity",
            "dtFields":{
                "dty_Type":"float",
                "rst_DisplayName": "Fill opacity:",
                "rst_DisplayHelpText": "Value from 0 (transparent) to 1 (opaque)",
                "rst_Spinner": "1",
                "rst_SpinnerStep": "0.1",
                "rst_MinValue": "0",
                "rst_MaxValue": "1"
        }}
        ];
        
    }
    else if(mode_edit==5){
        
        editFields = [
        {"dtID": "strokeColor1",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "Stroke color from:",
                "rst_DisplayWidth": 17,
                "rst_DisplayHelpText": "",
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "strokeColor2",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "to:",
                "rst_DisplayWidth": 17,
                "rst_DisplayHelpText": "",
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "strokeOpacity1",
            "dtFields":{
                "dty_Type":"integer",
                "rst_DisplayName": "Stroke opacity from :",
                "rst_DisplayHelpText": "Value from 0 (transparent) to 1 (opaque)"
        }},
        {"dtID": "strokeOpacity2",
            "dtFields":{
                "dty_Type":"integer",
                "rst_DisplayName": "to:",
                "rst_DisplayHelpText": "Value from 0 (transparent) to 1 (opaque)"
        }},
        {"dtID": "fillColor1",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "Fill color from:",
                "rst_DisplayHelpText": "",
                "rst_DisplayWidth": 17,
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "fillColor2",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "to:",
                "rst_DisplayWidth": 17,
                "rst_DisplayHelpText": "",
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "fillOpacity1",
            "dtFields":{
                "dty_Type":"float",
                "rst_DisplayName": "Fill opacity from:",
                "rst_DisplayHelpText": "Value from 0 (transparent) to 1 (opaque)"
        }},
        {"dtID": "fillOpacity2",
            "dtFields":{
                "dty_Type":"float",
                "rst_DisplayName": "to:",
                "rst_DisplayHelpText": "Value from 0 (transparent) to 1 (opaque)"
        }},
        {"dtID": "iconSize1",
            "dtFields":{
                "dty_Type":"integer",
                "rst_DisplayName": "Icon size from:"
        }},
        {"dtID": "iconSize2",
            "dtFields":{
                "dty_Type":"integer",
                "rst_DisplayName": "to:"
        }}
        ];
        
    }
    else if(mode_edit === 6){

        editFields = [
            {"dtID": "iconColour",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Icon color:",
                    "rst_DisplayWidth": 17,
                    "rst_DisplayHelpText": "",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
                }
            },
            {"dtID": "iconOpacity",
                "dtFields":{
                    "dty_Type":"float",
                    "rst_DisplayName": "Icon opacity:",
                    "rst_DisplayHelpText": "Value from 0 (transparent) to 100 (opaque)"
                }
            },
            {"dtID": "fillColour",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Circle colour:",
                    "rst_DisplayWidth": 17,
                    "rst_DisplayHelpText": "Fill color. Defaults to the value of the color option",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
                }
            },
            {"dtID": "fillOpacity",
                "dtFields":{
                    "dty_Type":"float",
                    "rst_DisplayName": "Circle opacity:",
                    "rst_DisplayHelpText": "Value from 0 (transparent) to 100 (opaque)"
                }
            }
        ];
    }
    else if(mode_edit === 7){

        editFields = [
            {"dtID": "lineColour",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Line color:",
                    "rst_DisplayWidth": 17,
                    "rst_DisplayHelpText": "",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
                }
            },
            // {"dtID": "weight",
            //     "dtFields":{
            //         "dty_Type":"integer",
            //         "rst_DisplayName": "Stroke width:",
            //         "rst_DisplayHelpText": "Stroke width in pixels"
            //     }
            // },
            {"dtID": "lineOpacity",
                "dtFields":{
                    "dty_Type":"float",
                    "rst_DisplayName": "Line opacity:",
                    "rst_DisplayHelpText": "Value from 0 (transparent) to 100 (opaque)"
                }
            }
        ];
    }
    else{
        
        if(mode_edit===3 && maplayer_query){ 
            
                let request = { q: maplayer_query,
                        w: 'a',
                        detail: 'count_by_rty'};

                let that = this;
                window.HAPI4.RecordMgr.search(request, function(response){ 

                    if(response.status == window.hWin.ResponseStatus.OK){

                        if(response.data && $.isPlainObject(response.data.recordtypes)){
                            maplayer_rty = Object.keys(response.data.recordtypes);
                        }
                    }else{
                        console.error(response.message);
                    }
                });
        }
        
        
        editFields = [                
        {
        "groupHeader": "Symbols",
        "groupTitleVisible": true,
        "groupType": "group",
            "children":[

        {"dtID": "iconType",
            "dtFields":{
                "dty_Type":"enum",
                "rst_DisplayName": "Icon source:",
                "rst_DefaultValue": "y",
                "rst_DisplayHelpText": "Define type and source of icon",
                "rst_FieldConfig":[
                    {"key":"url","title":"Image"},
                    {"key":"iconfont","title":"Icon font"},
                    {"key":"circle","title":"Circle"},
                    {"key":"rectype","title":"Record type icon"} //change to thematic mapping
                    //{"key":"","title":"Default marker"}
                ]
        }},
        {"dtID": "iconUrl",
            "dtFields":{
                "dty_Type":"url",
                "rst_DisplayName": "Icon URL:",
                "rst_DisplayWidth":40,
                "rst_Display":(current_value['iconType']=='url'?"visible":"hidden")
        }},
        {"dtID": "iconFont",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "Icon:",
                "rst_DisplayWidth":40,
                "rst_Display":(current_value['iconType']=='iconfont'?"visible":"hidden"),
                "rst_DefaultValue": "location",
                "rst_DisplayHelpText": "Define name of icon from set: <a href='http://mkkeck.github.io/jquery-ui-iconfont/' target=_blank>jQuery UI Icon Font</a> or <a href='https://fontawesome.com/search?ic=free-collection' target=_blank>Font Awesome</a>"
        }},
        {"dtID": "iconSize",
            "dtFields":{
                "dty_Type":"integer",
                "rst_DisplayName": "Icon size:",
                "rst_DisplayWidth": 5,
                "rst_DefaultValue": 18,
                "rst_DisplayHelpText": "Icon size in pixels",
                "rst_Spinner": "1",
                "rst_MinValue": "0"
            }
        }
        
        ]},

        {
        "groupHeader": "Outline",
        "groupTitleVisible": true,
        "groupType": "group",
            "children":[
       
        
        {"dtID": "stroke",
            "dtFields":{
                "dty_Type":"enum",
                "rst_DisplayName": "Stroke:",
                "rst_DefaultValue": "1",
                "rst_DisplayHelpText": "Whether to draw stroke along the path. Set it to false to disable borders on polygons or circles.",
                "rst_FieldConfig":
                (mode_edit===4)?
                [{"key":"","title":"&nbsp;"},
                    {"key":"0","title":"No"},
                    {"key":"1","title":"Yes"}
                ]
                :[
                    {"key":"0","title":"No"},
                    {"key":"1","title":"Yes"}
                ]
        }},
        {"dtID": "color",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "Stroke color:",
                "rst_DisplayWidth": 17,
                "rst_DisplayHelpText": "",
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "weight",
            "dtFields":{
                "dty_Type":"float",
                "rst_DisplayName": "Stroke width:",
                "rst_DisplayWidth": 5,
                "rst_DisplayHelpText": "Stroke width in pixels",
                "rst_Spinner": "1",
                "rst_MinValue": "0"
            }
        },
        {"dtID": "dashArray",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "Dash array:",
                "rst_DisplayHelpText": "A string that defines the stroke <a href='https://developer.mozilla.org/en-US/docs/Web/SVG/Attribute/stroke-dasharray' target=_blank> dash pattern</a>."
        }},
        {"dtID": "opacity",
            "dtFields":{
                "dty_Type":"float",
                "rst_DisplayName": "Stroke opacity:",
                "rst_DisplayWidth": 5,
                "rst_DisplayHelpText": "Value from 0 (transparent) to 1 (opaque)",
                "rst_Spinner": "1",
                "rst_SpinnerStep": "0.1",
                "rst_MinValue": "0",
                "rst_MaxValue": "1"
            }
        }
        
        ]},

        {
        "groupHeader": "Area fill",
        "groupTitleVisible": true,
        "groupType": "group",
            "children":[
        
        /*                    
        lineCap    String    'round'    A string that defines shape to be used at the end of the stroke.
        lineJoin    String    'round'    A string that defines shape to be used at the corners of the stroke.
        dashArray    String    null    A string that defines the stroke dash pattern. Doesn't work on Canvas-powered layers in some old browsers.
        dashOffset    String    null    A string that defines the distance into the dash pattern to start the dash. Doesn't work on Canvas-powered layers in some old browsers.
        */                    
        {"dtID": "fill",
            "dtFields":{
                "dty_Type":"enum",
                "rst_DisplayName": "Fill:",
                "rst_DisplayHelpText": "Whether to fill the path with color. Set it to false to disable filling on polygons or circles.",
                "rst_DefaultValue": "1",
                "rst_FieldConfig":
                (mode_edit===4)?
                [{"key":"","title":"&nbsp;"},
                    {"key":"0","title":"No"},
                    {"key":"1","title":"Yes"}
                ]
                :[
                    {"key":"0","title":"No"},
                    {"key":"1","title":"Yes"}
                ]
        }},
        {"dtID": "fillColor",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "Fill color:",
                "rst_DisplayWidth": 17,
                "rst_DisplayHelpText": "Fill color. Defaults to the value of the color option",
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "fillOpacity",
            "dtFields":{
                "dty_Type":"float",
                "rst_DisplayName": "Fill opacity:",
                "rst_DisplayWidth": 5,
                "rst_DisplayHelpText": "Value from 0 (transparent) to 1 (opaque)",
                "rst_Spinner": "1",
                "rst_SpinnerStep": "0.1",
                "rst_MinValue": "0",
                "rst_MaxValue": "1"
            }
        }
        ]}
        //fillRule  A string that defines how the inside of a shape is determined.
        ];
        
        if(mode_edit==3 && maplayer_query){
            
            editFields.push(
                {
                "groupHeader": "Thematic maps",
                "groupTitleVisible": true,
                "groupType": "group",
                    "children":[
                    {"dtID": "thematicMap",
                        "dtFields":{
                        "dty_Type":"blocktext",
                        "rst_DisplayWidth": "50",
                        "rst_DisplayHeight": "2",
                        "rst_DisplayName": "Thematic maps:",
                        "rst_Display": "visible",
                        "rst_DisplayHelpText": "Thematic maps configuration",
                        "rst_FieldConfig":{"thematicmap": maplayer_query}  //use thematic map widget
                        }}
                ]}
            );
        }else if(mode_edit===4){ //legend label for thematic map range
            editFields.unshift({"dtID": "legendLabel",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName":"Legend caption:",
                }});
        }
        
    }
    
    _editing_symbology.initEditForm( editFields, recdata );

    // Gradient values are numeric. HEditing normally renders integer/float fields
    // as text inputs, so use native numeric inputs here to prevent accidental
    // non-numeric values and expose browser increment controls.
    if(mode_edit==5){
        const numericFields = {
            strokeOpacity1:{min:0,max:1,step:0.1},
            strokeOpacity2:{min:0,max:1,step:0.1},
            fillOpacity1:{min:0,max:1,step:0.1},
            fillOpacity2:{min:0,max:1,step:0.1},
            iconSize1:{min:0,step:1},
            iconSize2:{min:0,step:1}
        };

        $.each(numericFields, function(fieldName, limits){
            const inputs = _editing_symbology.getInputs(fieldName);
            if(!inputs) return;
            $(inputs).each(function(){
                $(this).attr($.extend({type:'number'}, limits));
            });
        });
    }

    // Live previews are available for every map-symbol mode. Graph node/edge
    // editors (6/7) use a different symbol schema and remain intentionally separate.
    if(mode_edit!==6 && mode_edit!==7){

        const __initSymbolPreviews = function(){
            if(!window.hWin.HEURIST4.ui.renderMapSymbolPreview) return;

            const previews = [];

            function __addPreview(beforeFieldName, geometryType, label){
                let field = _editing_symbology.getFieldByName(beforeFieldName);
                if(!field || field.length===0) return;

                let row = $('<div class="map-symbol-preview-row">')
                    .css({display:'block',margin:'3px 0 7px 0','min-height':'36px',
                          'line-height':'34px','white-space':'nowrap'})
                    .insertBefore(field);

                // Match the normal symbology field layout: fixed label column followed
                // by a compact input/sample column.
                $('<span>').text(label).css({display:'inline-block',width:'150px',
                        'text-align':'right','padding-right':'5px','vertical-align':'middle'})
                    .appendTo(row);

                let sample = $('<span>')
                    .css({display:'inline-block',width:'90px',height:'34px',
                          'vertical-align':'middle','line-height':'normal'})
                    .appendTo(row);

                previews.push({element:sample, geometryType:geometryType});
            }

            if(mode_edit===5){
                // Gradient endpoints belong together. Keep both previews on one row
                // rather than inserting two full-width preview rows into the form.
                let field = _editing_symbology.getFieldByName('strokeColor1');
                if(field && field.length){
                    let row = $('<div class="map-symbol-preview-row map-symbol-gradient-preview-row">')
                        .css({display:'block',margin:'3px 0 7px 0','min-height':'36px',
                              'line-height':'34px','white-space':'nowrap'})
                        .insertBefore(field);

                    $('<span>').text('Preview:').css({display:'inline-block',width:'150px',
                            'text-align':'right','padding-right':'5px','vertical-align':'middle'})
                        .appendTo(row);

                    ['From','To'].forEach(function(label){
                        $('<span>').text(label+':').css({display:'inline-block','margin-left':'8px',
                                'margin-right':'4px','vertical-align':'middle'})
                            .appendTo(row);
                        let sample = $('<span>')
                            .css({display:'inline-block',width:'70px',height:'34px',
                                  'vertical-align':'middle','line-height':'normal'})
                            .appendTo(row);
                        previews.push({element:sample, geometryType:null});
                    });
                }
            }else if(mode_edit===2){
                __addPreview('color', 'line', 'Preview:');
                __addPreview('fillColor', 'polygon', 'Preview:');
            }else{
                __addPreview('iconType', 'point', 'Preview:');
                __addPreview('stroke', 'line', 'Preview:');
                __addPreview('fill', 'polygon', 'Preview:');
            }

            _refreshSymbolPreviews = function(){
                let editedSymbol = _editing_symbology.getValues() || {};
                editedSymbol = window.hWin.HEURIST4.util.cloneJSON(editedSymbol);

                if(mode_edit===5){
                    const endpoints = [
                        {color:editedSymbol.strokeColor1, opacity:editedSymbol.strokeOpacity1,
                         fillColor:editedSymbol.fillColor1, fillOpacity:editedSymbol.fillOpacity1,
                         iconSize:editedSymbol.iconSize1},
                        {color:editedSymbol.strokeColor2, opacity:editedSymbol.strokeOpacity2,
                         fillColor:editedSymbol.fillColor2, fillOpacity:editedSymbol.fillOpacity2,
                         iconSize:editedSymbol.iconSize2}
                    ];
                    previews.forEach(function(item, index){
                        const symbol = window.hWin.HEURIST4.ui.prepareMapSymbol(endpoints[index] || {}, null);
                        window.hWin.HEURIST4.ui.renderMapSymbolPreview(
                            item.element, symbol, {rectypeId:12});
                    });
                    return;
                }

                // Do not write inherited values back into the editor. They are
                // only the preview base. Empty editor values mean "inherit";
                // explicit false/0 values remain valid overrides.
                let symbol = window.hWin.HEURIST4.util.cloneJSON(_previewDefaultSymbol);
                $.each(editedSymbol, function(key, value){
                    if(value !== null && typeof value !== 'undefined' && value !== ''){
                        symbol[key] = value;
                    }
                });

                if(!window.hWin.HEURIST4.util.isnull(symbol.stroke)){
                    symbol.stroke = window.hWin.HEURIST4.util.istrue(symbol.stroke);
                }
                if(!window.hWin.HEURIST4.util.isnull(symbol.fill)){
                    symbol.fill = window.hWin.HEURIST4.util.istrue(symbol.fill);
                }

                symbol = window.hWin.HEURIST4.ui.prepareMapSymbol(symbol, null);

                // Symbology editing has no reliable record-type context. Use Place.
                previews.forEach(function(item){
                    window.hWin.HEURIST4.ui.renderMapSymbolPreview(
                        item.element, symbol, {geometryType:item.geometryType, rectypeId:12});
                });
            };

            _refreshSymbolPreviews();
        };

        if(window.hWin.HEURIST4.ui.renderMapSymbolPreview){
            __initSymbolPreviews();
        }else{
            $.getScript(window.hWin.HAPI4.baseURL+
                'hclient/widgets/editing/mapSymbolPreview.js', __initSymbolPreviews);
        }
    }

    let edit_buttons = [
        {text:window.hWin.HR('Cancel'), 
            id:'btnRecCancel',
            css:{'float':'right'}, 
            click: function() { 
                edit_symb_dialog.dialog('close'); 
        }},
        {text:window.hWin.HR('Save'),
            id:'btnRecSave',
            css:{'visibility':'hidden', 'float':'right'},  
            click: function() { 
                let res = _editing_symbology.getValues(); //all values
                if(mode_edit!==6 && mode_edit!==7){
                    if(Object.hasOwn(res, 'opacity')){
                        res.opacity = window.hWin.HEURIST4.map.normalizeOpacity(res.opacity);
                    }
                    if(Object.hasOwn(res, 'fillOpacity')){
                        res.fillOpacity = window.hWin.HEURIST4.map.normalizeOpacity(res.fillOpacity);
                    }
                }
                //remove empty values
                let propNames = Object.getOwnPropertyNames(res);
                for (let i = 0; i < propNames.length; i++) {
                    let propName = propNames[i];
                    if (window.hWin.HEURIST4.util.isempty(res[propName])) {
                        delete res[propName];
                    }
                }
                if(res['iconType']=='circle'){
                    res['radius'] = (res['iconSize']>0 ? Number(res['iconSize'])/2 : 4);
                }
                // The nested thematic editor now returns canonical symbology.
                // Keep compatibility with a legacy thematic array during transition.
                let thematicRenderers = window.hWin.HEURIST4.util.cloneJSON(_thematicRenderers);

                if(Object.hasOwn(res, 'thematicMap')){
                    let tmaps = window.hWin.HEURIST4.util.isJSON(res['thematicMap']);
                    delete res['thematicMap'];

                    if($.isPlainObject(tmaps) && Array.isArray(tmaps.thematic)){
                        thematicRenderers = window.hWin.HEURIST4.util.cloneJSON(tmaps.thematic);
                    }else if(Array.isArray(tmaps)){
                        thematicRenderers = [];
                        for(let i=0; i<tmaps.length; i++){
                            if(tmaps[i] && tmaps[i].fields){
                                thematicRenderers.push(tmaps[i]);
                            }
                        }
                    }else if(!tmaps){
                        thematicRenderers = [];
                    }
                }

                // Persist/output the compact canonical form. If there are no thematic
                // renderers, retain the plain symbol object.
                if(thematicRenderers.length>0){
                    res = {
                        symbol: res,
                        thematic: thematicRenderers
                    };
                }

                _editing_symbology.setModified(false);
                _saved = true;
                edit_symb_dialog.dialog('close');
                
                if(window.hWin.HEURIST4.util.isFunction(callback)){
                    callback.call(this, res);
                }

        }}
        
    ];                

    // The dialog wrapper already exists, so all form widgets have been
    // initialised against the correct dialog parent. Add the action buttons and
    // only now make the dialog visible.
    edit_symb_dialog.dialog('option', 'buttons', edit_buttons);
    edit_symb_dialog.dialog('open');
        
        }//on init
    });
    
}//end editSymbology

/**
 * Opens a selector for one geographic field path available from a Heurist query.
 *
 * The selected value is returned as one Heurist field-path code, for example
 * "10:lt134:12:28". Resource fields may be followed through linked records;
 * only geographic fields are selectable. The dialog closes immediately after
 * a geographic field is selected.
 *
 * @memberof Widgets.Editing
 * @param {string|object} mapQuery Heurist query defining the DataSource result set.
 * @param {function} callback Receives the selected field-path code.
 * @param {jQuery|null} parentDialog Optional parent dialog used to host the selector element.
 */
function selectGeoField(mapQuery, callback, parentDialog){

    if(window.hWin.HEURIST4.util.isempty(mapQuery)){
        window.hWin.HEURIST4.msg.showMsgFlash('A query is required to select a geo field', 2000);
        return;
    }

    let request = {
        q: mapQuery,
        w: 'a',
        detail: 'count_by_rty'
    };

    window.HAPI4.RecordMgr.search(request, function(response){

        if(response.status != window.hWin.ResponseStatus.OK){
            window.hWin.HEURIST4.msg.showMsgErr(response);
            return;
        }

        let recordTypes = [];
        if(response.data && $.isPlainObject(response.data.recordtypes)){
            recordTypes = Object.keys(response.data.recordtypes);
        }
        if(recordTypes.length==0){
            window.hWin.HEURIST4.msg.showMsgFlash('No record types found for this query', 2000);
            return;
        }

        let treeData = window.hWin.HEURIST4.dbs.createRectypeStructureTree(
            null, 6, recordTypes, ['geo','resource']);

        if(!treeData || treeData.length==0){
            window.hWin.HEURIST4.msg.showMsgFlash('No geographic fields found for this query', 2000);
            return;
        }
        treeData[0].expanded = true;

        let host = parentDialog && parentDialog.length
            ? parentDialog
            : $(window.hWin.document).find('body');
        let popele = host.find('#divGeoFieldSelector');
        if(popele.length==0){
            popele = $('<div id="divGeoFieldSelector"><div class="rtt-tree"/></div>').appendTo(host);
        }

        let treediv = popele.find('.rtt-tree');
        if(treediv.hasClass('fancytree-container')){
            try{ treediv.fancytree('destroy'); }catch(e){}
        }
        treediv.empty();

        let $dlg = null;

        function __selectNode(node){
            if(!node || window.hWin.HEURIST4.util.isempty(node.data.code)) return;
            if(node.type == 'resource' || node.type == 'rectype') return;

            if(window.hWin.HEURIST4.util.isFunction(callback)){
                callback.call(this, node.data.code);
            }
            if($dlg){
                $dlg.dialog('close');
            }
        }

        treediv.fancytree({
            checkbox: false,
            source: treeData,
            renderNode: function(event, data){
                if(data.node.parent &&
                   (data.node.parent.type == 'resource' || data.node.parent.type == 'rectype')){
                    $(data.node.li).attr('style',
                        'border-left: black solid 1px !important;margin-left: 9px;');
                }
            },
            lazyLoad: function(event, data){
                let node = data.node;
                let parentcode = node.data.code;
                let rectypes = node.data.rt_ids;

                if(parentcode.split(':').length<5){
                    let res = window.hWin.HEURIST4.dbs.createRectypeStructureTree(
                        null, 6, rectypes,
                        (parentcode.split(':').length<3 ? ['geo','resource'] : ['geo']),
                        parentcode);
                    data.result = res.length>1 ? res : res[0].children;
                }else{
                    data.result = [];
                }
                return data;
            },
            click: function(e, data){
                let node = data.node;
                let isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');

                if(node.type != 'resource' && node.type != 'rectype' && !node.lazy){
                    __selectNode(node);
                    return false;
                }

                if($(e.originalEvent.target).is('span') && node.children && node.children.length>0){
                    if(!isExpander){
                        node.setExpanded(!node.isExpanded());
                    }
                }else if(node.lazy && !isExpander){
                    node.setExpanded(true);
                }
            },
            keydown: function(e, data){
                if(e.which === 13 || e.which === 32){
                    let node = data.node;
                    if(node.type != 'resource' && node.type != 'rectype' && !node.lazy){
                        __selectNode(node);
                    }else{
                        node.setExpanded(!node.isExpanded());
                    }
                    return false;
                }
            }
        });

        $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({
            window: window.hWin,
            title: window.hWin.HR('Select geo field'),
            width: 400,
            height: 600,
            element: popele[0],
            resizable: true,
            buttons: [
                {text:window.hWin.HR('Close'), click:function(){ $dlg.dialog('close'); }}
            ],
            default_palette_class: 'ui-heurist-design'
        });
    });
}

