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
//            3  - symbology editor from recrd edit for map layer
// 
function editSymbology(current_value, mode_edit, callback){

    var edit_dialog = null;
    
    var popup_dlg = $('#heurist-dialog-editSymbology');

    if(popup_dlg.length>0){
        popup_dlg.empty();
    }else{
        popup_dlg = $('<div id="heurist-dialog-editSymbology">')
            .appendTo( $(window.hWin.document).find('body') );
    }

    var editForm = $('<div class="ent_content_full editForm" style="top:0">')
    .appendTo($('<div class="ent_wrapper">').appendTo(popup_dlg));

    var _editing_symbology = new hEditing({container:editForm, 
        onchange:
        function(){
            var isChanged = _editing_symbology.isModified();
            var mode = isChanged?'visible':'hidden';
            edit_dialog.parent().find('#btnRecSave').css('visibility', mode);

            if(isChanged){

                var ele = _editing_symbology.getFieldByName('iconType');
                var res = ele.editing_input('getValues'); 
                var ele_icon_url = _editing_symbology.getFieldByName('iconUrl').hide();
                var ele_icon_font = _editing_symbology.getFieldByName('iconFont').hide();
                if(res[0]=='url'){
                    ele_icon_url.show();
                }else if(res[0]=='iconfont'){
                    ele_icon_font.show();
                }

            }

        },
        oninit: function(){
            
            _editing_symbology = this;
            
    if(current_value){
        current_value.fill = window.hWin.HEURIST4.util.istrue(current_value.fill)?'1':'0';
        current_value.stroke = window.hWin.HEURIST4.util.istrue(current_value.stroke)?'1':'0';
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
        
    }else{
        
        var ptr_fields = [];
        if(mode_edit===3){
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
        
        
        editFields = [                
        {"dtID": "sym_Name",
            "dtFields":{
                "dty_Type":"freetext",
                //"rst_RequirementType":"required",                        
                "rst_DisplayName":"Name:",
                "rst_Display": (mode_edit===1)?"visible":"hidden"
        }},

        {"dtID": "geofield",
            "dtFields":{
                "dty_Type":"enum",
                //"rst_RequirementType":"required",                        
                "rst_DisplayName":"Field to be mapped:",
                "rst_Display": (mode_edit===3)?"visible":"hidden",
                "rst_FieldConfig":ptr_fields, //{"entity":"defDetailTypes","csv":false},
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
                "rst_FieldConfig":[
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
                "rst_FieldConfig":[
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
    }
    
    _editing_symbology.initEditForm( editFields, recdata );

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
                if(res['iconType']=='circle'){
                    res['radius'] = (res['iconSize']>0?res['iconSize']:8);
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
        height: (mode_edit==2)?300:700,
        width:  740,
        modal:  true,
        title: window.hWin.HR('Define Symbology'),
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
                    {default_palette_class:'ui-heurist-design'});
                return false;   
            }
            return true;
        },

        buttons: edit_buttons
    });                

    edit_dialog.parent().addClass('ui-heurist-design');
    
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
// disableClick => disable click on search option, to avoid selecting it as the value
//
function openSearchMenu(that, $select, disableClick=true){

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
                $.each($mnu.find('.ui-menu-item-wrapper'), function(i,item){

                    let title = $(item).text().toLowerCase();
                    if($select.attr('rectype-select') == 1 && window.hWin.HEURIST4.browseRecordCache.hasOwnProperty(key)){
                        title = window.hWin.HEURIST4.browseRecordCache[key][i]['rec_Title'].toLowerCase();
                        title = title.replace(/[\r\n]+/g, ' ');
                    }

                    if(title.indexOf(val)>=0){
                        $(item).parent().css('display','list-item');
                    }else{
                        $(item).parent().css('display','none');
                    }
                });
                $mnu.find('div.not-found').css('display',
                    $mnu.find('.ui-menu-item-wrapper:visible').length==0?'block':'none');
                _timeout = setTimeout(function(){$mnu.find('.ui-menu-item-wrapper').css('cursor','default');_timeout=0;},500);
            }                                    
            
        }});

		if(disableClick){			
			//stop click for menu filter option
			that._on($menu.find('li.ui-menu-item:first'), {
				click: function(event){
                    if ($(event.target).parents('.show-select-dialog').length==0){
					    window.hWin.HEURIST4.util.stopEvent(event);
					    return false;
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
//
function browseRecords(_editing_input, $input){
    
    var that = _editing_input;
    
    var $inputdiv = $input.parent(); //div.input-div

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
                    
                    onselect:function(event, data){
                             if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
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
                                
                                //window.hWin.HEURIST4.ui.setValueAndWidth($input, rec_Title);
                                
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
    };

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
                            qobj = window.hWin.HEURIST4.util.mergeHeuristQuery(qobj, pointer_filter);
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

        $input.empty();
        if(window.hWin.HEURIST4.util.isNumber(trm_ID) && trm_ID>0){
            
            var trm_Label = $Db.trm(trm_ID, 'trm_Label');
            var trm_info = $Db.trm(trm_ID);

            if(trm_info && trm_info.trm_ParentTermID != 0){
                
                while(1){

                    trm_info = $Db.trm(trm_info.trm_ParentTermID);

                    if(trm_info.trm_ParentTermID == 0){
                        break;
                    }else{
                        trm_Label = trm_info.trm_Label + '.' +  trm_Label;
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

        if(window.hWin.HEURIST4.util.isempty(allTerms) &&
            that.options.dtID==window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'])
        { //specific behaviour - show all
            allTerms = 'relation'; //show all possible relations
        }else if(typeof allTerms == 'string' && allTerms.indexOf('-')>0){ //vocabulary concept code
            allTerms = $Db.getLocalID('trm', allTerms);
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

        }


        $inputdiv.addClass('selectmenu-parent');

        that.selObj = document.createElement("select");
        $(that.selObj).addClass('enum-selector-main')
        .css('max-width','300px')
        .appendTo($inputdiv);

        that.selObj = window.hWin.HEURIST4.ui.createTermSelect(that.selObj,
            {vocab_id:allTerms, //headerTermIDsList:headerTerms,
                defaultTermID:$input.val(), topOptions:topOptions, supressTermCode:true, 
                useHtmlSelect:false, eventHandlers:events});                

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
        if(!that.selObj || !$(that.selObj).hSelect('instance')){

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
                    }
                }
                _dlg.remove();
        }});

    }


}


//
// obtains values from input and textarea elements with data-lang attribute
// and assigns them to json params with key+language suffix
// data-lang='xx' means default languge - key will be without suffix
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
        if(key.indexOf(':')==key.length-3){
            key2 = key.substring(0, key.length-3);
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
        if(lang=='xx') lang = ''
        else lang = ':'+lang;
        
        var value = item.val().trim();
        if(!window.hWin.HEURIST4.util.isempty(value) || lang===''){
            params[keyname+lang] = value;    
        }
    });
}

//
//  Assign values from params to UI and initialize "translation" button
//
function translationToUI(params, $container, keyname, name, is_text_area){
    
    var def_ele = null;
    
    var ele_type = is_text_area?'textarea':'input';
    
    //find element assign data-lang for default, remove others
    //remove all except default
    $container.find(ele_type+'[name="'+name+'"]').each(function(i,item){
        var lang  = $(item).attr('data-lang');
        if(lang=='xx' || !lang){
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
    def_ele.attr('data-lang','xx').val(params[keyname]);
    
    if($container.find('span[name="'+name+'"]').length==0){//button
    
        var btn_add = $( "<span>")
            .attr('data-lang','xx')
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
        
        btn_add.on({click: function(e){
            
            var values = [];
            //$(e.target).attr('data-lang')
            $container.find(ele_type+'[name="'+name+'"]').each(function(i,item){
                var lang  = $(item).attr('data-lang');
                if(lang=='xx' || !lang){
                    values.push($(item).val())
                }else{
                    values.push(lang+':'+$(item).val());
                }
            });
            
            translationSupport( values, is_text_area, function(res){
                
                var res2 = {};
                for(var i=0; i<res.length; i++){
                    var keyname2=keyname, value = res[i];
                    
                    if(!window.hWin.HEURIST4.util.isempty(value) && value.substr(2,1)==':'){
                        keyname2 = keyname2+':'+value.substr(0,2);
                        value = value.substr(3).trim();
                    }else{
                        value = value.trim();
                    }
                    if(!window.hWin.HEURIST4.util.isempty(value)){
                        res2[keyname2] = value;
                    }
                }
                if(!res2[keyname]) res2[keyname] = '';
                
                translationToUI(res2, $container, keyname, name, is_text_area);
            });
            
        }});
    }
    
    
    //add new hidden lang elements
    $(Object.keys(params)).each(function(i, key){
        if(key==keyname){
            
        }else if(keyname==key.substring(0,key.length-3)){ // key.indexOf(keyname+':')===0){
            var lang = key.substring(key.length-2);
            
            var ele = $('<'+ele_type+'>')
                .attr('name',name).attr('data-lang',lang)
                
                .val(params[key]).insertAfter(def_ele);
                
            if(is_text_area){
                ele.css('display','none');
            }else{
                ele.attr('type','hidden');
            }
                
            sTitle += (lang+':'+params[key]+'\n');
        }
    });    
    
    def_ele.attr('title',sTitle);

}