/**
 * @file editing2.js
 * @brief Defines the HEditing class for dynamic form generation and management.
 *
 * @fileOverview Defines the HEditing class, a powerful tool for dynamically generating
 *              and managing complex data entry forms within the Heurist system. It handles
 *              various field types, grouping, validation, and data retrieval.
 *              It integrates with TinyMCE for rich text editing and uses the
 *              `editing_input` jQuery plugin for individual field rendering.
 *
 * @project     Heurist academic knowledge management system
 *
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
 * @since       4.0
 *
 * @todo Consider converting HEditing to an ES6 class for better syntax and maintainability if future refactoring is planned.
 */

/**
 * @namespace Widgets.Editing
 * @description Edit forms and input widgets
 */

/**
 * @class HEditing
 * @memberof Widgets.Editing
 * @classdesc  The HEditing class is responsible for rendering and managing dynamic data entry forms.
 * It supports complex layouts with nested groups (tabs, accordions, simple groups), various field types
 * (freetext, blocktext, geo, etc., via `editing_input` jQuery plugin), data validation,
 * and communication of changes. It integrates with TinyMCE for rich text editing.
 * 
 * @constructor
 * @description Constructs an HEditing instance to manage a dynamic form.
 * This class takes a structure definition and data, then renders an interactive form
 * with various input types, groupings (tabs, accordions), and handles data manipulation.
 * 
 * @param {object} _options - Configuration options for the HEditing instance.
 * @param {string|jQuery} _options.container - The DOM element (or its ID) where the form will be rendered.
 * @param {object} [_options.entity] - Configuration object for the entity type being edited.
 * @param {Array<object>} _options.recstructure - An array defining the form structure, including field groups and fields.
 *        Each object in the array can define a group (with `groupType`, `groupHeader`, `children`) or a field (with `dtID`, `dtFields`).
 * @param {HRecordSet} [_options.recdata] - An HRecordSet object containing the initial data to populate the form.
 * @param {function} [_options.onchange] - Callback function triggered when any field value changes.
 * @param {function} [_options.onrecreate] - Callback function triggered when an input element is recreated.
 * @param {function} [_options.oninit] - Callback function triggered after the HEditing instance is fully initialized.
 * @param {string} [_options.className] - An optional CSS class name to apply to the main form container.
 *
 */
