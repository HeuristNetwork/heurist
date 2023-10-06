/*
* editng_exts.js - additional functions for editing_input
*  1) editSymbology - edit map symbol properties 
*  2) calculateImageExtentFromWorldFile - calculate image extents from worldfile
*  3) browseRecords - browse records for resource fields 
*     browseTerms
*       3a) openSearchMenu
*  4) translationSupport - opens popup dialog with ability to define translations for field values
*       4a) translationFromUI 4b) translationToUI    
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

//
//  mode_edit 2 - symbology for general map draw style
//            1 - symbology editor from map legend
//            3 - symbology editor from record edit for map layer
//            4 - symbology editor from thematic map
//            5 - define symbology ranges
// 
function editSymbology(current_value, mode_edit, callback){

    var edit_symb_dialog = null; //assigned on popup_dlg.dialog
    
    var dialog_div_id = 'heurist-dialog-editSymbology'+(mode_edit>=3?mode_edit:'');
    
    var popup_dlg = $('#'+dialog_div_id);
    
    if(popup_dlg.length>0){
        popup_dlg.empty();
    }else{
        popup_dlg = $('<div id="'+dialog_div_id+'">')
            .appendTo( $(window.hWin.document).find('body') );
    }
    
    //heurist query for map query layer is passed via symbology value
    var maplayer_rty = null; //record types in mapquery resultset
    var maplayer_query = true;
    if(current_value && current_value.maplayer_query){
        maplayer_query = current_value.maplayer_query;
        delete current_value.maplayer_query;
    }

    var editForm = $('<div class="ent_content_full editForm" style="top:0">')
    .appendTo($('<div class="ent_wrapper">').appendTo(popup_dlg));

    var _editing_symbology = new hEditing({container:editForm, 
        onchange:
        function(){
            var isChanged = _editing_symbology.isModified();
            var mode = isChanged?'visible':'hidden';
            edit_symb_dialog.parent().find('#btnRecSave').css('visibility', mode);

            if(isChanged){

                var ele = _editing_symbology.getFieldByName('iconType');
                if(ele!=null){
                    var res = ele.editing_input('getValues'); 
                    var ele_icon_url = _editing_symbology.getFieldByName('iconUrl').hide();
                    var ele_icon_font = _editing_symbology.getFieldByName('iconFont').hide();
                    if(res[0]=='url'){
                        ele_icon_url.show();
                    }else if(res[0]=='iconfont'){
                        ele_icon_font.show();
                    }
                }
            }

        },
        oninit: function(){
            
            _editing_symbology = this;
            
            if(current_value){
                //detect base layer symbology
                if($.isArray(current_value)){
                    var thematicMap = [];
                    var baseSymb = {};
                    for(var i=0; i<current_value.length; i++){
                        if(current_value[i].fields){
                            //thematic map
                            thematicMap.push(current_value[i]);
                        }else{
                            baseSymb = current_value[i];
                        }
                    }
                    current_value = baseSymb;
                    current_value.thematicMap = JSON.stringify(thematicMap);
                }
                
                if(mode_edit==4 && window.hWin.HEURIST4.util.isempty(current_value.stroke)){
                    current_value.stroke = '';
                }else{
                    current_value.stroke = window.hWin.HEURIST4.util.istrue(current_value.stroke)?'1':'0';    
                }
                if(mode_edit==4 && window.hWin.HEURIST4.util.isempty(current_value.fill)){
                    current_value.fill = '';
                }else{
                    current_value.fill = window.hWin.HEURIST4.util.istrue(current_value.fill)?'1':'0';
                }
                
            }
            
            var recdata = current_value ? new hRecordSet({count:1, order:[1], 
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
    var editFields;
    if(mode_edit==2){
        editFields = [
        
        {"dtID": "color",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "Stroke color:",
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
                "rst_DisplayHelpText": "Value from 0 (transparent) to 100 (opaque)"
        }},
        
        {"dtID": "fillColor",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "Fill color:",
                "rst_DisplayHelpText": "Fill color. Defaults to the value of the color option",
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "fillOpacity",
            "dtFields":{
                "dty_Type":"float",
                "rst_DisplayName": "Fill opacity:",
                "rst_DisplayHelpText": "Value from 0 (transparent) to 100 (opaque)"
        }}
        ];
        
    }
    else if(mode_edit==5){
        
        editFields = [
        {"dtID": "strokeColor1",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "Stroke color from:",
                "rst_DisplayHelpText": "",
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "strokeColor2",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "to:",
                "rst_DisplayHelpText": "",
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "strokeOpacity1",
            "dtFields":{
                "dty_Type":"integer",
                "rst_DisplayName": "Stroke opacity from :",
                "rst_DisplayHelpText": "Value from 0 (transparent) to 100 (opaque)"
        }},
        {"dtID": "strokeOpacity2",
            "dtFields":{
                "dty_Type":"integer",
                "rst_DisplayName": "to:",
                "rst_DisplayHelpText": "Value from 0 (transparent) to 100 (opaque)"
        }},
        {"dtID": "fillColor1",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "Fill color from:",
                "rst_DisplayHelpText": "",
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "fillColor2",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "to:",
                "rst_DisplayHelpText": "",
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "fillOpacity1",
            "dtFields":{
                "dty_Type":"float",
                "rst_DisplayName": "Fill opacity from:",
                "rst_DisplayHelpText": "Value from 0 (transparent) to 100 (opaque)"
        }},
        {"dtID": "fillOpacity2",
            "dtFields":{
                "dty_Type":"float",
                "rst_DisplayName": "to:",
                "rst_DisplayHelpText": "Value from 0 (transparent) to 100 (opaque)"
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
    else{
        
        var ptr_fields = [];
        if(mode_edit===3){ 
            
            if(maplayer_query){
            
                var request = { q: maplayer_query,
                        w: 'a',
                        detail: 'count_by_rty'};

                var that = this;
                window.HAPI4.RecordMgr.search(request, function(response){ 

                    if(response.status == window.hWin.ResponseStatus.OK){

                        if(response.data && $.isPlainObject(response.data.recordtypes)){
                            maplayer_rty = Object.keys(response.data.recordtypes);
                        }
                    }else{
                        console.error(response.message);
                    }
                });
            
            }else{
            
                //get list of pointer fields
                var rty_as_place = window.hWin.HAPI4.sysinfo['rty_as_place']; 
                rty_as_place = (rty_as_place)?rty_as_place.split(','):[];
                rty_as_place.push((''+window.hWin.HAPI4.sysinfo['dbconst']['RT_PLACE']));
                
                $Db.dty().each2(function(dty_ID, record){
                    
                    var dty_Type = record['dty_Type'];
                    if(record['dty_Type']=='resource') 
                    {
                        is_parent = false;
                        
                        var ptr = record['dty_PtrTargetRectypeIDs'];
                        if(ptr){
                            ptr = ptr.split(',');  
                            const has_entry = ptr.filter(value => rty_as_place.includes(value));
                            if(has_entry.length>0){
                              ptr_fields.push({"key":$Db.getConceptID('dty',dty_ID),"title":record['dty_Name']});  
                            }
                        } 
                    }
                });
            }
        }
        
        
        editFields = [                
        {"dtID": "sym_Name",
            "dtFields":{
                "dty_Type":"freetext",
                //"rst_RequirementType":"required",                        
                "rst_DisplayName":"Name:",
                "rst_Display": (mode_edit===1)?"visible":"hidden"
        }},

        /*
        {"dtID": "geofield2",
            "dtFields":{
                "dty_Type":"enum",
                //"rst_RequirementType":"required",                        
                "rst_DisplayName":"Field to be mapped:",
                "rst_Display": (ptr_fields.length>0 && mode_edit===3)?"visible":"hidden",
                "rst_FieldConfig":ptr_fields, //{"entity":"defDetailTypes","csv":false},
                "rst_DisplayHelpText": "Geo fields from query resultset or linked records to be mapped"
        }},*/

        {"dtID": "geofield",
            "dtFields":{
                "dty_Type":"blocktext",
                //"rst_RequirementType":"required",                        
                "rst_DisplayName":"Field to be mapped:",
                "rst_Display": (maplayer_query && mode_edit===3)?"visible":"hidden",
                "rst_DisplayWidth":50,
                "rst_DisplayHelpText": "Geo fields from query resultset or linked records to be mapped"
        }},
        
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
                "rst_DisplayHelpText": "Define name of icon from set: <a href='http://mkkeck.github.io/jquery-ui-iconfont/' target=_blank>http://mkkeck.github.io/jquery-ui-iconfont/</a>"
        }},
        {"dtID": "iconSize",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName": "Icon size:"
                //"rst_DefaultValue": 18,
        }}
        
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
                "rst_DisplayHelpText": "",
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "weight",
            "dtFields":{
                "dty_Type":"integer",
                "rst_DisplayName": "Stroke width:",
                "rst_DisplayHelpText": "Stroke width in pixels"
        }},
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
                "rst_DisplayHelpText": "Value from 0 (transparent) to 100 (opaque)"
        }}
        
        ]},

        {
        "groupHeader": "Are fill",
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
                "rst_DisplayHelpText": "Fill color. Defaults to the value of the color option",
                "rst_FieldConfig":{"colorpicker":"colorpicker"}  //use colorpicker widget
        }},
        {"dtID": "fillOpacity",
            "dtFields":{
                "dty_Type":"float",
                "rst_DisplayName": "Fill opacity:",
                "rst_DisplayHelpText": "Value from 0 (transparent) to 100 (opaque)"
        }}
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
        }
        
        
    }
    
    _editing_symbology.initEditForm( editFields, recdata );

    var edit_buttons = [
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
                var res = _editing_symbology.getValues(); //all values
                //remove empty values
                var propNames = Object.getOwnPropertyNames(res);
                for (var i = 0; i < propNames.length; i++) {
                    var propName = propNames[i];
                    if (window.hWin.HEURIST4.util.isempty(res[propName])) {
                        delete res[propName];
                    }
                }
                if(res['iconType']=='circle'){
                    res['radius'] = (res['iconSize']>0?res['iconSize']:8);
                }
                if(res['thematicMap']){
                    let tmaps = window.hWin.HEURIST4.util.isJSON(res['thematicMap']);
                    delete res['thematicMap'];
                    if(tmaps){
                        tmaps.unshift(res);
                        res = tmaps;
                    }
                }
                
                _editing_symbology.setModified(false);
                edit_symb_dialog.dialog('close');
                
                if($.isFunction(callback)){
                    callback.call(this, res);
                }

        }}
        
    ];                

    //
    //
    edit_symb_dialog = popup_dlg.dialog({
        autoOpen: true,
        height: (mode_edit==2)?300:((mode_edit==5)?500:700), //((mode_edit==3)?750:700),
        width:  740,
        modal:  true,
        title: window.hWin.HR((mode_edit==5)?'Define symbology gradient values':'Define Symbology'),
        resizeStop: function( event, ui ) {//fix bug
            //that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
        },
        beforeClose: function(){
            //show warning in case of modification
            if(_editing_symbology.isModified()){
                var $dlg, buttons = {};
                buttons['Save'] = function(){ 
                    //that._saveEditAndClose(null, 'close'); 
                    edit_symb_dialog.parent().find('#btnRecSave').click();
                    $dlg.dialog('close'); 
                }; 
                buttons['Ignore and close'] = function(){ 
                    _editing_symbology.setModified(false);
                    edit_symb_dialog.dialog('close'); 
                    $dlg.dialog('close'); 
                };

                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                    window.hWin.HR('Warn_Lost_Data'),
                    buttons,
                    {title: window.hWin.HR('Confirm'),
                       yes: window.hWin.HR('Save'),
                        no: window.hWin.HR('Ignore and close')},
                    {default_palette_class:'ui-heurist-design'});
                return false;   
            }
            return true;
        },

        buttons: edit_buttons
    });                

    edit_symb_dialog.parent().addClass('ui-heurist-design');
    
    if(mode_edit==3 && maplayer_query){
            
            var intputs  = _editing_symbology.getInputs('geofield');
            var geofield_input = $(intputs[0]);
            var geofield_lbls = $('<div>').insertBefore(geofield_input);
            geofield_lbls
                .css({background:geofield_input.css('background'), 
                      cursor:'pointer',
                      padding: '2px',
                      width: '500px',
                     'fonst-size':'10px !important',   
                     'fonst-style':'italic', 
                     'text-decoration':'underline'});
            geofield_input.hide();
            
            var titles = [];
            if(current_value['geofield']){
                var codes = current_value['geofield'].split(',');
                for(var i=0; i<codes.length; i++){
                    var code = codes[i];
                    if(code && code.indexOf(':')>0){
                        var harchy = $Db.getHierarchyTitles(code);
                        if(harchy){
                            titles.push(harchy.harchy.join(''));
                        }
                    }
                }
            }
            if(titles.length>0){
                geofield_lbls.html(titles.join('<br>'));    
            }else{
                geofield_lbls.html(window.hWin.HR('Click to select geo fields'));    
            }
            
            

            $(geofield_lbls).on({click: function(e){
                
                if(!maplayer_rty || maplayer_rty.length==0) return;
                
                maplayer_rty_treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, maplayer_rty, ['geo','resource'] );                 
                
            var popele = edit_symb_dialog.find('#divFieldSelector');
            if(popele.length==0){
                   popele = $('<div id="divFieldSelector"><div class="rtt-tree"/></div>').appendTo(edit_symb_dialog);
            }
            
            maplayer_rty_treedata[0].expanded = true;
                
            var treediv = popele.find('.rtt-tree');
            
                treediv.fancytree({
            checkbox: true,
            selectMode: 3,  // single
            source: maplayer_rty_treedata,
            beforeSelect: function(event, data){
                // A node is about to be selected: prevent this, for folder-nodes:
                if( data.node.hasChildren() ){
                    return false;
                }
            },
            renderNode: function(event, data){
                if(data.node.parent && (data.node.parent.data.type == 'resource' || data.node.parent.data.type == 'rectype')){ // add left border+margin
                    $(data.node.li).attr('style', 'border-left: black solid 1px !important;margin-left: 9px;');
                }
                if(data.node.data.type == 'resource' || data.node.data.type == 'rectype'){
                    $(data.node.li).find('.fancytree-checkbox').hide();
                }
            },
            lazyLoad: function(event, data){
                var node = data.node;
                var parentcode = node.data.code; 
                var rectypes = node.data.rt_ids;

                if(parentcode.split(":").length<5){  //limit with 3 levels
                
                    var res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, 
                        rectypes, (parentcode.split(":").length<3?['geo','resource']:['geo']), parentcode );
                    if(res.length>1){
                        data.result = res;
                    }else{
                        data.result = res[0].children;
                    }

                }else{
                    data.result = [];
                }                            

                return data;                                                   
            },
            loadChildren: function(e, data){
                setTimeout(function(){
                    //that._assignSelectedFields();
                    },500);
            },
            click: function(e, data){
                
                var isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');
                var setDefaults = !data.node.isExpanded();

                if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                    if(!isExpander){
                        data.node.setExpanded(!data.node.isExpanded());
                    }
                }else if( data.node.lazy && !isExpander) {
                    data.node.setExpanded( true );
                }
            },
            keydown: function(e, data) {
                if( e.which === 32 ) {
                    data.node.toggleSelected();
                    return false;
                }
            }
        });
                
                var dlg2, btns = [
                    {text:window.hWin.HR('Apply'),
                        click: function(){
                            
                            var tree = treediv.fancytree("getTree");
                            var fieldIds = tree.getSelectedNodes(false);
                            var k, len = fieldIds.length;
                            var selectedFields = [], titles = [];
                            
                            for (k=0;k<len;k++){
                                var node =  fieldIds[k];
                                if(window.hWin.HEURIST4.util.isempty(node.data.code)) continue;
                                
                                if(!node.children || node.children.length==0){
                                    var code = node.data.code;
                                    selectedFields.push(code);
                                    var harchy = $Db.getHierarchyTitles(code);
                                    titles.push(harchy.harchy.join(''));
                                }
                            }                            
                            //geofield_input.val(selectedFields.join(','));
                            //_editing_symbology.setModified(true);
                            _editing_symbology.setFieldValueByName2('geofield',selectedFields.join(','),true);
                            geofield_lbls.html(titles.join('<br>'));
                            $dlg2.dialog('close');
                            
                            //

                        }
                    },
                    {text:window.hWin.HR('Close'),
                        click: function() { $dlg2.dialog('close'); }
                    }
                ];
        
                $dlg2 = window.hWin.HEURIST4.msg.showElementAsDialog({
                    window:  window.hWin, //opener is top most heurist window
                    title: window.hWin.HR('Select geo field to be mapped'),
                    width: 400,
                    height: 600,
                    element:  popele[0],
                    resizable: true,
                    buttons: btns,
                    default_palette_class: 'ui-heurist-design'
                });        
                
            }});
        
    }//mode 3
        
    
    
        }//on init
    });
    
}//end editSymbology


