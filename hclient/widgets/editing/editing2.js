
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

function hEditing(_options) {
     var _className = "Editing",
         _version   = "0.4";

     var $container = null,
         recdata = null,     //hRecordSet with data to be edited
         editing_inputs = [],
         recstructure,
         onChangeCallBack=null,
         entityConfig = null,
         options = {},
         _editStructureMode = false;

    /**
    * Initialization
    * options:
    *   container - element Id or jqyuery element
    *   entity 
    *   recstructure - configuration of fields
    *   recdata - initial data
    *   onchange
    */
    function _init(_options) {
        
        
        if (typeof _options.container==="string") {
            $container = $("#"+_options.container);
        }else{
            $container = $(_options.container);
        }
        if($container==null || $container.length==0){
            $container = null;
            alert('Container element for editing not found');
        }
        
        if(_options.entity){
            entityConfig = _options.entity;
        }else{
            entityConfig = null;
        }
        
        onChangeCallBack = _options.onchange;
        
        if(!_options.className) {
            if($container.parents('.editor').length==0){
                //2020-12-29 _options.className = 'ui-heurist-bg-light';
            }else {
                _options.className = '';
            }
        }
        
        options = _options;
        
        _initEditForm(_options.recstructure, _options.recdata);
    }

    //
    // reload existing form        NOT USED
    //
    function _reload(_recdata) {
        
        if(!$container) return;

        recdata = _recdata;

        if(!recdata) return; //nothing to edit

        //create form, fieldset and input elements according to record type/entity structure

        var record = recdata.getFirstRecord();
        /*if(record){
            var recID = recdata.fld(record, 'rec_ID');
            var rectypeID = recdata.fld(record, 'rec_RecTypeID');
            //load recordtype/entity structure
            var rectypes = recdata.getStructures();
        }*/
        if(record!=null){
            //fill form with values
            var idx, ele;
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                var val = recdata.values(record, ele.editing_input('option', 'dtID'));
                if(!window.hWin.HEURIST4.util.isArray(val)) val = [val];
                ele.editing_input('setValue', val );
            }
            
        }
    }
    
    function _setDisabled(mode){
            var idx, ele;
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                ele.editing_input('setDisabled', mode);
            }
    }
    
    function _initEditForm(_recstructure, _recdata, _is_insert){
        
        that.wasModified = 0;
        
        $container.hide();
        $container.empty(); //clear previous edit elements
        editing_inputs = [];
        recdata = _recdata;
        record = null;
        
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(_recstructure) && _recdata==null){
            //$('<div class="center-message">Select an entity in the list to edit</div>').appendTo($container);
            $container.show();
            return;     
        } 
        
        if(_recstructure) recstructure = _recstructure;
                
        var recID = '';
        if(window.hWin.HEURIST4.util.isRecordSet(recdata)){
            //for edit mode
                record = recdata.getFirstRecord();
                //get record ID
                var idx;
                for (idx=0; idx<recstructure.length; idx++){
                   if(recstructure[idx]['keyField']){
                        recID = recdata.fld(record, recstructure[idx]['dtID']);
                        break;
                   }
                }
        }
        
        
        //rec structure is array in following format
        /*
            only type 'header' can have children
               [
                    {
                    groupHeader: '',
                    groupType: '',  accordeon, tab, group 
                    groupStyle: {}
                    children:[
                        dtID, dtID, dtID, 
                        {groupHeader: , children:},
                    ]
                    },.....
                ],
                
                [
                    {
                    dtID: 'dty_Name',
                    dtFields:{
                        dty_Type:'freetext',
                        rst_DisplayName:'Default field type name:',
                        rst_DisplayHelpText: '', 
                        rst_DisplayExtendedDescription:'',
                        rst_DisplayWidth:60,
                        rst_DefaultValue:'',
                        rst_RequirementType:'required',
                        rst_MaxValues:1
                    }
                    },....]
        */
        
        function __createGroup(fields, groupContainer, fieldContainer){
            var idx, tab_index;
                
            var currGroupType = null, currGroupHeaderClass = null; //current accodion or tab control
            var groupTabHeader, groupEle;
            var hasVisibleFields = false;
            
            //var groupEle,      //current accordion or tab control
            //    fieldContainer, groupTabHeader;
                
            for (idx=0; idx<fields.length; idx++){
                
                if( $.isPlainObject(fields[idx]) && fields[idx].groupType ){ //this is group
                //  window.hWin.HEURIST4.util.isArrayNotEmpty(fields[idx].children)
                    
                    if(fields[idx].groupHidden){ //this group is hidden all fields goes to previous group

                        __createGroup(fields[idx].children, groupContainer, fieldContainer);
                        continue;                        
                    }
                    
                    
                    if(fields[idx].groupType != currGroupType){ //create new group container and init previous
                        //init previous one 
                        if(groupEle!=null){
                            if(currGroupType == 'accordion' || currGroupType == 'expanded'){
                                groupEle.accordion({heightStyle: "content", 
                                                    active:(currGroupType == 'expanded')?0:false, 
                                                    collapsible: true});
                                if(currGroupHeaderClass){
                                    groupEle.find('.ui-accordion-header').addClass(currGroupHeaderClass);
                                }
                            }else if(currGroupType == 'tabs'){
                                groupEle.tabs().addClass('edit-form-tabs');
                            }
                        }
                
                        currGroupHeaderClass= fields[idx].groupHeaderClass;
                        currGroupType = (fields[idx].groupType == 'tabs_new')
                                                  ?'tabs':fields[idx].groupType;
                        //create new accordion or tabcontrol
                        if(currGroupType == 'accordion' || currGroupType == 'expanded'){
                            groupEle = $('<div>').appendTo(groupContainer);
                        }else if(currGroupType == 'tabs'){
                            //header(tabs)
                            groupEle = $('<div>').appendTo(groupContainer);
                            groupTabHeader = $('<ul>').appendTo(groupEle);
                            
                        }else{
                            groupEle = null;
                        }
                        if(groupEle && fields[idx].dtID>0){
                                groupEle.attr('data-group-dtid', fields[idx].dtID);
                        }
                        
                        tab_index = 0;
                    }
                    
                    var headerText = fields[idx]['groupHeader'];
                    var headerHelpText = fields[idx]['groupHelpText'];
                    var is_header_visible = fields[idx]['groupTitleVisible'];
                    
                    var newFieldContainer = $('<fieldset>').uniqueId();
                    if(!$.isEmptyObject(fields[idx]['groupStyle'])){
                        newFieldContainer.css(fields[idx]['groupStyle']);    
                    }
                    if(parseInt(fields[idx]['dtID'])>0){ //for Records only
                    
                        newFieldContainer.attr('data-dtid', fields[idx]['dtID']);

                        if(!(currGroupType == 'tabs' || currGroupType == 'accordion' || currGroupType == 'expanded')){
                            //div for gearwheel
                            $('<div>').css({'padding-left':'7px','height':'12px','display':'inline-block'}) 
                                .attr('data-dtid', fields[idx]['dtID'])
                                .appendTo(groupContainer);
                        }else{
                            newFieldContainer.attr('data-tabindex', tab_index);
                            tab_index++;
                        }
                    }

                    //add header and field container
                    if(currGroupType == 'accordion' || currGroupType == 'expanded'){
                        $('<h3>').html('<span class="separator2">'+headerText+'</span>').appendTo(groupEle);
                        newFieldContainer.appendTo($('<div>').appendTo(groupEle));

                        newFieldContainer.addClass(options.className);

                    }else if(currGroupType == 'tabs'){
                        // class="separator2"
                        $('<li>').addClass('edit-form-tab').html('<a href="#'+newFieldContainer.attr('id')+'"><span style="font-weight:bold">'+headerText+'</span></a>')
                        .appendTo(groupTabHeader);

                        $(newFieldContainer).appendTo(groupEle);

                        newFieldContainer.addClass(options.className);

                    }else{

                        var ele = $('<h4>').text(headerText).addClass('separator').appendTo(groupContainer);

                        if(!is_header_visible){
                            ele.addClass('separator-hidden').hide();
                        }

                        newFieldContainer.appendTo(groupContainer);
                    }

                    if(true || headerHelpText!=''){
                         var div_prompt = $('<div>').text(headerHelpText)
                            .addClass('heurist-helper1')
                            .appendTo(newFieldContainer);
                         if(currGroupType == 'tabs' || currGroupType == 'accordion'){
                            div_prompt.addClass('tab-separator-helper')
                                .attr('separator-dtid',fields[idx]['dtID']).css({padding:'5px 0 0 5px',display:'inline-block'});
                         }else{
                            div_prompt.addClass('separator-helper').css({'padding-left':'14px'});
                         }
                         if(!is_header_visible){
                            div_prompt.addClass('separator-hidden').hide();
                        }
                    }
                    if(currGroupType == 'tabs'){ //some space on the top
                        $(newFieldContainer).append('<div style="min-height:10px">&nbsp;</div>');
                    }
                        
                    __createGroup(fields[idx].children, groupContainer, newFieldContainer);
                    
                    //reset fieldContainer
                    fieldContainer = null;
                    
                }//has children
                else{ //this is entry field 
                
                    if(fieldContainer==null){ 
                        //we do not create it before loop to avoid create empty fieldset 
                        // in case first element is group
                        fieldContainer = $('<fieldset>').uniqueId().appendTo(groupContainer);
                    }
                    
                    if(fields[idx]['dty_Type']=="separator"){
                        $('<h4>').text(fields[idx]['rst_DisplayName']).addClass('separator').appendTo(fieldContainer);
                        var div_prompt = $('<div>')
                            .text(top.HEURIST4.ui.getRidGarbageHelp(fields[idx]['rst_DisplayHelpText']))
                            .addClass('heurist-helper1').appendTo(fieldContainer);
                        //see applyCompetencyLevel
                        //if(window.hWin.HAPI4.get_prefs('help_on')!=1){div_prompt.hide();}
                    }else  
                    //if(fields[idx]['dtFields']['rst_Display']!="hidden") 
                    {
                        
                        //assign values from record
                        if(record!=null){
                            
                            var val;
                            if(fields[idx]['dty_Type']=="geo"){
                                val = recdata.getFieldGeoValue(record, fields[idx]['dtID']);
                            }else{
                                val = recdata.values(record, fields[idx]['dtID']);
                            }

//console.log( fields[idx]['dtID'] + '=>');
//console.log(val);                            
                            
                            if(!window.hWin.HEURIST4.util.isnull(val)){
                                if(!window.hWin.HEURIST4.util.isArray(val)) val = [val];
                                fields[idx].values = val;
                            }else{
                                fields[idx].values = null; //[''];
                            }  
                            
                        }else{
                        //new record - reset all values    
                            fields[idx].values = null;    
                        }
                        
                        fields[idx].recID = recID;
                        fields[idx].recordset = recdata;
                        fields[idx].editing = that;
                        fields[idx].change = _onChange;
                        fields[idx].is_insert_mode = _is_insert;
                        
                        var inpt = $('<div>').css('display','block !important')
                                .appendTo(fieldContainer).editing_input(fields[idx]);     
                        //mark each field with dty_ID         
                        if(parseInt(fields[idx]['dtID'])>0){ //for Records only
                            inpt.attr('data-dtid', fields[idx]['dtID']);
                        }
                                
                        editing_inputs.push(inpt);  
                        
                        hasVisibleFields = true;
                    }
                }//end field addition
                
            }//for
            
            //init last one
            if(groupEle!=null){
                if(currGroupType == 'accordion' || currGroupType == 'expanded'){
                    groupEle.accordion({heightStyle: "content", 
                                        active:(currGroupType == 'expanded')?0:false,
                                        collapsible: true });
                    if(currGroupHeaderClass){
                        groupEle.find('.ui-accordion-header').addClass(currGroupHeaderClass);
                    }
                }else if(currGroupType == 'tabs'){
                    groupEle.tabs({active: 0}).addClass('edit-form-tabs');;
                }
            }
            
            if(!hasVisibleFields){
                $('<div>There are no fields visible under this heading/tab. Please define new fields or move fields into this section.</div>')
                    .addClass('heurist-helper3').appendTo(fieldContainer);
            }
            
        }//end of function

        $container.addClass(options.className);


        if(entityConfig && entityConfig.entityDescription){
            //add description at the beginning of form
            $('<div>').css({padding: '4px'}).addClass('heurist-helper2 entity-description')
                .html(entityConfig.entityDescription).appendTo($container);
        }
        
        __createGroup(recstructure, $container, null);
        
       
        $container.fadeIn(250);
        
        _setFocus();
        adjustHelpWidth();
        
        var $div_hints = $('<div>').css({'padding-top':'5px', 'padding-left': '180px'}).appendTo($container); //float: 'left'
        if($container.find('.forbidden').length>0 && window.hWin.HAPI4.is_admin()){
            $('<div>').css({padding: '4px'})
                .addClass('hidden_field_warning')
                .html('There are hidden fields in this form. <span class="btn-modify_structure"'
                +'  style="cursor:pointer;display:inline-block;color:#7D9AAA;">'
                +'Modify structure</span> to enable them.').appendTo($div_hints);
        }
        if($container.find('div.optional').length>0 && recdata && recdata.entityName=='Records'){
            $('<div>').css({padding: '4px'}).addClass('optional_hint')
                .html('Fields missing? Turn on <u>optional fields</u> (checkbox at the top of page)').appendTo($div_hints);
        }
        
    }
    
    //
    //
    //
    function _setFocus(){
        if(editing_inputs.length>0){
            var idx, ele;
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                if(ele.editing_input('focus')){
                    break;    
                }
            }
        }
    }
    
    //
    //to reduce width of edit form set heurist-helper1 to max width of input element
    //
    function adjustHelpWidth(){
        
        var maxW = 300;
        if(editing_inputs.length>0){
            var idx, ele; 
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                maxW = Math.max(maxW, ele.editing_input('getInputWidth'));
            }
        }
        
        //for all except image selector help
        $container.find('.input-cell').each(function(i,item){
               if(!$(item).find('.image_input')) {
                   $(item).find('.heurist-helper1').width(maxW);
               }
        });
        
        //$container.find('.input-cell > .heurist-helper1').width(maxW);
    }
    

    //
    //
    //
    function _save(){
        alert('save');
        return true;
    }

    //
    // returns array with at least one empty value
    //
    function _getValue(dtID){
        var idx, ele, values = [];
        for (idx in editing_inputs) {
            ele = $(editing_inputs[idx]);
            if(ele.editing_input('option', 'dtID')==dtID){
                var vals = ele.editing_input('getValues');
                if(vals && vals.length>0){
                    return ele.editing_input('getValues');
                }else{
                    return null;
                }
            }
        }
        return null;
    }
    
    //
    // returns values as object {'xyz_Field':'value','xyz_Field2':['val1','val2','val3]}
    //    
    function _getValues(needArrays){
        
        var idx, ele, details = {};
        for (idx in editing_inputs) {
            ele = $(editing_inputs[idx]);
            var vals = ele.editing_input('getValues');
            //var vals = ele.list("getValues"); 
            //var vals = ele.data("editing_input").getValues();
            if(vals && vals.length>0){
                
                var a_val;
                if(needArrays || vals.length>1 || $.isPlainObject(vals[0])){
                    a_val = vals;
                }else{
                    a_val = vals[0];   
                }
                details[ ele.editing_input('option', 'dtID') ] = a_val;
            }
        }
        return details;
    }
    
    //
    // get values from editing form and assign to underlaying recordset
    //
    function _assignValuesIntoRecord(){
    
        if(recdata!=null){ //for edit mode
            record = recdata.getFirstRecord();

            var idx, ele, details = {};
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                var vals = ele.editing_input('getValues');
                if(vals && vals.length>0){
                    recdata.setFld(record, ele.editing_input('option', 'dtID'), vals);
                }
            }
        }
    }

    
    function _isModified(){
        
        if(that.wasModified==2){ //modfied flag is reset (after save)
            return false;
        }else if(that.wasModified==1){
            return true;
        }else{
            var idx;
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                
                if(ele.editing_input('instance') && ele.editing_input('isChanged')) {
                    return true;   
                }
            }
            return false;
        }
    }
    
    function _onChange(){
        if($.isFunction(onChangeCallBack)){
            onChangeCallBack.call( this );    
        }
    }
    
    function _validate(){
        
        var idx, res = true;
        for (idx in editing_inputs) {
            res = $(editing_inputs[idx]).editing_input('validate') && res;
        }
        
        return res;
    }
    
    //
    // returns input element for given field
    // 
    function _getFieldByName(fieldName){
        var idx, ele;
        for (idx in editing_inputs) {
            ele = $(editing_inputs[idx]);
            if(ele.editing_input('instance') && ele.editing_input('option', 'dtID')  == fieldName){
                return ele;
            }
        }
        return null;
    }

    //
    // returns array of input elements for field value
    // 
    function _getFieldByValue(fieldName, value){
        var idx, ele, ress = [], val;
        if(value==='[not empty]'){
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                val = ele.editing_input('f', fieldName)
                if(!window.hWin.HEURIST4.util.isempty(val)){
                    ress.push(ele);
                }
            }
        }else{
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                if(ele.editing_input('f', fieldName)  == value){
                    ress.push(ele);
                }
            }
        }
        
        return ress;
    }

    //
    // returns array of input elements for field value
    // 
    function _getFieldByClass(className){
        var idx, ele, ress = [];
        for (idx in editing_inputs) {
            ele = $(editing_inputs[idx]);
            if(ele.hasClass(className)){
                ress.push(ele);
            }
        }
        return ress;
    }

        
    //
    // returns array of input elements for given field
    // fieldName is rec_XXX or dty_ID
    // 
    function _getInputs(fieldName){
        var ele = _getFieldByName(fieldName);
        if(ele && ele.length>0){
            return ele.editing_input('getInputs');
        }
    }


    //public members
    var that = {
        
        wasModified: 0, //0 not modified (on init), 1 was modified, 2 - finalized(not modified)

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        //assign values only based on old structure
        reload: function(_recdata){
            _reload(_recdata);
        },

        validate: function(){
            return _validate();
        },

        save: function(){
            _save();
        },
        
        //
        // create edit form and fill values from given recordset
        //
        initEditForm: function(_recstructure, _recdata, _is_insert){
            _initEditForm(_recstructure, _recdata, _is_insert);
        },
        
        // returns array with at least one empty value
        //
        getValue:function(dtID){
            return _getValue(dtID);
        },
        
        //
        // returns all values of editing form as json
        //
        getValues:function(needArrays){
            return _getValues(needArrays);
        },
        
        //
        // get values from editing form and assign to underlaying recordset
        //
        assignValuesIntoRecord:function(){
            _assignValuesIntoRecord();
        },
        
        //
        // returns editing_element by name
        //
        getFieldByName:function(fieldName){
            return _getFieldByName(fieldName);
        },

        // 
        // is_changed == false do not set flag as modified
        // editing element will be recreated!
        //
        setFieldValueByName:function(fieldName, value, is_changed){
            var ele = _getFieldByName(fieldName);
            if(ele && ele.editing_input('instance')){
                ele.editing_input('setValue', $.isArray(value)?value:[value], (is_changed===false));
                if(is_changed!==false){
                    ele.editing_input('isChanged', true);    
                    ele.editing_input('onChange');
                    //_onChange();
                }
            }
        },
        
        //
        // is_changed == false do not set flag as modified
        // editing element will NOT be recreated!
        // value assigned to the first input element only
        //
        setFieldValueByName2:function(fieldName, value, is_changed){
        
            var ele = _getFieldByName(fieldName);
            if(ele && ele.editing_input('instance')){
                
                    var elements = ele.editing_input('getInputs') //_getInputs(fieldName);               
                    $(elements[0]).val( value );
                            
                    if(is_changed!==false){
                        ele.editing_input('isChanged', true);    
                        ele.editing_input('onChange');
                    }
            }
        },
        
        //
        //
        //
        getAllFields: function(){
            return editing_inputs;    
        },
        
        //
        // returns array of ALL input elements by field name
        //
        getInputs:function(fieldName){
            return _getInputs(fieldName);
        },
        
        //
        // returns array of input elements by value
        //
        getFieldByValue:function(fieldName, value){
            return _getFieldByValue(fieldName, value);
        },
        
        getFieldByClass:function(className){
            return _getFieldByClass(className);
        },
        
        isModified: function(){
            return _isModified();
        },
        
        setModified: function(val){
            
            if(val===0){
                that.wasModified = 0;    
                var idx;
                for (idx in editing_inputs) {
                    ele = $(editing_inputs[idx]);
                    
                    if(ele.editing_input('instance')){
                        ele.editing_input('setUnchanged');
                    }
                }
                
            }else{
                that.wasModified = (val===false)?2:1;
            }
        },
        
        
        
        getContainer: function(){
            return $container;
        },
        
        setDisabled: function(mode){
            _setDisabled(mode);
        },
        
        setFocus: function(){
            _setFocus();
        },
        
        editStructureFlag: function(value){
            if(value===true || value===false){
                _editStructureMode = value;      
            }else{
                return _editStructureMode;
            }
        }
        
    }

    _init(_options);
    return that;  //returns object
}
/*
           var rfrs = rectypes.typedefs[rectypeID].dtFields;
            var fi = rectypes.typedefs.dtFieldNamesToIndex;

            var order = rectypes.dtDisplayOrder[rectypeID];
            if(order){
                var i, l = order.length;

                //???? move outside????
                var $header = $('<div>')
                        .css('padding','0.4em')
                        .addClass('ui-widget-header ui-corner-all')
                        .appendTo($container);
                $('<div style="display:inline-block"><span>'
                        + rectypes.names[rectypeID]
                        +'</span><h3 id="recTitle">'
                        +recdata.fld(record, 'rec_Title')
                        +'</h3></div>')
                        .appendTo($header);

                var $toolbar = $('<div>')
                    .css('float','right')
                    .appendTo( $header );

                _create_toolbar($toolbar);

                $('<button>', {text:'Save'}).button().on("click", function(event){ _save(); } ).appendTo($toolbar);
                $('<button>', {text:'Cancel'}).button().appendTo($toolbar);
                //???? END move outside????

                var $formedit = $("<div>").appendTo($container);
                var $fieldset = $("<fieldset>").appendTo($formedit);

                for (i = 0; i < l; ++i) {
                    var dtID = order[i];
                    if (rfrs[dtID][fi['rst_RequirementType']] == 'forbidden') continue;

                    var values = recdata.fld(record, dtID);

                    $("<div>").editing_input(
                              {
                                recID: recID,
                                rectypeID: rectypeID,
                                dtID: dtID,
                                rectypes: rectypes,
                                values: values
                              })
                              .appendTo($fieldset);

                }
            }
*/