function HEditing(_options) {
     const _className = "Editing";

     let $container = null,
         recdata = null,     //HRecordSet with data to be edited
         editing_inputs = [],
         recstructure,
         onChangeCallBack=null,
         entityConfig = null,
         options = {},
         _editStructureMode = false,
         onNewInputCallBack=null;

    /**
    * Initialization
    * options:
    *   container - element Id or jquery element
    *   entity 
    *   recstructure - configuration of fields
    *   recdata - initial data
    *   onchange
    */
    function _init(_options) {
        
        if(typeof tinyMCE === 'undefined'){
            _loadTinyMCE(function(){
                _init(_options);    
            });
            return;        
        }
        
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
        onNewInputCallBack = _options.onrecreate;
        
        if(!_options.className && $container.parents('.editor').length>0) {
                _options.className = '';
        }
        
        options = _options;
        
        _initEditForm(_options.recstructure, _options.recdata);
        
        if(window.hWin.HEURIST4.util.isFunction(_options.oninit)){ //init completed
            _options.oninit.call(that);
        }
    }
    
    /**
     * @private
     * @function _loadTinyMCE
     * @description Loads the TinyMCE script dynamically if it's not already loaded.
     * @param {function} callback - Function to call after TinyMCE is loaded.
     */
    function _loadTinyMCE(callback) {
       const tinyMCEPath = window.hWin.HAPI4.baseURL+'external/tinymce5/tinymce.min.js';
       const script = window.hWin.document.createElement('script');
       script.id = 'tiny-mce-script';
       script.onload = function(){  //() => 
         // tinymce is loaded at this point
        
         callback.call(this);
       };
       script.src = tinyMCEPath;
       window.hWin.document.head.appendChild(script);
    }    
    
    /**
     * NOT USED
     * @private
     * @function _reload
     * @description Reloads the form with new data.
     * @param {HRecordSet} _recdata - The new recordset data.
     */
    function _reload(_recdata) {
        
        if(!$container) return;

        recdata = _recdata;

        if(!recdata) return; //nothing to edit

        //create form, fieldset and input elements according to record type/entity structure

        let record = recdata.getFirstRecord();

        if(record!=null){
            //fill form with values
            let idx, ele;
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                let val = recdata.values(record, ele.editing_input('option', 'dtID'));
                if(!Array.isArray(val)) val = [val];
                ele.editing_input('setValue', val );
            }
            
        }
    }
    
    /**
     * @private
     * @function _setDisabled
     * @description Sets the disabled state for all input fields in the form.
     * @param {boolean} mode - True to disable, false to enable.
     */
    function _setDisabled(mode){
            let idx, ele;
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                ele.editing_input('setDisabled', mode);
            }
    }
    
    /**
     * @private
     * @function _initEditForm
     * @description Initializes and builds the main editing form based on record structure and data.
     * Clears any existing form content, processes the structure for groups and fields,
     * and renders the form elements.
     * @param {Array<object>} _recstructure - The form structure definition.
     * @param {HRecordSet} _recdata - The data to populate the form.
     * @param {boolean} [_is_insert] - Flag indicating if the form is for a new record (insert mode).
     */
    function _initEditForm(_recstructure, _recdata, _is_insert){
        
        if(!($container && $container.length>0)) return;
        
        that.wasModified = 0;
        $container.hide();
        $container.empty(); //clear previous edit elements
        editing_inputs = [];
        recdata = _recdata;
        
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(_recstructure) && _recdata==null){
           
            $container.show();
            return;     
        } 
        
        if(_recstructure) recstructure = _recstructure;
                
        let record;
        let recID = '';
        if(window.hWin.HEURIST4.util.isRecordSet(recdata)){
            //for edit mode
                //get record ID
               
                record = recdata.getFirstRecord();
                
                /** @private */
                function __findRecID(fields){
                    for (let idx=0; idx<fields.length; idx++){
                       if(fields[idx].groupType){
                           let _recID = __findRecID(fields[idx].children)
                           if(_recID>0) return _recID;
                       }else
                       if(fields[idx]['keyField']){
                           return recdata.fld(record, fields[idx]['dtID']);
                       }
                    }
                    return '';
                }
                recID = __findRecID(recstructure);
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
        
        //special case for group_inside - add it as a child for parent
        /** @private */
        function __processGroupInside(fields){
        
            let prev_children = null;
            let idx = 0;
            while(idx<fields.length){
                if( $.isPlainObject(fields[idx]) && fields[idx].groupType ){ //this is group
                    
                    if((fields[idx].groupType=='group' || fields[idx].groupType=='accordion_inner' || fields[idx].groupType=='expanded_inner') && prev_children){
                        //move this group inside previous group on the same level
                        prev_children.push(fields[idx]);    
                        fields.splice(idx,1);
                        continue;
                    }else if(fields[idx].groupType=='group_break'){
                        prev_children = null;    
                    }else if (fields[idx].groupType=='group'){ //group inside
                        fields[idx].groupType = 'group_break';  
                    }else if(fields[idx].groupType=='accordion_inner' || fields[idx].groupType=='expanded_inner'){
                        fields[idx].groupType = (fields[idx].groupType=='accordion_inner') ? 'accordion' : 'expanded';
                    }else{
                        prev_children = fields[idx].children;    
                    }
                        
                        // At the moment subgroups are not supported
                        //__processGroupInside(fields[idx].children);  
                    
                }
                idx++;
            }//for
        
        }//__processGroupInside
        __processGroupInside(recstructure);
        
        /** @private */
        function __createGroup(fields, groupContainer, fieldContainer){
            let idx, tab_index;
                
            let currGroupType = null, currGroupHeaderClass = null; //current accodion or tab control
            let groupTabHeader, groupEle;
            let hasVisibleFields = false;
            
            //    groupEle,      //current accordion or tab control
           
                
            for (idx=0; idx<fields.length; idx++){
                
                if( $.isPlainObject(fields[idx]) && fields[idx].groupType ){ //this is group
                    
                    if(fields[idx].groupHidden || fields[idx]['groupTitleVisible']===false){ //this group is hidden all fields goes to previous group

                        if(fieldContainer == null){
                            fieldContainer = groupContainer.find('fieldset').last();
                        }

                        __createGroup(fields[idx].children, groupContainer, fieldContainer);
                        continue;                        
                    }else if(fields[idx].groupType=='group'){ //group inside

                        let headerText = fields[idx]['groupHeader'];
                        let headerHelpText = fields[idx]['groupHelpText'];
                        const is_header_visible = fields[idx]['groupTitleVisible'];

                        if(headerText == '-'){ // Placeholder for no text, just use a simple divider
                            headerText = '';
                            headerHelpText = '';
                        }else if(headerHelpText == 'new separator'){ // remove default separator help
                            headerHelpText = '';
                        }

                        let hele = $('<h4>')
                            .text(headerText).addClass('separator').appendTo(fieldContainer);
                        
                        hele.css({'margin-bottom':'4px'});
                        
                        let div_prompt = $('<div>').text(headerHelpText)
                               .addClass('heurist-helper1')
                               .addClass('separator-helper').css({'padding-left':'20px','padding-bottom':'4px'})
                               .appendTo(fieldContainer);
                        if(!is_header_visible){
                            hele.addClass('separator-hidden').hide();
                            div_prompt.addClass('separator-hidden').hide();
                        }

                        //container for gear-wheel                              
                        let ele = $('<div>').appendTo(fieldContainer);    
                        if(parseInt(fields[idx]['dtID'])>0){ //for Records only
                            ele.attr('data-dtid', fields[idx]['dtID']);
                        }
                        
                        __createGroup(fields[idx].children, groupContainer, fieldContainer);
                        continue;
                    }else if(fields[idx].groupType=='accordion_inner' || fields[idx].groupType=='expanded_inner'){ // accordion within another group

                        let headerText = fields[idx]['groupHeader'];
                        let headerHelpText = fields[idx]['groupHelpText'];

                        let $group_ele = $('<div>').css('width', '100%').appendTo(fieldContainer);
                        let $field_ele = $('<fieldset>').addClass(options.className).appendTo($group_ele);

                        let $help_ele = $('<div>').text(headerHelpText)
                            .addClass('heurist-helper1 tab-separator-helper')
                            .css({padding:'5px 0 0 5px',display:'inline-block'})
                            .appendTo($field_ele);

                        $('<h3>').html('<span class="separator2">'+headerText+'</span>').appendTo($group_ele);
                        $field_ele.appendTo($('<div>').css('border', 'none').appendTo($group_ele));

                        if(parseInt(fields[idx]['dtID'])>0){
                            $field_ele.attr('data-dtid', fields[idx]['dtID']);
                            $help_ele.attr('separator-dtid', fields[idx]['dtID']);
                        }

                        $group_ele.accordion({
                            heightStyle: 'content',
                            active: (fields[idx].groupType == 'expanded_inner') ? 0 : false,
                            collapsible: true
                        }).css('width', '100%');


                        __createGroup(fields[idx].children, groupContainer, $field_ele);
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
                    
                    let headerText = fields[idx]['groupHeader'];
                    let headerHelpText = fields[idx]['groupHelpText'];
                    const is_header_visible = fields[idx]['groupTitleVisible'];
                    
                    let newFieldContainer = $('<fieldset>').uniqueId();
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

                    }
                    else if(currGroupType == 'tabs'){
                        // class="separator2"
                        $('<li>').addClass('edit-form-tab').html('<a href="#'+newFieldContainer.attr('id')+'"><span style="font-weight:bold">'+headerText+'</span></a>')
                        .appendTo(groupTabHeader);

                        $(newFieldContainer).appendTo(groupEle);

                        newFieldContainer.addClass(options.className);

                    }
                    else{
                        
                        let ele = $('<h4>').text(headerText).addClass('separator');
                        
                        ele.appendTo(groupContainer);    

                        if(!is_header_visible){
                            ele.addClass('separator-hidden').hide();
                        }

                        newFieldContainer.appendTo(groupContainer);
                    }
                    const is_show_header_help_text = true; //This flag is always true.
                    if(is_show_header_help_text){
                         let div_prompt = $('<div>').text(headerHelpText)
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
                        $('<div>')
                            .text(top.HEURIST4.ui.getRidGarbageHelp(fields[idx]['rst_DisplayHelpText']))
                            .addClass('heurist-helper1').appendTo(fieldContainer);
                        //see applyCompetencyLevel
                    }else  
                    {
                        
                        //assign values from record
                        if(record!=null){
                            
                            let val;
                            if(fields[idx]['dty_Type']=="geo"){
                                val = recdata.getFieldGeoValue(record, fields[idx]['dtID']);
                            }else{
                                val = recdata.values(record, fields[idx]['dtID']);
                            }

                            if(!window.hWin.HEURIST4.util.isnull(val)){
                                if(!Array.isArray(val)) val = [val];
                                fields[idx].values = val;
                            }else{
                                fields[idx].values = null;
                            }  
                            
                        }else{
                        //new record - reset all values    
                            fields[idx].values = null;    
                        }
                        
                        fields[idx].recID = recID;
                        fields[idx].recordset = recdata;
                        fields[idx].editing = that;
                        fields[idx].change = _onChange;
                        fields[idx].onrecreate = onNewInputCallBack;
                        fields[idx].is_insert_mode = _is_insert;
                        
                        
                        let inpt = $('<div>').css('display','block !important')
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
                    groupEle.tabs({active: 0}).addClass('edit-form-tabs');
                }
            }
            
            if(!hasVisibleFields && 
                (fieldContainer==null || fieldContainer.find('.input-cell').length == 0)){ //fieldContainer could be null here if all items in `fields` were groups.
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
        
        let $div_hints = $('<div>').css({'padding-top':'5px', 'padding-left': '180px'}).appendTo($container); //float: 'left'
        if($container.find('.forbidden').length>0 && window.hWin.HAPI4.is_admin()){
            $('<div>').css({padding: '4px'})
                .addClass('hidden_field_warning')
                .html('There are hidden fields in this form. <span class="btn-modify_structure"'
                +'  style="cursor:pointer;display:inline-block;color:#7D9AAA;">'
                +'Modify structure</span> to enable them.').appendTo($div_hints);
        }

        let is_Records = recdata && recdata.entityName=='Records';
        if(is_Records && $container.find('ul[role="tablist"]').length>0){
            
            let tab_groups = $container.find('ul[role="tablist"]');
            $.each(tab_groups, function(idx, group){
                let $tabs = $(group).find('a');
                let max_char = 30;

                $tabs.attr('style', 'max-width:'+max_char+'ex;width:auto;padding-right:30px !important;cursor:pointer;').addClass('truncate');
            });
			
			if($container.find('ul[role="tablist"]').length>1){
                let groups = $container.children('div');

                $.each(groups, function(idx, group){
                    if(idx == 0){
                        return;
                    }

                    if($(group).hasClass('ui-tabs') && $(groups[idx-1]).is('.ui-tabs, .ui-accordion')){
                        $(group).css('margin-top', $(groups[idx-1]).hasClass('ui-tabs') ? '40px' : '20px');
                    }
                });
            }
        }
        if(is_Records && $container.find("div.optional").length > 0){
            $('<div>').css({padding: '4px'}).addClass('optional_hint')
                    .html('Fields missing? Turn on <u>optional fields</u> (checkbox at the top of page)').appendTo($container);
        }
    }
    
    /**
     * @private
     * @function _setFocus
     * @description Sets focus to the first focusable input element in the form.
     */
    function _setFocus(){
        if(editing_inputs.length>0){
            let idx, ele;
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                if(ele.editing_input('instance') && ele.editing_input('focus')){
                    break;    
                }
            }
        }
    }
    
    /**
     * @private
     * @function adjustHelpWidth
     * @description Adjusts the width of help text containers to align with input field widths.
     */
    function adjustHelpWidth(){
        
        let maxW = 300;
        if(editing_inputs.length>0){
            let idx, ele; 
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
        
       
    }
    

    /**
     * @private
     * @function _save
     * @description Placeholder for a save operation. Currently shows an alert.
     * @returns {boolean} Always true.
     */
    function _save(){
        alert('save');
        return true;
    }

    /**
     * @private
     * @function _getValue
     * @description Retrieves the value(s) for a specific field by its dtID.
     * @param {string|number} dtID - The ID of the data type field.
     * @returns {Array<any>|null} An array of values for the field, or null if not found or empty.
     */
    function _getValue(dtID){
        for (let idx in editing_inputs) {
            let ele = $(editing_inputs[idx]);
            if(ele.editing_input('instance') && ele.editing_input('option', 'dtID')==dtID){
                let vals = ele.editing_input('getValues');
                if(vals && vals.length>0){
                    return ele.editing_input('getValues');
                }else{
                    return null;
                }
            }
        }
        return null;
    }
    
    /**
     * @private
     * @function _getValues
     * @description Retrieves all values from the form.
     * @param {boolean} [needArrays=false] - If true, all values are returned as arrays, even single values.
     *                                       If false or undefined, single values are returned directly,
     *                                       multi-values or objects as arrays.
     * @returns {Object<string, any|Array<any>>} An object where keys are dtIDs and values are the field values.
     */
    function _getValues(needArrays){
        
        let details = {};
        for (let idx in editing_inputs) {
            let ele = $(editing_inputs[idx]);
            let vals = ele.editing_input('getValues');

            if(vals && vals.length>0){
                
                let a_val;
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
    
    /**
     * @private
     * @function _getFieldsVisibility
     * @description Retrieves the visibility settings for each field in the form.
     * @returns {Object<string, Array<number>>} An object where keys are dtIDs and values are arrays representing visibility states.
     */
    function _getFieldsVisibility(){
        
        let idx, ele, details = {};
        for (idx in editing_inputs) {
            ele = $(editing_inputs[idx]);
            let dty_ID = ele.editing_input('option', 'dtID'); //field type id
            
            if(window.hWin.HEURIST4.util.isNumber(dty_ID) && dty_ID>0){
                let vals = ele.editing_input('getVisibilities');
                if(vals && vals.length>0){
                    details[ dty_ID ] = vals;
                }
            }
        }
        return details;
    }

    /**
     * @private
     * @function _setFieldsVisibility
     * @description Sets the visibility of fields in the form based on data from a recordset.
     * @param {HRecordSet} recdata - The recordset containing visibility information.
     */
    function _setFieldsVisibility( recdata ){

        if(recdata!=null){ //for edit mode
            let record = recdata.getFirstRecord();
        
            for (let idx in editing_inputs) {
                let ele = $(editing_inputs[idx]);
                
                let dty_ID = ele.editing_input('option', 'dtID'); //field type id
                
                if(window.hWin.HEURIST4.util.isNumber(dty_ID) && dty_ID>0){
                    let visibilities = recdata.getFieldVisibilites(record, dty_ID); //from record['v']
                    ele.editing_input('setVisibilities', visibilities);
                }
            }
        }
    }
    
    /**
     * @private
     * @function _assignValuesIntoRecord
     * @description Assigns the current form values back to the underlying HRecordSet.
     */
    function _assignValuesIntoRecord(){
    
        if(recdata!=null){ //for edit mode
            let record = recdata.getFirstRecord();
            
            for (let idx in editing_inputs) {
                let ele = $(editing_inputs[idx]);
                let vals = ele.editing_input('getValues');
                if(vals && vals.length>0){
                    recdata.setFld(record, ele.editing_input('option', 'dtID'), vals);
                }
            }
        }
    }
    
    /**
     * @private
     * @function _isModified
     * @description Checks if any field in the form has been modified.
     * Considers the public `that.wasModified` flag and also checks individual inputs.
     * @returns {boolean} True if the form is modified, false otherwise.
     */
    function _isModified(){
        
        if(that.wasModified==2){ //modfied flag is reset (after save)
            return false;
        }else if(that.wasModified==1){
            return true;
        }else{
            for (let idx in editing_inputs) {
                let ele = $(editing_inputs[idx]);
                
                if(ele.editing_input('instance') && ele.editing_input('isChanged')) {
                    return true;   
                }
            }
            return false;
        }
    }
    
    /**
     * @private
     * @function _onChange
     * @description Callback function invoked when a field's value changes.
     * Calls the `onChangeCallBack` provided in options.
     */
    function _onChange(){
        if(window.hWin.HEURIST4.util.isFunction(onChangeCallBack)){
            onChangeCallBack.call( this );    
        }
    }
    
    /**
     * @private
     * @function _validate
     * @description Validates all input fields in the form.
     * @returns {boolean} True if all fields are valid, false otherwise.
     */
    function _validate(){
        
        let idx, res = true;
        for (idx in editing_inputs) {
            res = $(editing_inputs[idx]).editing_input('validate') && res;
        }
        
        return res;
    }
    
    /**
     * @private
     * @function _getFieldByName
     * @description Finds and returns the jQuery wrapper for an editing input by its field name (dtID).
     * @param {string|number} fieldName - The dtID of the field.
     * @returns {jQuery|null} The jQuery object for the field's container, or null if not found.
     */
    function _getFieldByName(fieldName){
        let idx, ele;
        for (idx in editing_inputs) {
            ele = $(editing_inputs[idx]);
            if(ele.editing_input('instance') && ele.editing_input('option', 'dtID')  == fieldName){
                return ele;
            }
        }
        return null;
    }

    /**
     * @private
     * @function _getFieldByValue
     * @description Finds input elements based on a sub-property of their configuration.
     * Used with `ele.editing_input('f', fieldName)` which implies getting a configuration option
     * of the `editing_input` widget itself, not the value of the input.
     * @param {string} fieldName - The name of the configuration property on the `editing_input` widget.
     * @param {any} value - The value to match for the specified configuration property.
     *                      If `value` is `'[not empty]'`, it checks for non-empty values of that property.
     * @returns {Array<jQuery>} An array of jQuery objects for matching field containers.
     */
    function _getFieldByValue(fieldName, value){
        let idx, ele, ress = [], val;
        if(value==='[not empty]'){
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                val = ele.editing_input('f', fieldName) // get options.dtFields[fieldName]
                if(!window.hWin.HEURIST4.util.isempty(val)){
                    ress.push(ele);
                }
            }
        }else{
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                if(ele.editing_input('f', fieldName)  == value){ // get options.dtFields[fieldName]
                    ress.push(ele);
                }
            }
        }
        
        return ress;
    }

    /**
     * @private
     * @function _getFieldByClass
     * @description Finds input elements that have a specific CSS class.
     * @param {string} className - The CSS class to search for.
     * @returns {Array<jQuery>} An array of jQuery objects for matching field containers.
     */
    function _getFieldByClass(className){
        let idx, ele, ress = [];
        for (idx in editing_inputs) {
            ele = $(editing_inputs[idx]);
            if(ele.hasClass(className)){
                ress.push(ele);
            }
        }
        return ress;
    }

        
    /**
     * @private
     * @function _getInputs
     * @description Retrieves the actual HTML input/textarea/select elements for a given field name (dtID).
     * @param {string|number} fieldName - The dtID of the field.
     * @returns {Array<HTMLElement>|undefined} An array of DOM input elements, or undefined if the field is not found.
     */
    function _getInputs(fieldName){
        let ele = _getFieldByName(fieldName);
        if(ele && ele.length>0){
            return ele.editing_input('getInputs');
        }
    }

    /**
     * @private
     * @function _displayValueErrors
     * @description Displays validation errors for specified fields, if errors exist in the record data.
     * @param {string|Array<string>} fieldNames - A single field name (dtID) or an array of field names.
     */
    function _displayValueErrors(fieldNames){

        if(!Array.isArray(fieldNames)){
            fieldNames = fieldNames.split(',');
        }

        for(const fieldName of fieldNames){

            let ele = _getFieldByName(fieldName);
            let record = recdata.getFirstRecord();

            if(ele && ele.length > 0 && record?.errors?.[fieldName]){
                ele.editing_input('showValueErrors', record.errors[fieldName]);
            }
        }
    }


    //public members
    let that = {
        
        /**
         * Flag indicating the modification state of the form.
         * 0: Not modified (initial state).
         * 1: Modified by user.
         * 2: Finalized (e.g., after save, considered not modified for further checks).
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @type {number}
         */
        wasModified: 0,

        /**
         * Reloads the form with new data. Assumes the form structure remains the same.
         * (Marked as NOT USED in original source comments).
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {HRecordSet} _recdata - The new HRecordSet data to populate the form.
         */
        reload: function(_recdata){
            _reload(_recdata);
        },

        /**
         * Validates all fields in the form.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @returns {boolean} True if all fields are valid, false otherwise.
         */
        validate: function(){
            return _validate();
        },

        /**
         * Placeholder save function. Currently alerts 'save'.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @returns {void}
         */
        save: function(){
            _save();
        },
        
        /**
         * Initializes or re-initializes the editing form with a given structure and data.
         * This will clear any existing form content in the container.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {Array<object>} _recstructure - The structure definition for the form.
         * @param {HRecordSet} _recdata - The HRecordSet data to populate the form.
         * @param {boolean} [_is_insert=false] - Flag indicating if the form is for creating a new record.
         */
        initEditForm: function(_recstructure, _recdata, _is_insert){
            _initEditForm(_recstructure, _recdata, _is_insert);
        },
        
        /**
         * Gets the value(s) of a specific field by its dtID.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {string|number} dtID - The ID of the data type field.
         * @returns {Array<any>|null} An array of values for the field. Returns null if the field is not found or has no values.
         */
        getValue:function(dtID){
            return _getValue(dtID);
        },
        
        /**
         * Gets all values from the form as an object.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {boolean} [needArrays=false] - If true, all field values are returned as arrays,
         *                                       even if they are single values. If false or omitted,
         *                                       single values are returned directly, while multi-value fields
         *                                       or fields with object values are returned as arrays.
         * @returns {Object<string, any|Array<any>>} An object where keys are field dtIDs and values are the corresponding field values.
         */
        getValues:function(needArrays){
            return _getValues(needArrays);
        },
        
        /**
         * Gets the visibility settings for all fields in the form.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @returns {Object<string, Array<number>>} An object where keys are field dtIDs
         *                                          and values are arrays representing visibility states (e.g., [1,0,1]).
         */
        getFieldsVisibility: function(){
            return _getFieldsVisibility();
        },
        
        /**
         * Sets the visibility of fields in the form based on data from a recordset.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {HRecordSet} vals - The HRecordSet containing visibility information.
         *                          Typically, this information is stored in a specific part of the record.
         */
        setFieldsVisibility: function(vals) {
            _setFieldsVisibility( vals );    
        },
        
        /**
         * Assigns the current values from the form fields back to the HRecordSet instance
         * that was provided during initialization or via `reload`.
         * @instance
         * @memberof Widgets.Editing.HEditing
         */
        assignValuesIntoRecord:function(){
            _assignValuesIntoRecord();
        },
        
        /**
         * Gets the jQuery wrapper for the specified field.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {string|number} fieldName - The dtID of the field.
         * @returns {jQuery|null} The jQuery object for the field's container, or null if not found.
         */
        getFieldByName:function(fieldName){
            return _getFieldByName(fieldName);
        },

        /**
         * Sets the value of a field by its name (dtID). The field's input element(s) might be recreated.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {string|number} fieldName - The dtID of the field.
         * @param {any|Array<any>} value - The value or array of values to set.
         * @param {boolean} [is_changed=true] - If true (default), marks the field as changed and triggers the onChange callback.
         *                                      If false, the field is not marked changed and onChange is not triggered.
         */
        setFieldValueByName:function(fieldName, value, is_changed){
            let ele = _getFieldByName(fieldName);
            if(ele && ele.editing_input('instance')){
                ele.editing_input('setValue', Array.isArray(value)?value:[value], (is_changed===false));
                if(is_changed!==false){
                    ele.editing_input('isChanged', true);    
                    ele.editing_input('onChange');
                   
                }
            }
        },
        
        /**
         * Sets the value of a field by its name (dtID) directly into the first input element.
         * The input element will NOT be recreated. This is a more direct manipulation.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {string|number} fieldName - The dtID of the field.
         * @param {any} value - The value to set in the first input element of the field.
         * @param {boolean} [is_changed=true] - If true (default), marks the field as changed and triggers the onChange callback.
         *                                      If false, the field is not marked changed and onChange is not triggered.
         */
        setFieldValueByName2:function(fieldName, value, is_changed){
        
            let ele = _getFieldByName(fieldName);
            if(ele && ele.editing_input('instance')){
                
                    let elements = ele.editing_input('getInputs') //_getInputs(fieldName);               
                    $(elements[0]).val( value );
                            
                    if(is_changed!==false){
                        ele.editing_input('isChanged', true);    
                        ele.editing_input('onChange');
                    }
            }
        },
        
        /**
         * Gets all jQuery wrappers for the input fields managed by this HEditing instance.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @returns {Array<jQuery>} An array of jQuery objects, each wrapping an `editing_input`.
         */
        getAllFields: function(){
            return editing_inputs;    
        },
        
        /**
         * Gets the actual HTML input/textarea/select DOM element(s) for a given field.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {string|number} fieldName - The dtID of the field.
         * @returns {Array<HTMLElement>|undefined} An array of DOM elements, or undefined if the field is not found.
         */
        getInputs:function(fieldName){
            return _getInputs(fieldName);
        },
        
        /**
         * Finds input elements based on a sub-property of their `editing_input` configuration.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {string} fieldName - The name of the configuration property on the `editing_input` widget.
         * @param {any} value - The value to match. Use `'[not empty]'` to find fields where this property is not empty.
         * @returns {Array<jQuery>} An array of jQuery objects for matching field containers.
         */
        getFieldByValue:function(fieldName, value){
            return _getFieldByValue(fieldName, value);
        },
        
        /**
         * Finds input elements that have a specific CSS class.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {string} className - The CSS class to search for.
         * @returns {Array<jQuery>} An array of jQuery objects for matching field containers.
         */
        getFieldByClass:function(className){
            return _getFieldByClass(className);
        },
        
        /**
         * Checks if the form has been modified.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @returns {boolean} True if modified, false otherwise.
         */
        isModified: function(){
            return _isModified();
        },
        
        /**
         * Sets the modification state of the form.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {boolean|number} val - If `0` or `false`, resets all fields to unchanged and sets `wasModified` to 0 or 2 respectively.
         *                               If `true` or `1`, sets `wasModified` to 1.
         */
        setModified: function(val){
            
            if(val===0){
                that.wasModified = 0;    
                for (let idx in editing_inputs) {
                    let ele = $(editing_inputs[idx]);
                    
                    if(ele.editing_input('instance')){
                        ele.editing_input('setUnchanged');
                    }
                }
                
            }else{
                that.wasModified = (val===false)?2:1;
            }
        },
        
        
        /**
         * Gets the main jQuery container element for this HEditing instance.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @returns {jQuery} The jQuery object for the container.
         */
        getContainer: function(){
            return $container;
        },
        
        /**
         * Sets the disabled state for all input fields in the form.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {boolean} mode - True to disable fields, false to enable them.
         */
        setDisabled: function(mode){
            _setDisabled(mode);
        },
        
        /**
         * Sets focus to the first focusable input element in the form.
         * @instance
         * @memberof Widgets.Editing.HEditing
         */
        setFocus: function(){
            _setFocus();
        },
        
        /**
         * Gets or sets the structure editing mode flag.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {boolean} [value] - If provided, sets the flag. True for edit structure mode, false otherwise.
         * @returns {boolean|undefined} If `value` is not provided, returns the current state of the flag. Otherwise, undefined.
         */
        editStructureFlag: function(value){
            if(value===true || value===false){
                _editStructureMode = value;      
            }else{
                return _editStructureMode;
            }
        },
        
        /**
         * Gets the options object that was used to initialize this HEditing instance.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @returns {object} The options object.
         */
        getOptions: function (){
            return options;
        },

        /**
         * Displays validation errors for the specified fields.
         * Errors are typically sourced from the `record.errors` property of the HRecordSet.
         * @memberof Widgets.Editing.HEditing
         * @instance
         * @param {string|Array<string>} fieldNames - A single field name (dtID) or an array of field names for which to display errors.
         */
        displayValueErrors: function(fieldNames){
            _displayValueErrors(fieldNames);
        }
    }

    _init(_options);
    return that;  //returns object
}