//
// Get image dimension and calculate bounding box based on world file parameters
//
function calculateImageExtentFromWorldFile(_editing){

    if(!_editing) return;

    var ulf_ID = null, worldFile = null;
    
    //
    // calculate extent based on worldfile parameters
    //
    var dtId_File = window.hWin.HAPI4.sysinfo['dbconst']['DT_FILE_RESOURCE'];
    var ele = _editing.getFieldByName( dtId_File );
    if(ele){
        var val = ele.editing_input('getValues');
        if(val && val.length>0){
            ulf_ID = val[0]['ulf_ObfuscatedFileID'];    
        }
    }
    var dtId_WorldFile = window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_WORLDFILE'];
    ele = _editing.getFieldByName( dtId_WorldFile );
    if(ele){
        var val = ele.editing_input('getValues');
        if(val && val.length>0 && !window.hWin.HEURIST4.util.isempty( val[0] )){
            worldFile = val[0];    
        }
    }

    if(ulf_ID && worldFile){

        var dtId_Geo = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
        ele = _editing.getFieldByName( dtId_Geo );
        if(!ele){
            window.hWin.HEURIST4.msg.showMsgErr('Image map source record must have Bounding Box field! '
                +'Please correct record type structure.');
        }else{

            window.hWin.HEURIST4.msg.showMsgDlg(
                '<p>Recalculate image extent based on these parameters and image dimensions. </p>'+
                '<p>You can also define extent directly by drawing rectangle in map digitizer</p>',
                function() {
                    //get image dimensions
                    window.hWin.HAPI4.checkImage('Records', ulf_ID, 
                        null,
                        function(response){
                            if(response!=null && response.status == window.hWin.ResponseStatus.OK){
                                if($.isPlainObject(response.data) && 
                                    response.data.width>0 && response.data.height>0)
                                {
                                    var extentWKT = window.hWin.HEURIST4.geo.parseWorldFile(worldFile, 
                                        response.data.width, response.data.height);

                                    if(extentWKT){
                                        _editing.setFieldValueByName(dtId_Geo, 'pl '+extentWKT);
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr( 'Cannot calculate image extent. Verify your worldfile parameters' );
                                    }

                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr( response.data );                            
                                }
                            }
                    });

                },
                {title:'Calculate image extent', yes:'Proceed', no:'Cancel'});

        }                    
    }else if(!ulf_ID){
        window.hWin.HEURIST4.msg.showMsgFlash('Define image file first');
    }else if(!worldFile){
        window.hWin.HEURIST4.msg.showMsgFlash('Define valid worldfile parameters first');
    }

}

