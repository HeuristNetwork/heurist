/*
* editng_exts.js - additional functions for editing_input
*  1) editSymbology - edit map symbol properties 
*  2) calculateImageExtentFromWorldFile - calculate image extents from worldfile
*  3) browseRecords - browse records for resource fields 
*  4) translationSupport 
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

    }});
    
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
        editFields = [                
        {"dtID": "sym_Name",
            "dtFields":{
                "dty_Type":"freetext",
                //"rst_RequirementType":"required",                        
                "rst_DisplayName":"Name:",
                "rst_Display": (mode_edit===true)?"visible":"hidden"
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
                {title:'Calculate image extent',yes:'Proceed',no:'Cancel'});

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
    var $inpt = $menu.find('input.input_menu_filter');

    if(!$inpt.attr('data-inited')){

        //reset filter                                
        that._on($menu.find('span.smallbutton'), {click:
        function(event){
            window.hWin.HEURIST4.util.stopEvent(event); 
            var $mnu = $select.hSelect('menuWidget');
            $mnu.find('input.input_menu_filter').val('');
            $mnu.find('li').css('display','list-item');
            $mnu.find('div.not-found').hide();
        }});

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
                $.each($mnu.find('.ui-menu-item-wrapper'),
                    function(i,item){
                        if($(item).text().toLowerCase().indexOf(val)>=0){
                            $(item).parent().css('display','list-item');
                        }else{
                            $(item).parent().css('display','none');
                        }
                    });    
                $mnu.find('div.not-found').css('display',
                    $mnu.find('.ui-menu-item-wrapper:visible').length==0?'block':'none');
            }                                    
            
        }});

		if(disableClick){			
			//stop click for menu filter option
			that._on($menu.find('li.ui-menu-item:first'), {
				click: function(event){
					window.hWin.HEURIST4.util.stopEvent(event);
					return false;
				}
			});
		}

        $inpt.attr('data-inited',1);

        $inpt.parents('.ui-menu-item-wrapper').removeClass('ui-menu-item-wrapper ui-state-active');
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
        pointerMode = 'addorbrowse';
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
        var __show_select_dropdown = function(event){
          
            if(that.is_disabled) return;
        
            if(event!==false){
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
                            
                            if(response.data.count>1000){
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
                                           __show_select_dropdown();

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
                
                if(!that.selObj || !$(that.selObj).hSelect('instance')){

                    if(that.selObj){
                        $(that.selObj).remove();
                    }
                    
                    that.selObj = window.hWin.HEURIST4.ui.createSelector(null);//, [{key:'select', title:'Search/Add'}]);
                    
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
                    +'<span style="padding:0px 4px 0 20px;vertical-align:sub">'
                    +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                    + '" class="rt-icon rt-icon2" style="background-image: url(&quot;'+search_icon+ '&quot;);"/></span>'
                    + window.hWin.HR('Search') + (s_action=='select'?'':('/' +  window.hWin.HR('Add'))) 
                    + '<div class="not-found" style="padding:10px;color:darkgreen;display:none;">'
                    +window.hWin.HR('No records match the filter')+'</div></div>');
                    
                    //$(opt).attr('icon-url', search_icon);
                    
                    $.each(window.hWin.HEURIST4.browseRecordCache[key], function(idx, item){
                        
                        var opt = window.hWin.HEURIST4.ui.addoption(that.selObj, item['rec_ID'], item['rec_Title'].substr(0,64)); 
                        
                        var icon = window.hWin.HAPI4.iconBaseURL + item['rec_RecTypeID'];
                        $(opt).attr('icon-url', icon);
                        $(opt).attr('data-rty', item['rec_RecTypeID']);
                    });
                    
//console.log('init hselect '+key);                    
                    function __onSelectMenu( event ){
                        var targetID = $(event.target).val();
                        if(!targetID) return;

                        that._off($(that.selObj),'change');
                        
                        if(targetID=='select'){
                           __show_select_dialog(); 
                        }else{
                            
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
                    that.selObj = window.hWin.HEURIST4.ui.initHSelect(that.selObj, false, null,
                        function(){ //onOpenMenu
                            var ele = that.selObj.hSelect('menuWidget');
                            ele.css('max-width', '500px');
                            ele.find('div.ui-menu-item-wrapper').addClass('truncate');
                            ele.find('.rt-icon').css({width:'12px',height:'12px','margin-right':'10px'});
                            ele.find('.rt-icon2').css({'margin-right':'0px'});

                            openSearchMenu(that, that.selObj, false);
                        },
                        __onSelectMenu
                    );
                    
                }else{
                    that._off($(that.selObj), 'change');    
                }
                
                var $inpt_ele = $inputdiv.find('.sel_link2');
                if($inpt_ele.is(':hidden') && $inputdiv.find('.link-div').length == 1){
                    $inpt_ele = $inputdiv.find('.link-div');
                }

                that.selObj.val('');
                that.selObj.hSelect('open');
                that.selObj.hSelect('widget').hide();
                that.selObj.hSelect('menuWidget')
                        .position({my: "left top", at: "left bottom", of: $inpt_ele});
                //that._on($(that.selObj),{change:f(){}});
                
            }
        }
        
    
        that._on( $inputdiv.find('.sel_link2'), { click: __show_select_dropdown } );

        return __show_select_dropdown;
    }else{
        that._on( $inputdiv.find('.sel_link2'), { click: __show_select_dialog } );
        return __show_select_dialog;
    }
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