//
// Opening menuWidget's with searching capabilities
// that => context
// $select => jQuery select with hSelect init'd
// has_filter => disable click on search option, to avoid selecting it as the value
//
function openSearchMenu(that, $select, has_filter=true){

    var $menu = $select.hSelect('menuWidget');
    var $inpt = $menu.find('input.input_menu_filter'); //filter input

    if(!$inpt.attr('data-inited')){

        $inpt.attr('data-inited',1);
        
        //reset filter                                
        that._on($menu.find('span.smallbutton'), {click:
        function(event){
            window.hWin.HEURIST4.util.stopEvent(event); 
            var $mnu = $select.hSelect('menuWidget');
            $mnu.find('input.input_menu_filter').val('');
            $mnu.find('li').css('display','list-item');
            $mnu.find('div.not-found').hide();
        }});

        that._on($menu.find('span.show-select-dialog'), {click:
        function(event){
            var $mnu = $select.hSelect('menuWidget');
            if($mnu.find('.ui-menu-item-wrapper:first').css('cursor')!='progress'){
                var foo = $select.hSelect('option','change');//.trigger('change');
                foo.call(this, null, 'select'); //call __onSelectMenu
            }
        }});
        
        var _timeout = 0;
        
        //set filter
        that._on($menu, {
            click:function(event){
                window.hWin.HEURIST4.util.stopEvent(event);
                return false;                       
            },
            keyup:function(event){
                var val = $(event.target).val().toLowerCase();
                window.hWin.HEURIST4.util.stopEvent(event);                       
                var $mnu = $select.hSelect('menuWidget');
                if(val.length<2){
                    $mnu.find('li').css('display','list-item');
                    $mnu.find('div.not-found').hide();
                }else{
                    if(_timeout==0){
                        $mnu.find('.ui-menu-item-wrapper').css('cursor','progress');
                    }

                    let key = that.f('rst_RecTypeID')+'-'+that.f('rst_DetailTypeID');
                        let showing_option = false;
                    $.each($mnu.find('.ui-menu-item-wrapper'), function(i,item){

                        let title = $(item).text().toLowerCase();
                        if($select.attr('rectype-select') == 1 && window.hWin.HEURIST4.browseRecordCache.hasOwnProperty(key)){
                            title = window.hWin.HEURIST4.browseRecordCache[key][i]['rec_Title'].toLowerCase();
                            title = title.replace(/[\r\n]+/g, ' ');
                        }

                        if(title.indexOf(val)>=0){
                            $(item).parent().css('display','list-item');
                                showing_option = true;
                        }else{
                            $(item).parent().css('display','none');
                        }
                    });
                    $mnu.find('div.not-found').css('display',
                            !showing_option?'block':'none');
                    _timeout = setTimeout(function(){$mnu.find('.ui-menu-item-wrapper').css('cursor','default');_timeout=0;},500);
                }                                    
            }
        });

		if(has_filter){			
 
            let start_pos = 0;

			that._on($menu.find('li.ui-menu-item:first'), {
				click: function(event){ // stop click for menu filter option
                    if ($(event.target).parents('.show-select-dialog').length==0){
					    window.hWin.HEURIST4.util.stopEvent(event);
					    return false;
                    }
				},
                keydown: function(event){ // allow hotkeys for input filter

                    /**
                     * Allows:
                     *  Space bar to add a space (default was select and close)
                     *  Press Enter to auto select the only displayed option
                     *  Press Tab to change focus to the dropdown's first item
                     *  Control/Meta + 'A' to select all input, and
                     *  Highlighting input via arrow keys, control/meta, and shift keys
                     */

                    let $input = $menu.find('.input_menu_filter');
                    let cur_val = $input.val();

                    let code = event.keyCode || event.which;

                    let ctrl_pressed = event.ctrlKey || event.metaKey;
                    let shift_pressed = event.shiftKey;

                    let is_enter = event.key == "Enter" || code == 13;
                    let is_tab = event.key == "Tab" || code == 9;

                    let left_arrow = event.key == "ArrowLeft" || code == 37;
                    let right_arrow = event.key == "ArrowRight" || code == 39;

                    let add_space = event.key == " " || code == 32 || (is_enter && (shift_pressed || ctrl_pressed));

                    if(add_space){

                        window.hWin.HEURIST4.util.stopEvent(event);
                        event.stopImmediatePropagation();

                        $input.val(cur_val + ' ');
                        start_pos = ++cur_val.length;
                    }else if(is_enter && $menu.find('.ui-menu-item:visible').length == 2){ // auto select only result

                        window.hWin.HEURIST4.util.stopEvent(event);
                        event.stopImmediatePropagation();

                        $($menu.find('.ui-menu-item:visible')[1]).click();
                    }else if(is_tab && $menu.find('.ui-menu-item:visible').length > 1){ // focus first item

                        window.hWin.HEURIST4.util.stopEvent(event);
                        event.stopImmediatePropagation();

                        $($menu.find('.ui-menu-item:visible')[1]).mouseover();
                    }else if((event.key == "A" || code == 13) && ctrl_pressed){

                        window.hWin.HEURIST4.util.stopEvent(event);
                        event.stopImmediatePropagation();

                        $input[0].setSelectionRange(0, cur_val.length);
                        start_pos = cur_val.length;
                    }else if(left_arrow || right_arrow){

                        // ensure start is within bounds
                        start_pos = start_pos < 0 ? 0 : start_pos;
                        start_pos = start_pos > cur_val.length ? cur_val.length : start_pos;

                        let swap_start = false;
                        let cur_start = $input[0].selectionStart;
                        let end_pos = $input[0].selectionEnd;

                        if(ctrl_pressed && shift_pressed){

                            if(cur_start == end_pos){
                                start_pos = right_arrow ? cur_start : 0;
                                end_pos = right_arrow ? cur_val.length : end_pos;
                                swap_start = right_arrow;
                            }else if(start_pos == end_pos){ // already selected section
                                start_pos = cur_start;
                                end_pos = right_arrow ? cur_val.length : start_pos;
                                swap_start = true;
                            }else{
                                end_pos = end_pos;
                                start_pos = right_arrow ? end_pos : 0;
                            }

                        }else if(shift_pressed){

                            if(cur_start == end_pos){
                                start_pos = right_arrow ? cur_start : --cur_start;
                                end_pos = right_arrow ? ++end_pos : end_pos;
                                swap_start = right_arrow;
                            }else if(start_pos == end_pos){ // already selected section
                                start_pos = cur_start;
                                end_pos = right_arrow ? ++end_pos : --end_pos;
                                swap_start = true;
                            }else{
                                start_pos = right_arrow ? ++cur_start : --cur_start;
                                end_pos = end_pos;
                            }

                        }else if(ctrl_pressed){

                            start_pos = right_arrow ? cur_val.length : 0;
                            end_pos = start_pos;

                        }else{

                            if(cur_start == end_pos){
                                start_pos = right_arrow ? ++start_pos : --start_pos;
                            }else if(start_pos == end_pos){
                                start_pos = right_arrow ? start_pos : cur_start; 
                            }else{
                                start_pos = right_arrow ? end_pos : start_pos;
                            }

                            start_pos = start_pos < 0 ? 0 : start_pos;
                            start_pos = start_pos > cur_val.length ? cur_val.length : start_pos;

                            end_pos = start_pos;
                        }

                        $input[0].setSelectionRange(start_pos, end_pos);

                        if(swap_start){ // replace start_pos w/ end_pos
                            start_pos = end_pos;
                        }

                    }else{
                        ++start_pos
                    }
                }
			});
		}
        
        var btn_add_term = $menu.find('.add-trm');
        if(btn_add_term.length>0){
            that._on(btn_add_term, {
                click: function(){

                    var suggested_name = $menu.find('input.input_menu_filter').val();
                    var vocab_id = that.f('rst_FilteredJsonTermIDTree');

                    var rg_options = {
                        isdialog: true, 
                        select_mode: 'manager',
                        edit_mode: 'editonly',
                        height: 240,
                        rec_ID: -1,
                        trm_VocabularyID: vocab_id,
                        suggested_name: suggested_name, 
                        create_one_term: true,
                        onClose: function(trm_id){
                            var trm_info = $Db.trm(trm_id, 'trm_ParentTermID'); 
                            if(trm_info > 0){
                                if(that.selObj){
                                    var ref_id = that.selObj.attr('ref-id');
                                    that.selObj.remove();    
                                    that.selObj = null;
                                    
                                    var $input = that.element.find('#'+ref_id);
                                    browseTerms(that, $input, trm_id);                                    
                                }
                            }
                        }
                    };

                    window.hWin.HEURIST4.ui.showEntityDialog('defTerms', rg_options);
                }
            });        
        }

        var $trm_btns = $select.parents('.input-div').find('.btn_add_term, .btn_add_term');
        if($trm_btns.length > 0){
            $trm_btns.clone(true, true).css({
                'position': 'relative',
                'margin': '0px 2.5px'
            }).appendTo($menu.find('span.trm-btns'));
        }

        $inpt.parents('.ui-menu-item-wrapper').removeClass('ui-menu-item-wrapper ui-state-active');
    }

    // Alter width of menu for term fields
    let enum_fld = $select.parents('.input-div').find('.enum-selector');
    if(enum_fld.length > 0){

        $menu.width('auto');
        let width = $menu.width();

        if(width < 200){
            $menu.width(200);
        }else{
            $menu.width(width+10); // make slightly bigger than needed to avoid resizing
        }
    }

    $inpt.focus();
}

//
// It uses window.hWin.HEURIST4.browseRecordCache
// It returns selection function that opens record selection popup dialog
//
function browseRecords(_editing_input, $input){
    
    var that = _editing_input;
    
    var $inputdiv = $input.parent(); //div.input-div
    var __current_input_id = $input.attr('id');

    if ($inputdiv.find('.sel_link2 > .ui-button-icon').hasClass('rotate')) return;
    
    var isparententity = (that.f('rst_CreateChildIfRecPtr')==1);
    var pointerMode = that.f('rst_PointerMode');
    
    if(isparententity && pointerMode!='addonly'){
        pointerMode = 'dropdown_add'; //was 'addorbrowse';
    }
    
    var is_dropdown = (pointerMode && pointerMode.indexOf('dropdown')===0);
    
    var s_action = '';
    if(pointerMode=='addonly'){
        s_action = 'create';
    }else if(pointerMode=='browseonly' || pointerMode=='dropdown'){
        s_action = 'select';
        pointerMode = 'browseonly';
    }else{
        s_action = 'select or create';
        pointerMode = 'addorbrowse';
    }

    var popup_options = {
                    select_mode: (that.configMode.csv==true?'select_multi':'select_single'),
                    select_return_mode: 'recordset',
                    edit_mode: 'popup',
                    selectOnSave: true, //it means that select popup will be closed after add/edit is completed
                    title: window.hWin.HR((isparententity)
                        ?('CHILD record pointer: '+s_action+' a linked child record')
                        :('Record pointer: '+s_action+' a linked record')),
                    rectype_set: that.f('rst_PtrFilteredIDs'),
                    pointer_mode: pointerMode,
                    pointer_filter: that.f('rst_PointerBrowseFilter'),  //initial filter
                    pointer_field_id: (isparententity)?0:that.options.dtID,
                    pointer_source_rectype:  (isparententity)?0:that.options.rectypeID,
                    parententity: (isparententity)?that.options.recID:0,
                    
                    onselect: function(event, data){
                        
                             if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){

                                var f_id = $('#'+__current_input_id).parents('fieldset').attr('id');
                                
                                if(!f_id && that.options.editing){
                                    //for parent-child there is chance that edit form can be reloaded after open this popup
                                    //and original target elements will be missed (it saves record to obtain title)
                                    //we have to find new targets 
                                    var edit_ele = that.options.editing.getFieldByName(that.options.dtID);    
                                            
                                    $input = null;   
                                    var inputs = edit_ele.editing_input('getInputs');
                                    for (var idx in inputs) {
                                        //$(edit_ele.editing_input('getInputs')[idx])
                                        if($(inputs[idx]).parent().find('.child_rec_fld:visible').length>0){
                                            $inputdiv = $(inputs[idx]).parent();
                                            $input = inputs[idx];
                                            break;
                                        }
                                    }
                                    if(!$input){ //last resort - take last one
                                       $input = inputs[inputs.length-1];
                                       $inputdiv = $input.parent(); 
                                    }
                                }
                                
                                 
                                var recordset = data.selection;
                                var record = recordset.getFirstRecord();
                                
                                var rec_Title = recordset.fld(record,'rec_Title');
                                if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                                    // no proper selection 
                                    // consider that record was not saved - it returns FlagTemporary=1 
                                    return;
                                }
                               
                                var targetID = recordset.fld(record,'rec_ID');
                                var rec_Title = recordset.fld(record,'rec_Title');
                                var rec_RecType = recordset.fld(record,'rec_RecTypeID');
                                
                                that.newvalues[$input.attr('id')] = targetID;
                                $input.attr('data-value', targetID); //that's more reliable
                                
                                //save last 25 selected records
                                var now_selected = data.selection.getIds(25);
                                window.hWin.HAPI4.save_pref('recent_Records', now_selected, 25);      
                                
                                
                                $input.empty();
                                var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($input, 
                                    {rec_ID: targetID, 
                                     rec_Title: rec_Title, 
                                     rec_RecTypeID: rec_RecType,
                                     rec_IsChildRecord:isparententity
                                    }, __show_select_dialog);
                                
                                that.onChange();
                                ele.css({margin:'4px', 'border':'2px red solid !important'});
                                $inputdiv.css('border','4px green solid !important');
                                $input.css('border','1px blue solid');

                                if( $inputdiv.find('.link-div').length>0 ){ //hide this button if there are links
                                    $input.show();
                                    $inputdiv.find('.sel_link2').hide(); 
                                }else{
                                    $input.hide();
                                    $inputdiv.find('.sel_link2').show();
                                }
                                
                             }
                    }
    }; //popup_options

    // select/add target record with help of manageRecords popup dialog
    //
    // event is false for confirmation of select mode for parent entity
    // 
    var __show_select_dialog = function(event){
        
            if(that.is_disabled) return;
        
            if(event!==false){
        
                if(event) event.preventDefault();
     
                if(popup_options.parententity>0){
                    
                    if(that.newvalues[$input.attr('id')]>0){
                        
                        window.hWin.HEURIST4.msg.showMsgFlash('Points to a child record; value cannot be changed (delete it or edit the child record itself)', 2500);
                        return;
                    }
                    //__show_select_dialog(false); 
                }
            }
            
            
             // Save record first without validation, only if this is a new record
            if(that.options.editing){
                var et = that.options.editing.getFieldByName('rec_Title');
                
                var isparententity = (that.f('rst_CreateChildIfRecPtr')==1);
                
                if(et && et.editing_input('instance') && et.editing_input('getValues')[0] == '' && isparententity){

                    var is_empty = true;
                    var fields = that.options.editing.getValues(false);
                    for (var dtid in fields) {
                        if(parseInt(dtid)>0 && fields[dtid]!=''){
                            is_empty = false;
                            break;
                        }
                    }
                    if(is_empty){
                        window.hWin.HEURIST4.msg.showMsgFlash('To add child record you have to define some fields in parent record<br>(it is required to compose valid record title)', 2500);    
                        return;
                    }else if(that.options.editing && $.isFunction(that.options.editing.getOptions().onaction)){
                        //quick save without validation
                        that.options.editing.getOptions().onaction(null, 'save_quick');
                    }
                }
            }
            
            
            var usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_'+that.configMode.entity, 
                {width: null,  //null triggers default width within particular widget
                height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });

            popup_options.width = Math.max(usrPreferences.width,710);
            popup_options.height = (s_action=='create')?160:Math.max(usrPreferences.height,600);
            
            if(pointerMode!='browseonly' && that.options.editing && that.configMode.entity=='records'){
                
                var ele = that.options.editing.getFieldByName('rec_OwnerUGrpID');
                if(ele){
                    var vals = ele.editing_input('getValues');
                    ele = that.options.editing.getFieldByName('rec_NonOwnerVisibility');
                    var vals2 = ele.editing_input('getValues');
                    popup_options.new_record_params = {};
                    popup_options.new_record_params['ro'] = vals[0];
                    popup_options.new_record_params['rv'] = vals2[0];
                }
            }
            
            //init related/liked records selection dialog - selectRecord
            window.hWin.HEURIST4.ui.showEntityDialog(that.configMode.entity, popup_options);
    }

    
    if(is_dropdown && !isparententity && !(popup_options.parententity>0)){
        
        // select target record from cached drop down
        //
        var __show_select_dropdown = function(event_or_id){
          
            if(that.is_disabled) return;
            
            var $input, $inputdiv, ref_id;
            
            if(typeof event_or_id == 'string'){
                
                ref_id = event_or_id;
                $input = that.element.find('#'+ref_id);
                $inputdiv = $input.parents('.input-div');
                
            }else
            if(event_or_id && event_or_id.target){
                
                var event = event_or_id;
            
                $inputdiv = $(event.target).parents('.input-div');
                $input = $inputdiv.find('div:first');
                ref_id = $input.attr('id');

                if(event) event.preventDefault();
            }
            
            
            var key = that.f('rst_RecTypeID')+'-'+that.f('rst_DetailTypeID');
			var recordMax = 1000;
    
            if(!window.hWin.HEURIST4.browseRecordCache){
                window.hWin.HEURIST4.browseRecordCache = {};
            }
            if(!window.hWin.HEURIST4.browseRecordTargets){
                window.hWin.HEURIST4.browseRecordTargets = {};
            }
            if(window.hWin.HEURIST4.browseRecordMax){
                recordMax = window.hWin.HEURIST4.browseRecordMax;
            }
            
            if(window.hWin.HEURIST4.browseRecordCache[key]=='zero' || window.hWin.HEURIST4.browseRecordCache[key] > recordMax){
  
                __show_select_dialog(); //show usual dialog
                
            }else if(!window.hWin.HEURIST4.browseRecordCache[key]){ //cache does not exist - search for it
            
                    $inputdiv.find('.sel_link2 > .ui-button-icon').removeClass('ui-icon-triangle-1-e');
                    $inputdiv.find('.sel_link2 > .ui-button-icon').addClass('ui-icon-loading-status-circle rotate');
                
                    var rectype_set = that.f('rst_PtrFilteredIDs');
                    var qobj = (rectype_set)?[{t:rectype_set}]:null;
                    var pointer_filter = that.f('rst_PointerBrowseFilter');
                    if(pointer_filter){
                        if(qobj==null){
                            qobj = pointer_filter;
                        }else{
                            qobj = window.hWin.HEURIST4.query.mergeHeuristQuery(qobj, pointer_filter);
                        }
                    }
                    if(window.hWin.HEURIST4.util.isempty(qobj)){
                        window.hWin.HEURIST4.msg.showMsgFlash('Constraints or browse filter not defined');       
                        setTimeout(__show_select_dialog, 2000);
                        return;
                    }
                    
                    qobj.push({"sortby":"t"}); //sort by title
                    
                    var request = {
                        q: qobj,
                        w: 'a',
                        source:'_browseRecords',
                        detail: 'count'};
                    window.hWin.HAPI4.RecordMgr.search(request, function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            function __assignCache(value){
                                
                                   $inputdiv.find('.sel_link2 > .ui-button-icon').addClass('ui-icon-triangle-1-e');
                                   $inputdiv.find('.sel_link2 > .ui-button-icon').removeClass('ui-icon-loading-status-circle rotate');
                                
                                   window.hWin.HEURIST4.browseRecordCache[key] = value;
                                   if(!rectype_set) rectype_set = 'any';
                                   rectype_set = rectype_set.split(',');
                                   $.each(rectype_set, function(i,rty_id){
                                       var rty_id = ''+rty_id;
                                       if(!window.hWin.HEURIST4.browseRecordTargets[rty_id]){
                                           window.hWin.HEURIST4.browseRecordTargets[rty_id] = [];
                                       }
                                       window.hWin.HEURIST4.browseRecordTargets[rty_id].push(key);
                                   });
                            }
                            
                            if(response.data.count>recordMax){
                                __assignCache(response.data.count);
                                __show_select_dialog();
                            }else if (response.data.count==0){
                                __assignCache('zero');
                                window.hWin.HEURIST4.msg.showMsgFlash('No records for Browse filter');
                                setTimeout(__show_select_dialog, 1000);
                            }else{
                                
                                var request = {
                                    q: qobj,
                                    restapi: 1,
                                    columns:['rec_ID', 'rec_RecTypeID', 'rec_Title'],
                                    zip: 1,
                                    format:'json'};
                                
                                that.is_disabled = true;
                                
                                if(!that.selObj){
                                    that._off($(that.selObj), 'change');   
                                    $(that.selObj).remove();   
                                    that.selObj = null;
                                }
                                    
                                window.hWin.HAPI4.RecordMgr.search_new(request,
                                function(response){
                                   that.is_disabled = false;
                                   if(window.hWin.HEURIST4.util.isJSON(response)) {
                                       if(response['records'] && response['records'].length>0){

                                           //keep in cache
                                           __assignCache(response['records']);
                                           __show_select_dropdown(ref_id); //call again after loading list of records

                                       }else{
                                           //nothing found
                                           __assignCache('zero');
                                           window.hWin.HEURIST4.msg.showMsgFlash('No records for Browse filter');
                                               setTimeout(__show_select_dialog, 1000);
                                       }
                                   }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);       
                                   }
                                });
                                
                            }
                        }
                    });
                        
                    return;
            }else{
                //load from cache
                
                //recreate dropdown
                if(!that.selObj || !$(that.selObj).hSelect('instance')){

                    if(that.selObj){
                        $(that.selObj).remove();
                    }
                    
                    that.selObj = window.hWin.HEURIST4.ui.createSelector(null);//, [{key:'select', title:'Search/Add'}]);

                    $(that.selObj).attr('rectype-select', 1);
                    $(that.selObj).appendTo($inputdiv);
                    $(that.selObj).hide();

                    var search_icon = window.hWin.HAPI4.baseURL+'hclient/assets/magglass_12x11.gif',
                        filter_icon = window.hWin.HAPI4.baseURL+'hclient/assets/filter_icon_black18.png';
                    var opt = window.hWin.HEURIST4.ui.addoption(that.selObj, 'select', 
                    '<div style="width:300px;padding:15px 0px">'
                    +'<span style="padding:0px 4px 0 10px;vertical-align:sub">'
                    +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                    + '" class="rt-icon rt-icon2" style="background-image: url(&quot;'+filter_icon+ '&quot;);"/></span>'
                    +'<input class="input_menu_filter" size="10" style="outline: none;background:none;border: 1px solid lightgray;"/>'
+'<span class="smallbutton ui-icon ui-icon-circlesmall-close" tabindex="-1" title="Clear entered value" '
+'style="position:relative; cursor: pointer; outline: none; box-shadow: none; border-color: transparent;"></span>'                   
                    +'<span class="show-select-dialog"><span style="padding:0px 4px 0 20px;vertical-align:sub">'
                    +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                    + '" class="rt-icon rt-icon2" style="background-image: url(&quot;'+search_icon+ '&quot;);"/></span>'
                    + window.hWin.HR('Search') + (s_action=='select'?'':('/' +  window.hWin.HR('Add'))) 
                    + '</span><div class="not-found" style="padding:10px;color:darkgreen;display:none;">'
                    +window.hWin.HR('No records match the filter')+'</div></div>');
                    
                    //$(opt).attr('icon-url', search_icon);
                    
                    $.each(window.hWin.HEURIST4.browseRecordCache[key], function(idx, item){
                        
                        let title = item['rec_Title'].substr(0,64).replace(/[\r\n]+/g, ' ');
                        
                        var opt = window.hWin.HEURIST4.ui.addoption(that.selObj, item['rec_ID'], title); 
                        
                        var icon = window.hWin.HAPI4.iconBaseURL + item['rec_RecTypeID'];
                        $(opt).attr('icon-url', icon);
                        $(opt).attr('data-rty', item['rec_RecTypeID']);
                    });
                    
                    var events = {};
                    events['onOpenMenu'] = function(){

                        var ele = that.selObj.hSelect('menuWidget');
                        ele.css('max-width', '500px');
                        ele.find('div.ui-menu-item-wrapper').addClass('truncate');
                        ele.find('.rt-icon').css({width:'12px',height:'12px','margin-right':'10px'});
                        ele.find('.rt-icon2').css({'margin-right':'0px'});

                        openSearchMenu(that, that.selObj, true);
                    };

                    events['onSelectMenu'] = function ( event ){
                        
                        var $mnu = that.selObj.hSelect('menuWidget');
                        if($mnu.find('.ui-menu-item-wrapper:first').css('cursor')=='progress'){
                            openSearchMenu(that, that.selObj, false);
                            return;
                        }
                        
                        var targetID = (event) ?$(event.target).val() :$(that.selObj).val();
                        if(!targetID) return;

                        that._off($(that.selObj),'change');
                        
                        if(targetID=='select'){
                           __show_select_dialog(); 
                        }else{

                            var ref_id = $(that.selObj).attr('ref-id');
                            
                            var $input = $('#'+ref_id);
                            var $inputdiv = $('#'+ref_id).parent();

                            var opt = $(that.selObj).find('option:selected');
                            
                            var rec_Title = opt.text();
                            var rec_RecType = opt.attr('data-rty');
                            that.newvalues[$input.attr('id')] = targetID;
                            $input.attr('data-value', targetID); //that's more reliable
                            
                            $input.empty();
                            var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($input, 
                                {rec_ID: targetID, 
                                 rec_Title: rec_Title, 
                                 rec_RecTypeID: rec_RecType,
                                 rec_IsChildRecord:false
                                }, __show_select_dropdown);
                            //ele.appendTo($inputdiv);
                            that.onChange();
                            
                            if( $inputdiv.find('.link-div').length>0 ){ //hide this button if there are links
                                $input.show();
                                $inputdiv.find('.sel_link2').hide(); 
                            }else{
                                $input.hide();
                                $inputdiv.find('.sel_link2').show();
                            }
                        }
                    }

                    $inputdiv.addClass('selectmenu-parent');
                    $(that.selObj).css('max-width','300px');
                    that.selObj = window.hWin.HEURIST4.ui.initHSelect(that.selObj, false,null, events);
                }else{
                    that._off($(that.selObj), 'change');    
                }
                
                //that.find('.selectmenu-parent').removeClass('selectmenu-parent');
                
                var $inpt_ele = $inputdiv.find('.sel_link2'); //button
                var _ref_id = $input.attr('id');
                //$input.addClass('selectmenu-parent');
                
                if($inpt_ele.is(':hidden') && $inputdiv.find('.link-div').length == 1){
                    $inpt_ele = $inputdiv.find('.link-div');
                }
                
                //that.selObj.val('');
                that.selObj.attr('ref-id', _ref_id);
                that.selObj.hSelect('open');
                that.selObj.hSelect('widget').hide();
                var prn = that.selObj.hSelect('menuWidget').parent('div.ui-selectmenu-menu');
                if(prn.length>0){
                    prn.css({'position':'fixed'}); //to show above all 
                }
                that.selObj.hSelect('menuWidget')
                        .position({my: "left top", at: "left bottom", of: $inpt_ele});
                //that._on($(that.selObj),{change:f(){}});
                
            }
        } //__show_select_dropdown
        
    
        that._on( $inputdiv.find('.sel_link2'), { click: __show_select_dropdown } ); //main invocation of dialog - via button "select record"

        return __show_select_dropdown;
    }else{
        that._on( $inputdiv.find('.sel_link2'), { click: __show_select_dialog } );
        return __show_select_dialog;
    }
}


//
// Creates selectmenu that is common for input elements of editing_input
//
function browseTerms(_editing_input, $input, value){
    
    var that = _editing_input;
    
    var $inputdiv = $input.parent(); //div.input-div

        
    function __recreateTrmLabel($input, trm_ID){

        let lang_code = that.options.language;
        if(!window.hWin.HEURIST4.util.isempty(lang_code) && lang_code != 'ALL' && !window.hWin.HAPI4.EntityMgr.getEntityData2('trm_Translation')){ // retrieve translations

            window.hWin.HAPI4.EntityMgr.getTranslatedDefs('defTerms', 'trm', null, function(){
                __recreateTrmLabel($input, trm_ID);
            });
            lang_code = '';
            //return;
        }

        $input.empty();
        if(window.hWin.HEURIST4.util.isNumber(trm_ID) && trm_ID>0){
            
            var trm_Label = $Db.trm_getLabel(trm_ID, lang_code);
            var trm_info = $Db.trm(trm_ID);

            if(trm_info && trm_info.trm_ParentTermID != 0){
                
                while(1){

                    let label = $Db.trm_getLabel(trm_info.trm_ParentTermID, lang_code);
                    trm_info = $Db.trm(trm_info.trm_ParentTermID);

                    if(trm_info.trm_ParentTermID == 0){
                        break;
                    }else{
                        trm_Label = label + '.' +  trm_Label;
                    }
                }
            }

            window.hWin.HEURIST4.ui.addoption($input[0], trm_ID, trm_Label);
            $input.css('min-width', '');
        }else{
            window.hWin.HEURIST4.ui.addoption($input[0], '', '&nbsp;');
            $input.css('min-width', '230px');
            trm_ID = '';
        }
        $input.val(trm_ID);

        if($input.hSelect('instance') !== undefined){
            $input.hSelect('refresh');
        }
    }    

    function __createTermTooltips($input){

        var $menu = $input.hSelect('menuWidget');
        if(!$input.attr('data-tooltips')){

            var $tooltip = null;
            $input.attr('data-tooltips', 1);

            $menu.find('div.ui-menu-item-wrapper')//.filter(() => { return $(this).children().length == 0; })
                 .on('mouseenter', (event) => { // create tooltip

                    let $target_ele = $(event.target);

                    if($target_ele.children().length != 0 || $target_ele.text() == '<blank>'){
                        return;
                    }

                    let name = $target_ele.text();
                    let vocab_id = that.f('rst_FilteredJsonTermIDTree');

                    let term_id = $Db.getTermByLabel(vocab_id, name);
                    let details = '';

                    if(term_id){

                        let term = $Db.trm(term_id);
                        if(!window.hWin.HEURIST4.util.isempty(term.trm_Code)){
                            details += "<span style='text-align: center;'>Code &rArr; " + term.trm_Code + "</span>";
                        }

                        if(!window.hWin.HEURIST4.util.isempty(term.trm_Description)){

                            if(details == ''){
                                details = "<span style='text-align: center;'>Code &rArr; N/A </span>";
                            }
                            details += "<hr/><span>" + term.trm_Description + "</span>";
                        }
                    }

                    if(details == ''){
                        details = "No Description Provided";
                    }

                    $tooltip = $menu.tooltip({
                        items: "div.ui-state-active",
                        position: { // Post it to the right of menu item
                            my: "left+20 center",
                            at: "right center",
                            collision: "none"
                        },
                        show: { // Add slight delay to show
                            delay: 2000,
                            duration: 0
                        },
                        content: function(){ // Provide text
                            return details;
                        },
                        open: function(event, ui){ // Add custom CSS + class
                            ui.tooltip.css({
                                "width": "200px",
                                "background": "rgb(209, 231, 231)",
                                "font-size": "1.1em"
                            })//.addClass('ui-heurist-populate');
                        }
                    });
                 })
                 .on('mouseleave', (event) => { // ensure tooltip is gone
                    if($tooltip && $tooltip.tooltip('instance') != undefined){
                        $tooltip.tooltip('destroy');
                    }
                 });
        }
    }

    function __recreateSelector(){

        if(that.selObj){
            $(that.selObj).remove();
        }


        var allTerms = that.f('rst_FilteredJsonTermIDTree');        
        //headerTerms - disabled terms
        var headerTerms = that.f('rst_TermIDTreeNonSelectableIDs') || that.f('dty_TermIDTreeNonSelectableIDs');
        let lang_code = that.options.language;

        if(window.hWin.HEURIST4.util.isempty(allTerms) &&
            that.options.dtID==window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'])
        { //specific behaviour - show all
            allTerms = 'relation'; //show all possible relations
        }else if(typeof allTerms == 'string' && allTerms.indexOf('-')>0){ //vocabulary concept code
            allTerms = $Db.getLocalID('trm', allTerms);
        }else if(!window.hWin.HEURIST4.util.isempty(lang_code) && lang_code != 'ALL'
            && !window.hWin.HAPI4.EntityMgr.getEntityData2('trm_Translation')){
            window.hWin.HAPI4.EntityMgr.getTranslatedDefs('defTerms', 'trm', null, __recreateSelector);
            return;
        }


        var search_icon = window.hWin.HAPI4.baseURL+'hclient/assets/filter_icon_black18.png';

        var  filter_form = '<div style="padding:10px 0px">'
        +'<span style="padding-right:10px;vertical-align:sub">'
        +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        + '" class="rt-icon rt-icon2" style="background-image: url(&quot;'+search_icon+ '&quot;);"/></span>'
        +'<input class="input_menu_filter" size="8" style="outline: none;background:none;border: 1px solid lightgray;"/>'
        +'<span class="smallbutton ui-icon ui-icon-circlesmall-close" tabindex="-1" title="Clear entered" '
        +'style="position:relative; cursor: pointer; outline: none; box-shadow: none; border-color: transparent;"></span>'
        +'<span class="trm-btns" style="padding: 0 0 0 10px;cursor: pointer;"></span>'
        + '<div class="not-found" style="padding:10px;color:darkgreen;display:none;width:210px;">No terms match the filter '
        + '<a class="add-trm" href="#" style="padding: 0 0 0 10px;color:blue;display:inline-block;">Add term</a>'
        +'</div></div>';

        var topOptions = [{key:'select',title:filter_form},{key:'',title:'&lt;blank&gt;'}];

        var events = {};
        events['onOpenMenu'] = function(){
            __createTermTooltips(that.selObj);
            openSearchMenu(that, that.selObj, true);
        };

        events['onSelectMenu'] = function ( event ){

            var trm_ID = (event) ?$(event.target).val() :$(that.selObj).val();

            that._off($(that.selObj),'change');

            var ref_id = $(that.selObj).attr('ref-id');

            var $input = $('#'+ref_id);
            //var $inputdiv = $('#'+ref_id).parent();
            //var opt = $(that.selObj).find('option:selected');
            that.newvalues[$input.attr('id')] = trm_ID;
            $input.attr('data-value', trm_ID); //that's more reliable

            __recreateTrmLabel($input, trm_ID);
            /*
            $input.empty(); //clear 
            //add new value
            $('<span tabindex="0"class="ui-selectmenu-button ui-button ui-widget ui-selectmenu-button-closed ui-corner-top" style="padding: 0px; font-size: 1.1em; width: auto; min-width: 10em;">'
            +'<span class="ui-selectmenu-icon ui-icon ui-icon-triangle-1-s"></span><span class="ui-selectmenu-text" style="min-height: 17px;">'
            + window.hWin.HEURIST4.util.htmlEscape(trm_Label)
            +'</span></span>').appendTo($input);
            */
            that.onChange();

        };

        events['onCloseMenu'] = function (event){

            let $menu = that.selObj.hSelect('menuWidget');

            // Reset filter input
            $menu.find('.input_menu_filter').val('');
            $menu.find('li').css('display','list-item');
        };

        $inputdiv.addClass('selectmenu-parent');

        that.selObj = document.createElement("select");
        $(that.selObj).addClass('enum-selector-main')
        .css('max-width','300px')
        .appendTo($inputdiv);

        that.selObj = window.hWin.HEURIST4.ui.createTermSelect(that.selObj,
            {vocab_id:allTerms, //headerTermIDsList:headerTerms,
                defaultTermID:$input.val(), topOptions:topOptions, supressTermCode:true, 
                useHtmlSelect:false, eventHandlers:events, language_code: lang_code});

        $(that.selObj).hide(); //button will be hidden        
    }
    
    //
    // select term from drop down
    //
    var __show_select_dropdown = function(event_or_id){
        
        if(that.is_disabled) return;
        
        var $input, $inputdiv, ref_id; 
        
        if(typeof event_or_id == 'string'){ //id
            
            ref_id = event_or_id; 
            $input = that.element.find('#'+ref_id);
            $inputdiv = $input.parents('.input-div');
            
        }else 
        if(event_or_id && event_or_id.target){ //event
            
            var event = event_or_id;
        
            $inputdiv = $(event.target).parents('.input-div');
            $input = $inputdiv.find('select');
            ref_id = $input.attr('id');

            if(event) event.preventDefault();
        }
        
        var dty_ID = that.f('rst_DetailTypeID');
        
        //recreate dropdown if not inited
        if(!that.selObj || !that.selObj.hSelect('instance')){

            __recreateSelector();
                
        }else{
            that._off($(that.selObj), 'change');    
        }
            
        //Adjust position
        var _ref_id = $input.attr('id');
        var menu_location = $input;

        if($input.hSelect('instance') !== undefined){
            menu_location = $input.hSelect('widget');
        }
           
        //that.selObj.val('');
        that.selObj.attr('ref-id', _ref_id); //assign current input id for reference in onSelectMenu
        that.selObj.hSelect('open');
        that.selObj.hSelect('widget').hide();
        var prn = that.selObj.hSelect('menuWidget').parent('div.ui-selectmenu-menu');
        if(prn.length>0){
            prn.css({'position':'fixed'}); //to show above all 
        }
        that.selObj.hSelect('menuWidget')
            .position({my: "left top", at: "left bottom", of: menu_location});
        //that._on($(that.selObj),{change:f(){}});
            
        
    } //__show_select_dropdown
    
    that._off( $input, 'click');
    that._on( $input, { click: __show_select_dropdown } ); //main invocation of dropdown

    
    if($input.is('select')){
        $input.addClass('enum-selector').css({'min-width':'230px', width:'auto', 'padding-left': '15px'});
        
        __recreateTrmLabel($input, value);
        
        /*replace with div
        $input = $('<div>').uniqueId()
                .addClass('enum-selector')
                .appendTo( $inputdiv );
        */
    }
    
    return __show_select_dropdown;
}

//
// Opens popup dialog with ability to define translations for field values
//
function translationSupport(_input_or_values, is_text_area, callback){

    if(!$.isFunction($('body')['editTranslations'])){
        $.getScript( window.hWin.HAPI4.baseURL + 'hclient/widgets/editing/editTranslations.js', 
            function() {  //+'?t='+(new Date().getTime())
                if($.isFunction($('body')['editTranslations'])){
                    translationSupport( _input_or_values, is_text_area, callback );
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr('Widget editTranslations not loaded. Verify your configuration');
                }
        });
    }else{
        //open popup
        var that = _input_or_values;    
        var _dlg, values, fieldtype;
        
        if($.isArray(that)){
            values = that;
            _dlg = $('<div/>').hide().appendTo($('body'));
            fieldtype = is_text_area?'blocktext':'freetext';
        }else{ //editing_input
            _dlg = $('<div/>').hide().appendTo( that.element );                               
            values = that.getValues();
            fieldtype = that.detailtype
        }

        _dlg.editTranslations({
            values: values,
            fieldtype: fieldtype,
            onclose:function(res){
                if(res){
                    if($.isFunction(callback)){
                        callback.call(this, res);
                    }else{
                        that.setValue(res);    
                        that.isChanged(true);
                        that.onChange();
                    }
                }
                _dlg.remove();
        }});

    }


}


//
// obtains values from input and textarea elements with data-lang attribute
// and assigns them to json params with key+language suffix
// data-lang='def' means default languge - key will be without suffix
// 
// params - json array to be modified
// $container - container element
// keyname - key in params
// name - name of element
//
function translationFromUI(params, $container, keyname, name, is_text_area){
    
    //clear previous values, except default
    $(Object.keys(params)).each(function(i, key){

        var key2 = key;        
        if(key.length>5 && key.indexOf(':')==key.length-4){
            key2 = key.substring(0, key.length-4);
            if(key2 == keyname){
                delete params[key];
            }
        }
    });
    
    //find all elements with given name
    var ele_type = is_text_area?'textarea':'input';
    
    $container.find(ele_type+'[name="'+name+'"]').each(function(i,item){
        item = $(item);
        var lang = item.attr('data-lang');
        if(lang=='def') lang = ''
        else lang = ':'+lang;
        
        var value = item.val().trim();
        if(!window.hWin.HEURIST4.util.isempty(value) || lang===''){
            params[keyname+lang] = value;    
        }
    });
}

//
//  Assign values from params to UI and initialize "translation" button
// params= [{keyname:value},...]
//
function translationToUI(params, $container, keyname, name, is_text_area){
    
    var def_ele = null;
    
    var ele_type = is_text_area?'textarea':'input';
    
    //find element assign data-lang for default, remove others
    //1. Removes all except default (first one)
    $container.find(ele_type+'[name="'+name+'"]').each(function(i,item){
        var lang  = $(item).attr('data-lang');
        if(lang=='def' || !lang){
            def_ele = $(item);
        }else{
            $(item).remove(); //remove non-default
        }
    });
    
    if(!def_ele) return;
    
    if(!params) params = {}; 
    if(!params[keyname]){
      params[keyname] = def_ele.val();  
    } 
    
    var sTitle = '';
    
    //init input element for default value and button
    def_ele.attr('data-lang','def').val(params[keyname]);
    
    //2. Add translation button    
    if($container.find('span[name="'+name+'"]').length==0){

        //translation button    
        var btn_add = $( "<span>")
            .attr('data-lang','def')
            .attr('name',name)
            .addClass('smallbutton editint-inout-repeat-button ui-icon ui-icon-translate')
            .insertAfter( def_ele )
        .attr('tabindex', '-1')
        .attr('title', 'Define translation' )
        .css({display:'inline-block', 
        'font-size': '1em', cursor:'pointer', 
            'min-width':'22px',
            outline: 'none','outline-style':'none', 'box-shadow':'none'
        });
        
        if(is_text_area){
            btn_add.css({'vertical-align':'top'});    
        }
        
        btn_add.on({click: function(e){//--------------------------
            
            var values = [];
            //$(e.target).attr('data-lang')
            
            //gather the list of values from input elements
            $container.find(ele_type+'[name="'+name+'"]').each(function(i,item){
                var lang  = $(item).attr('data-lang');
                if(lang=='def' || !lang){
                    values.push($(item).val())
                }else{
                    values.push(lang+':'+$(item).val());
                }
            });
            
            //open dialog
            translationSupport( values, is_text_area, function(newvalues){
                
                var res2 = {};
                for(var i=0; i<newvalues.length; i++){
                    var keyname2=keyname, value = newvalues[i];
                    
                    if(!window.hWin.HEURIST4.util.isempty(value) && value.substr(3,1)==':'){
                        keyname2 = keyname2+':'+value.substr(0,3);
                        value = value.substr(4).trim();
                    }else{
                        value = value.trim();
                    }
                    if(!window.hWin.HEURIST4.util.isempty(value)){
                        res2[keyname2] = value;
                    }
                }
                if(!res2[keyname]){
                    res2[keyname] = '';  
                } 

                translationToUI(res2, $container, keyname, name, is_text_area);
            });
            
        }});
    }//end add translation button
    
    
    //3. add new hidden lang elements
    $(Object.keys(params)).each(function(i, key){
        if(key==keyname){
            
        }else if(keyname==key.substring(0,key.length-4)){ // key.indexOf(keyname+':')===0){
            var lang = key.substring(key.length-3);
            
            var ele = $('<'+ele_type+'>')
                .attr('name',name).attr('data-lang',lang)
                
                .val(params[key]).insertAfter(def_ele);
                
            if(is_text_area){
                ele.css('display','none');
            }else{
                //ele.attr('type','hidden');
            }
                
            sTitle += (lang+':'+params[key]+'\n');
        }
    });    
    
    def_ele.attr('title',sTitle);

}

//
// Select arbitrary Heurist record 
//
function selectRecord(options, callback)
{
        var popup_options = {
            select_mode: 'select_single', //select_multi
            select_return_mode: 'recordset', //or ids
            edit_mode: 'popup',//'none'
            selectOnSave: true, //it means that select popup will be closed after add/edit is completed
            title: window.hWin.HR('Select record'),
            rectype_set: null,
            parententity: 0,
            default_palette_class: 'ui-heurist-populate',
            onselect:function(event, data){
                //if( window.hWin.HEURIST4.util.isArrayNotEmpty(data.selection) ){
                //    callback(data.selection[0]);
                //}
                
                if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                    var recordset = data.selection;
                    //var record = recordset.getFirstRecord();
                    //var record_id = recordset.fld(record,'rec_ID');
                    callback(data.selection);
                }
            }
        };//popup_options
        
        var usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_records', 
            {width: null,  //null triggers default width within particular widget
                height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });

        popup_options.width = Math.max(usrPreferences.width,710);
        popup_options.height = usrPreferences.height;

        if(options){
            popup_options = $.extend(popup_options,options);
        }
        
        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
}
