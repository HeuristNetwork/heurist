/**
* Simpe query builder
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @note        Completely revised for Heurist version 4
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


$.widget( "heurist.search_quick", $.heurist.recordAction, {

    // default options
    options: {
        is_h6style: true,
        is_json_query: true,
        
        isdialog: false, 
        supress_dialog_title: true,
        menu_locked: null, //callback to prevent close h6 menu on mouse exit
        
        button_class: 'ui-button-action',
        
        currentRecordset: {},  //stub
        search_realm: null,
        options_pane_expanded: false,
    },
    
    current_query:null,
    current_query_json:null,

    _suprress_change: true,
    
    //
    //
    //
    _init: function(){
        
        this.element.css('overflow','hidden');
        
        this.options.htmlContent = window.hWin.HAPI4.baseURL+'hclient/widgets/search/search_quick.html'
                            +'?t='+window.hWin.HEURIST4.util.random();
        this._super();        
    },

    /**
     * Initialize all controls.
     *
     * @return {boolean}
     * @private
     */
    _initControls:function(){
        
        var that = this;
        
        var $dlg = this.element.children('fieldset');
        
        if(this.options.is_h6style){
            //add title 
            $dlg.css({top:'36px',position:'absolute',width: 'auto', margin: '0px','font-size':'0.9em'});
            
            var _innerTitle = $('<div class="ui-heurist-header" style="top:0px;padding-left:10px;text-align:left">Filter builder</div>')
                .insertBefore($dlg);
                
            $('<button>').button({icon:'ui-icon-closethick',showLabel:false, label:'Close'}) 
                     .css({'position':'absolute', 'right':'4px', 'top':'6px', height:20, width:20})
                     .appendTo(_innerTitle)
                     .on({click:function(){
                         that.closeDialog();
                     }});
                
        }else{
             $dlg.addClass('ui-heurist-header1');
        }
        
        
        var dv = $dlg.find('.btns')
                .css({'display':'block !important'});

        this.search_quick_go = $( "<button>")
        .appendTo( dv )
        //.css({position:'absolute', zIndex:9999, 'right':4, top:4, width:18, height:18})
        .css('float', 'right')
        .button({
            icon: 'ui-icon-filter',
            label: window.hWin.HR("Filter"), showLabel:true
        })
        .addClass(this.options.button_class);
        
        this._on( this.search_quick_go, {
            click: function(event){
                this.doAction();
            }
        });
        
        
        this.search_get_query = $( "<button>")
        .appendTo( dv )
        .css({'float':'right',margin:'4px 10px'})
        .button({
            label: window.hWin.HR("get filter string"), showLabel:true
        });
        
        this._on( this.search_get_query, {
            click: function(event){
                this.getQueryString();
            }
        });

        //find all labels and apply localization
        $dlg.find('label').each(function(){
            $(this).html(window.hWin.HR($(this).html()));
        });
        
        $dlg.find('.search_field_value_label').html(window.hWin.HR('Contains'));
        $dlg.find(".sa_termvalue").hide();
        $dlg.find('.search_field_coordinates').hide();
        $dlg.find('.search_field_option').hide();

        function __startSearchOnEnterPress(e){
                var code = (e.keyCode ? e.keyCode : e.which);
                if (code == 13) {
                    window.hWin.HEURIST4.util.stopEvent(e);
                    that.doAction();
                }
        }
        
        that._on( $dlg.find('.text'), { keypress: __startSearchOnEnterPress});

        $dlg.find(".sa_spatial").button();
        that._on( $dlg.find(".sa_spatial"), {    //opens digitizer
            click: function(event){
                
                this._lockPopup(true)
                
                //open map digitizer - returns WKT rectangle 
                var rect_wkt = $dlg.find(".sa_spatial_val").val();
                var url = window.hWin.HAPI4.baseURL
                +'viewers/map/mapDraw.php?db='+window.hWin.HAPI4.database;

                var wkt_params = {wkt: rect_wkt, geofilter:true};

                window.hWin.HEURIST4.msg.showDialog(url, {height:'540', width:'600',
                    window: window.hWin,  //opener is top most heurist window
                    dialogid: 'map_digitizer_filter_dialog',
                    params: wkt_params,
                    title: window.hWin.HR('Heurist spatial search'),
                    class:'ui-heurist-bg-light',
                    afterclose: function(){
                        that._lockPopup(false)
                    },
                    callback: function(location){
                        
                        if( !window.hWin.HEURIST4.util.isempty(location) ){
                            //that.newvalues[$input.attr('id')] = location
                            $dlg.find(".sa_spatial_val").val(location.wkt);
                        }
                    }
                } );
            }
        });
        
        $dlg.find(".sa_spatial_clear").button({
            icon: 'ui-icon-undo',
            showLabel:false
        });
        that._on( $dlg.find(".sa_spatial_clear"), {
            click: function(event){
                $dlg.find(".sa_spatial_val").val('');
            }
        });

        that._on($dlg.find(".search_option_switch"), {
            click: function (event) {
                that.toggleOptionsPane();
            }
        });

        that._on($dlg.find('.search_field_value_add'), {
            click: function (event) {
                that.addFieldValueControls(event.target);
            }
        });

        that._on($dlg.find('.search_field_add'), {
            click: function (event) {
                that.addFieldControls(event.target);
            }
        });

        that._on($dlg.find('.search_sort_add'), {
            click: function (event) {
                that.addSortControls(event.target);
            }
        });
        
        this.popupDialog();

        window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(data) { 
                that._recreateSelectors();
            });
        
        this._recreateSelectors();
        this.refreshContainerHeight();
        
        return true;
    },
  
    _lockPopup: function( is_locked ){
        
        if($.isFunction(this.options.menu_locked)){
            this.options.menu_locked.call( this, is_locked );
        }
    },
  
    /**
     * Recreate all select controls.
     *
     * @private
     */
    _recreateSelectors: function(){
        
        this._suprress_change = true;
        
        var that = this;
        var $dlg = this.element.children('fieldset');
        var select_rectype = $dlg.find(".sa_rectype");//.uniqueId();
        var select_fieldtype = $dlg.find(".sa_fieldtype");
        var select_sortby = $dlg.find(".sa_sortby");
        var select_terms = $dlg.find(".sa_termvalue");
        var sortasc =  $dlg.find('.sa_sortasc');
        
        var allowed = Object.keys($Db.baseFieldType);
        allowed.splice(allowed.indexOf("separator"),1);
        allowed.splice(allowed.indexOf("geo"),1);
        allowed.splice(allowed.indexOf("relmarker"),1);
        
        var exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
        
        select_rectype = window.hWin.HEURIST4.ui.createRectypeSelectNew(select_rectype.get(0), 
                    {useIcons: false, useCounts:true, useGroups:true, useIds: (exp_level<2), 
                        topOptions:window.hWin.HR('Any record type'), useHtmlSelect:false});
                        
        //change list of field types on rectype change
        that._on( select_rectype, {
            change: function (event){
                that._resetSearchControls();
                this.search_quick_go.focus();
            }
        });

        that._on( select_fieldtype, {
            change: that._onFieldTypeChangeHandler()
        });                        

        select_rectype.trigger('change'); 
    },

    /**
     * Get the event handler when the field is changed.
     *
     * @return {function}
     * @private
     */
    _onFieldTypeChangeHandler: function () {
        var that = this;

        return function __onFieldTypeChange(event){
            var fieldItemElement = $(event.target).closest('.search_field_item');
            that._resetFieldValueControls(event.target);

            if(event.target.value === 'longitude' || event.target.value === 'latitude'){
                that._setFieldValueControlsToCoordinatesMode(event.target);

            }else{
                var dtID = Number(event.target.value);

                var detailType = '';

                if(Number(dtID)>0){
                    detailType = $Db.dty(dtID,'dty_Type');
                }
                if(detailType === 'enum'  || detailType === 'relationtype'){
                    that._setFieldValueControlsToEnumerationMode(event.target);

                } else if (detailType === 'date'  || detailType === 'float') {
                    that._setFieldValueControlsToNumericMode(event.target);
                } else {
                    that._setFieldValueControlsToCommonMode(event.target);
                }

            }

            that.search_quick_go.focus();
        }
    },

    /**
     * Reset the UI controls to initial states.
     *
     * This will reset the fields, field values, and sort to single, and set them to their
     * initial states.
     *
     * @private
     */
    _resetSearchControls: function () {
        var that = this;
        var $dlg = this.element.children('fieldset');
        $dlg.find('.search_field_item').not(':first').remove();
        that._resetFieldValueControls($dlg.find('.sa_fieldtype')[0]);
        that._setFieldValueControlsToCommonMode($dlg.find('.sa_fieldtype')[0]);
        $dlg.find('.search_sort_item .search_layout_grid .search_layout_row').not(':first').remove();
        that.collapseOptionsPane();
        that.refreshContainerHeight();

        $dlg.find(".sa_sortby").each(function () {
            that._populateSortbyList(this);
        });

        $dlg.find(".sa_fieldtype").each(function () {
            var select = that._populateFieldList(this);
            that._on( select, {
                change: that._onFieldTypeChangeHandler()
            });
        });
    },

    /**
     * Reset the field value controls to their initial states.
     *
     * This will reset the field value controls to single.
     *
     * @param {object} fieldSelectElement The DOM element of the field select control.
     * @private
     */
    _resetFieldValueControls: function (fieldSelectElement) {
        var fieldItemElement = $(fieldSelectElement).closest('.search_field_item');
        fieldItemElement.find('.search_field_common').children('.search_layout_row').not(':first').remove();
    },

    /**
     * Set the field value controls to common mode.
     *
     * The common mode is the default mode which is used for all other type of field such
     * as text and memo types.
     *
     * @param {object} fieldSelectElement The DOM element of the field select control.
     * @private
     */
    _setFieldValueControlsToCommonMode: function (fieldSelectElement) {
        var fieldItemElement = $(fieldSelectElement).closest('.search_field_item');
        fieldItemElement.data('field-type', 'common');
        fieldItemElement.find(".search_field_coordinates").hide();
        fieldItemElement.find(".search_field_common_value").show();
        fieldItemElement.find('.search_field_value_label').html(window.hWin.HR('Contains'));
        fieldItemElement.find('.sa_fieldvalue').show();
        fieldItemElement.find(".sa_termvalue").hide();
        fieldItemElement.find(".sa_termvalue").next('.ui-selectmenu-button').hide();
        this._populateFieldValueOptionsList(fieldItemElement.find(".sa_fieldvalue_option"));
        if (this.options.options_pane_expanded) {
            fieldItemElement.find(".search_field_option").show();
        } else {
            fieldItemElement.find(".search_field_option").hide();
        }

    },

    /**
     * Set the field value controls to numeric mode.
     *
     * The numeric mode is used for number and date field types.
     *
     * @param {object} fieldSelectElement The DOM element of the field select control.
     * @private
     */
    _setFieldValueControlsToNumericMode: function (fieldSelectElement) {
        var fieldItemElement = $(fieldSelectElement).closest('.search_field_item');
        fieldItemElement.data('field-type', 'numeric');
        fieldItemElement.find(".search_field_coordinates").hide();
        fieldItemElement.find(".search_field_common_value").show();
        fieldItemElement.find('.search_field_value_label').html(window.hWin.HR('Contains'));
        fieldItemElement.find('.sa_fieldvalue').show();
        fieldItemElement.find(".sa_termvalue").hide();
        fieldItemElement.find(".sa_termvalue").next('.ui-selectmenu-button').hide();
        this._populateFieldValueOptionsList(fieldItemElement.find(".sa_fieldvalue_option"));
        if (this.options.options_pane_expanded) {
            fieldItemElement.find(".search_field_option").show();
        } else {
            fieldItemElement.find(".search_field_option").hide();
        }
    },

    /**
     * Set the field value controls to coordinates mode.
     *
     * The coordinates mode is used for latitude and longitude.
     *
     * @param {object} fieldSelectElement The DOM element of the field select control.
     * @private
     */
    _setFieldValueControlsToCoordinatesMode: function (fieldSelectElement) {
        var fieldItemElement = $(fieldSelectElement).closest('.search_field_item');
        fieldItemElement.find(".search_field_common_value").hide();
        fieldItemElement.find(".search_field_coordinates").show();
        fieldItemElement.data('field-type', 'coordinates');
        this._populateFieldValueOptionsList(fieldItemElement.find(".sa_fieldvalue_option"));
        fieldItemElement.find(".search_field_option").hide();
    },

    /**
     * Set the field value controls to enumeration mode.
     *
     * The enumeration mode is used for term field types.
     *
     * @param {object} fieldSelectElement The DOM element of the field select control.
     * @private
     */
    _setFieldValueControlsToEnumerationMode: function (fieldSelectElement) {
        var that = this;
        var fieldItemElement = $(fieldSelectElement).closest('.search_field_item');
        fieldItemElement.data('field-type', 'enumeration');
        fieldItemElement.find(".search_field_coordinates").hide();
        fieldItemElement.find(".search_field_common_value").show();
        fieldItemElement.find('.search_field_value_label').html(window.hWin.HR('Is'));
        fieldItemElement.find('.sa_fieldvalue').hide();
        fieldItemElement.find(".sa_termvalue").show();
        this._populateFieldValueOptionsList(fieldItemElement.find(".sa_fieldvalue_option"));
        fieldItemElement.find(".search_field_option").hide();

        fieldItemElement.find(".sa_termvalue").each(function (e) {
            that._populateTermList(this);
        });
    },

    /**
     * Generate the options for the term select control.
     *
     * @param {Object} termSelectElement The DOM element of the term select control.
     * @return {*} The generated select control.
     * @private
     */
    _populateTermList: function (termSelectElement) {
        var fieldTypeID = Number($(termSelectElement).closest('.search_field_item').find('.sa_fieldtype').val());
        var detailType = '';

        if(fieldTypeID > 0){
            detailType = $Db.dty(fieldTypeID,'dty_Type');
        }
        if(detailType === 'enum'  || detailType === 'relationtype') {
            return window.hWin.HEURIST4.ui.createTermSelect(termSelectElement,
                {vocab_id: $Db.dty(fieldTypeID, 'dty_JsonTermIDTree'),
                    topOptions:[{ key:'any', title:window.hWin.HR('<any>')},{ key:'blank', title:'  '}] });
        }
    },

    /**
     * Generate the options for the field select control.
     *
     * @param {Object} fieldSelectElement The DOM element of the field select control.
     * @return {*} The generated select control.
     * @private
     */
    _populateFieldList: function (fieldSelectElement) {
        var $dlg = this.element.children('fieldset');
        var rectTypeValue = Number($dlg.find('.sa_rectype').val());
        var topOptions2 = [{key:'',title:window.hWin.HR('All fields')},{key:'title',title:'Recor title (constructed)'}];
        var bottomOptions = null;

        var allowed = Object.keys($Db.baseFieldType);
        allowed.splice(allowed.indexOf("separator"),1);
        allowed.splice(allowed.indexOf("geo"),1);
        allowed.splice(allowed.indexOf("relmarker"),1);

        if(!(rectTypeValue > 0)){
            bottomOptions = [{key:'latitude',title:window.hWin.HR('geo: Latitude')},
                {key:'longitude',title:window.hWin.HR('geo: Longitude')}];
        }
        var exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);

        var jqUIFieldSelect = window.hWin.HEURIST4.ui.createRectypeDetailSelect(
            fieldSelectElement,
            rectTypeValue, allowed, topOptions2,
            {show_parent_rt:true, show_latlong:true, bottom_options:bottomOptions,
                useIds: (exp_level<2), useHtmlSelect:false});
        return jqUIFieldSelect;

    },

    /**
     * Generate the options for the sort select control.
     *
     * @param {Object} sortBySelectElement The DOM element of the sort select control.
     * @return {*} The generated select control.
     * @private
     */
    _populateSortbyList: function (sortBySelectElement) {
        var $dlg = this.element.children('fieldset');
        var rectTypeValue = Number($dlg.find('.sa_rectype').val());
        var topOptions = [{key:'t', title:window.hWin.HR("record title")},
            {key:'id', title:window.hWin.HR("record id")},
            {key:'rt', title:window.hWin.HR("record type")},
            {key:'u', title:window.hWin.HR("record URL")},
            {key:'m', title:window.hWin.HR("date modified")},
            {key:'a', title:window.hWin.HR("date added")},
            {key:'r', title:window.hWin.HR("personal rating")},
            {key:'p', title:window.hWin.HR("popularity")}];

        var allowed = Object.keys($Db.baseFieldType);
        allowed.splice(allowed.indexOf("separator"),1);
        allowed.splice(allowed.indexOf("geo"),1);
        allowed.splice(allowed.indexOf("relmarker"),1);

        if(rectTypeValue > 0){
            topOptions.push({optgroup:'yes', title:$Db.rty(rectTypeValue, 'rty_Name')+' '+window.hWin.HR('fields')});
        }
        var jqUISortBySelect = window.hWin.HEURIST4.ui.createRectypeDetailSelect(
            sortBySelectElement,
            rectTypeValue, allowed, topOptions,
            {initial_indent:1, useHtmlSelect:false});

        jqUISortBySelect.css({margin:'4px 0px'});
        return jqUISortBySelect;
    },

    /**
     * Generate the options for the field value option select control.
     *
     * @param {Object} optionSelectElement The DOM element of the field value option select control.
     * @private
     */
    _populateFieldValueOptionsList: function (optionSelectElement) {
        var fieldType = $(optionSelectElement).closest('.search_field_item').data('field-type');
        var options = '';
        if (fieldType === 'common') {
            options = '<option selected value="all">All words</option>' +
                '<option value="any">Any word</option>' +
                '<option value="quoted">Quoted string</option>';
        } else if (fieldType === 'numeric') {
            options = '<option selected value="equal">=</option>' +
                '<option value="less">&lt;</option>' +
                '<option value="greater">&gt;</option>';
        }
        $(optionSelectElement).html(options);
    },


    /**
     * Generate the search query from the UI form.
     */
    calcShowSimpleSearch: function(){

        if (this.options.is_json_query) {
            this.current_query_json = this._jsonQueryStringConstructor();
        } else {
            this.current_query = this._simpleQueryStringConstructor();
        }
    },

    /**
     * TODO: fill up the function for the simple filter syntax queries.
     * @return {string}
     * @private
     */
    _simpleQueryStringConstructor: function () {
        return '';
    },

    /**
     * Generate the JSON syntax query from the UI elements.
     *
     * @return {string} The search query in JSON syntax.
     * @private
     */
    _jsonQueryStringConstructor: function () {
        var that = this;

      
        //if(!this._suprress_change) this._lockPopup('delay');
        

        var $dlg = this.element.children('fieldset');
        var queryObject = {};

        var recTypeValue = $dlg.find(".sa_rectype").val();
        if (recTypeValue) {
            queryObject.t = recTypeValue;
        }

        var numOfField = $dlg.find('.search_field_item').length;
        if (numOfField === 1) {
            queryObject = this._mergeQueryObject(queryObject, this._createFieldQueryObject($dlg.find('.search_field_item')[0]));
        } else {
            var fieldItems = [];
            var operators = [];
            $dlg.find('.search_field_item').each(function () {
                fieldItems.push(that._createFieldQueryObject(this));
                if ($(this).find('.sa_field_operator').length > 0) {
                    operators.push($(this).find('.sa_field_operator').val());
                }
            });
            queryObject = this._mergeQueryObject(queryObject, this._createLogicalObject(fieldItems, operators));
        }

        if($dlg.find(".sa_spatial_val").val()){
            queryObject.geo = $dlg.find(".sa_spatial_val").val();
        }

        var sortByValues = [];
        var sortOrderOperator;
        var sortByValue;
        $dlg.find('.search_sort_item > .search_layout_grid > .search_layout_row').each(function () {
            sortOrderOperator = ($(this).find(".sa_sortasc").val() === "1" ? "-" : '') ;
            sortByValue = $(this).find(".sa_sortby").val();
            if (!(sortByValue === 't' && sortOrderOperator === '')) {
                sortByValues.push(sortOrderOperator + (isNaN(sortByValue) ? "" : "f:") + sortByValue);
            }
        });
        if (sortByValues.length > 1) {
            queryObject.sortby = sortByValues;
        } else if (sortByValues.length === 1) {
            queryObject.sortby = sortByValues[0];
        }

        if(Object.keys(queryObject).length === 0){
            queryObject.sortby = 't';
        }
        return JSON.stringify(queryObject);
    },

    /**
     * Construct the query object for a single field.
     *
     * @param {Object} fieldItemElement The DOM element of the field wrap (class .search_field_item).
     * @return {Object}
     * @private
     */
    _createFieldQueryObject: function (fieldItemElement) {
        var queryObject = {};
        var that = this;

        fieldItemElement = $(fieldItemElement);

        var fieldType = fieldItemElement.data('field-type');
        var fieldTypeValue = this._createFieldQueryObjectName(fieldItemElement.find(".sa_fieldtype").val());

        var numOfValues;
        var fieldValueItems;
        var fieldOperators;

        if (fieldType === 'coordinates') {
            var coord1 = fieldItemElement.find(".sa_coord1").val();
            var coord2 = fieldItemElement.find(".sa_coord2").val();

            var morethan = !isNaN(parseFloat(coord1));
            var lessthan = !isNaN(parseFloat(coord2));

            if(morethan && lessthan){
                queryObject[fieldTypeValue] = coord1 + '<>' + coord2;
            }else if(morethan){
                queryObject[fieldTypeValue] = '>' + coord1;
            }else if(lessthan){
                queryObject[fieldTypeValue] = '<' + coord2;
            }
        } else if (fieldType === 'numeric') {
            numOfValues = fieldItemElement.find('.search_field_common > .search_layout_row').length;
            if (numOfValues > 1) {
                fieldValueItems = [];
                fieldOperators = [];
                fieldItemElement.find('.search_field_common > .search_layout_row').each(function () {
                    fieldValueItems.push(that._createNumericValueQueryObject(
                        fieldTypeValue,
                        $(this).find('.sa_fieldvalue').val(),
                        $(this).find('.sa_fieldvalue_option').val()));
                    if ($(this).find('.sa_fieldvalue_operator').length > 0) {
                        fieldOperators.push($(this).find('.sa_fieldvalue_operator').val());
                    }
                });
                queryObject = this._createLogicalObject(fieldValueItems, fieldOperators);
            } else {
                queryObject = this._createNumericValueQueryObject(fieldTypeValue, fieldItemElement.find('.sa_fieldvalue').val(), fieldItemElement.find('.sa_fieldvalue_option').val());
            }
        } else if (fieldType === 'enumeration') {
            numOfValues = fieldItemElement.find('.search_field_common > .search_layout_row').length;
            if (numOfValues > 1) {
                fieldValueItems = [];
                fieldOperators = [];
                fieldItemElement.find('.search_field_common > .search_layout_row').each(function () {
                    fieldValueItems.push(that._createEnumerationValueQueryObject(
                        fieldTypeValue,
                        $(this).find('.sa_termvalue').val(),
                        $(this).find(".sa_negate").is(':checked')));

                    if ($(this).find('.sa_fieldvalue_operator').length > 0) {
                        fieldOperators.push($(this).find('.sa_fieldvalue_operator').val());
                    }
                });
                queryObject = this._createLogicalObject(fieldValueItems, fieldOperators);
            } else {
                queryObject = this._createEnumerationValueQueryObject(fieldTypeValue, fieldItemElement.find('.sa_termvalue').val(), fieldItemElement.find(".sa_negate").is(':checked'));
            }
        } else {
            numOfValues = fieldItemElement.find('.search_field_common > .search_layout_row').length;
            if (numOfValues > 1) {
                fieldValueItems = [];
                fieldOperators = [];
                fieldItemElement.find('.search_field_common > .search_layout_row').each(function () {
                    fieldValueItems.push(that._createTextValueQueryObject(
                        fieldTypeValue,
                        $(this).find('.sa_fieldvalue').val(),
                        $(this).find('.sa_fieldvalue_option').val(),
                        $(this).find(".sa_negate").is(':checked')));
                    if ($(this).find('.sa_fieldvalue_operator').length > 0) {
                        fieldOperators.push($(this).find('.sa_fieldvalue_operator').val());
                    }
                });
                queryObject = this._createLogicalObject(fieldValueItems, fieldOperators);
            } else {
                queryObject = this._createTextValueQueryObject(fieldTypeValue, fieldItemElement.find('.sa_fieldvalue').val(), fieldItemElement.find('.sa_fieldvalue_option').val(), fieldItemElement.find(".sa_negate").is(':checked'));
            }
        }
        return queryObject;
    },

    /**
     * Construct the property name of a field in the query object.
     *
     * @param {string} fieldName Original field name.
     * @return {string}
     * @private
     */
    _createFieldQueryObjectName: function (fieldName) {
        if (fieldName === "") {
            fieldName = 'all';
        } else if (Number(fieldName) > 0) {
            fieldName = 'f:' + fieldName;
        }
        return fieldName;
    },

    /**
     * Construct the query object for the text type value.
     *
     * @param {string} fieldName The field property name in the query object.
     * @param {string} fieldValue The field value.
     * @param {string} option The field option value.
     * @param {boolean} negate The negate flag.
     * @return {Object}
     * @private
     */
    _createTextValueQueryObject: function (fieldName, fieldValue, option, negate) {
        var words = fieldValue.split(' ');
        var queryObj = {};
        if (words.length === 1 || option === 'quoted') {
            if (negate) {
                fieldValue = '-' + fieldValue;
            }
            queryObj[fieldName] = fieldValue;
        } else {
            queryObj[option] = [];
            var i;
            var wordQueryObj;
            for (i = 0; i < words.length; i++) {
                wordQueryObj = {};
                wordQueryObj[fieldName] = (negate ? '-' : '') + words[i];
                queryObj[option].push(wordQueryObj);
            }
        }
        return queryObj;
    },

    /**
     * Construct the query object for the numeric type value.
     *
     * @param {string} fieldName The field property name in the query object.
     * @param {string} fieldValue The field value.
     * @param {string} option The field option value.
     * @return {Object}
     * @private
     */
    _createNumericValueQueryObject: function (fieldName, fieldValue, option) {
        var queryObj = {};
        if (option === 'greater') {
            fieldValue = '>' + fieldValue;
        } else if (option === 'less') {
            fieldValue = '<' + fieldValue;
        }
        queryObj[fieldName] = fieldValue;
        return queryObj;
    },

    /**
     * Construct the query object for the enumeration type value.
     *
     * @param {string} fieldName The field property name in the query object.
     * @param {string} fieldValue The field value.
     * @param {boolean} negate The negate flag.
     * @return {Object}
     * @private
     */
    _createEnumerationValueQueryObject: function (fieldName, fieldValue, negate) {
        var queryObj = {};
        if(fieldValue === 'any' || fieldValue === 'blank'){
            fieldValue = '';
        }
        if(negate){
            fieldValue  = '-' + fieldValue;
        }
        queryObj[fieldName] = fieldValue;
        return queryObj;
    },

    /**
     * Construct the query object for a number of objects based on the operators.
     *
     * Note: the length of operators is always 1 less than the length of objects.
     *
     * @param {Array} items The objects array.
     * @param {Array} operators The operators array. The element is either "and" or "or".
     * @return {Object}
     * @private
     */
    _createLogicalObject: function (items, operators) {
        var logicalObj = {};
        var i;
        var orIndices = [];
        var andIndices = [];
        for (i = 0; i < operators.length; i++) {
            if (operators[i] === 'and') {
                andIndices.push(i);
            } else if (operators[i] === 'or') {
                orIndices.push(i);
            }
        }
        if (orIndices.length === 0) {
            logicalObj.all = items;
        } else if (andIndices.length === 0) {
            logicalObj.any = items;
        } else {
            logicalObj.any = [];
            var andSegment = {
                all: []
            };
            var andItemIndices = [];
            for (i = 0; i < andIndices.length; i++) {
                andItemIndices.push(andIndices[i]);
                if (i === andIndices.length - 1) {
                    andItemIndices.push(andIndices[i] + 1);
                } else if (andIndices[i + 1] - andIndices[i] > 1) {
                    andItemIndices.push(andIndices[i] + 1);
                }
            }
            var lastAndIndex = -1;
            for (i = 0; i < items.length; i++) {
                if (andItemIndices.indexOf(i) >= 0) {
                    if (lastAndIndex > 0 && (i - lastAndIndex > 1 || orIndices.indexOf(lastAndIndex) >= 0)) {
                        logicalObj.any.push(andSegment);
                        andSegment = {
                            all: []
                        };
                    }
                    andSegment.all.push(items[i]);
                    lastAndIndex = i;
                } else {
                    logicalObj.any.push(items[i]);
                }
            }
            if (andSegment.all.length > 0) {
                logicalObj.any.push(andSegment);
            }
        }
        return logicalObj;
    },

    /**
     * Merge the properties from one object to another.
     *
     * @param {Object} hostObj
     * @param {Object} guestObj
     * @return {Object}
     * @private
     */
    _mergeQueryObject: function (hostObj, guestObj) {
        var key;
        for (key in guestObj) {
            if (guestObj.hasOwnProperty(key) && !hostObj.hasOwnProperty(key)) {
                hostObj[key] = guestObj[key];
            }
        }
        return hostObj;
    },

    /**
     * Refresh the container height to fit its contents.
     */
    refreshContainerHeight: function () {
        var contentHeight = this.element.find('.ui-heurist-header').outerHeight() + this.element.find('fieldset').outerHeight();
        this.element.parent().height(contentHeight);
    },

    /**
     * Refresh the container width to adapt the options pane.
     */
    refreshContainerWidth: function () {
        this.element.parent().width(this.options.options_pane_expanded ? 930 : 800);
    },

    /**
     * Expand the options pane.
     */
    expandOptionsPane: function () {
        this.options.options_pane_expanded = true;
        this.element.parent().width(930);
        var $dlg = this.element.children('fieldset');
        $dlg.find('.search_option_switch').html(window.hWin.HR('Options <<'));
        $dlg.find('.search_field_item').each(function () {
            if ($(this).data('field-type') === 'common' || $(this).data('field-type') === 'numeric') {
                $(this).find('.search_field_option').show();
            }
        });
    },

    /**
     * Collapse the options pane.
     */
    collapseOptionsPane: function () {
        this.options.options_pane_expanded = false;
        this.element.parent().width(800);
        var $dlg = this.element.children('fieldset');
        $dlg.find('.search_option_switch').html(window.hWin.HR('Options >>'));
        $dlg.find('.search_field_option').hide();
    },

    /**
     * Toggle the options pane.
     */
    toggleOptionsPane: function () {
        if (this.options.options_pane_expanded) {
            this.collapseOptionsPane();
        } else {
            this.expandOptionsPane();
        }
    },

    /**
     * Add a set of new field value controls in the UI.
     *
     * @param {Object} addBtnElement The DOM element of the field value add button.
     */
    addFieldValueControls: function (addBtnElement) {
        var fieldValuesParentElement = $(addBtnElement).closest('.search_field_common');

        if (fieldValuesParentElement) {
            var fieldValueMode = fieldValuesParentElement.parent().data('field-type');
            if (fieldValueMode === 'common' || fieldValueMode === 'numeric' || fieldValueMode === 'enumeration') {
                var addFieldValueElement = $($('#templateFieldValue').html());
                fieldValuesParentElement.append(addFieldValueElement);

                if (fieldValueMode === 'common' || fieldValueMode === 'numeric') {
                    addFieldValueElement.find('.sa_termvalue').hide();
                    this._populateFieldValueOptionsList(addFieldValueElement.find('.sa_fieldvalue_option'));
                } else {
                    addFieldValueElement.find('.sa_fieldvalue').hide();
                    this._populateTermList(addFieldValueElement.find('.sa_termvalue')[0]);
                }
                if (!this.options.options_pane_expanded) {
                    addFieldValueElement.find('.search_field_option').hide();
                }
                this.refreshContainerHeight();
            }
        }
    },

    /**
     * Add a new set of field controls in the UI.
     *
     * @param {Object} addBtnElement The DOM element of the field add button.
     */
    addFieldControls: function (addBtnElement) {
        var that = this;
        var parentElement = $(addBtnElement).closest('.search_field_section').children('.search_field_set');
        if (parentElement) {
            var addElement = $($('#templateField').html());
            parentElement.append(addElement);
            this._populateFieldList(addElement.find('.sa_fieldtype')[0]);
            this._setFieldValueControlsToCommonMode(addElement.find('.sa_fieldtype')[0]);
            this._on(addElement.find('.search_field_value_add'), {
                click: function (event) {
                    that.addFieldValueControls(event.target);
                }
            });
            that._on(addElement.find('.sa_fieldtype'), {
                change: that._onFieldTypeChangeHandler()
            });
            this.refreshContainerHeight();
        }
    },

    /**
     * Add a new set of sort controls in the UI.
     *
     * @param {Object} addBtnElement The DOM element of the sort add button.
     */
    addSortControls: function (addBtnElement) {
        var parentElement = $(addBtnElement).closest('.search_sort_section').find('.search_sort_item .search_layout_grid');
        if (parentElement) {
            var addElement = $($('#templateSortField').html());
            parentElement.append(addElement);
            this._populateSortbyList(addElement.find('.sa_sortby')[0]);
            this.refreshContainerHeight();
        }
    },
    
    //
    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        //$(window.hWin.document).off(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
        window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);        
        
        //this.div_entity_btns.remove();
    },

    /**
     * Show the query string in UI.
     */
    getQueryString: function(){
        
        this.calcShowSimpleSearch();
        
        var req = {q:this.options.is_json_query ?this.current_query_json :this.current_query};
        
        window.hWin.HEURIST4.util.hQueryCopyPopup(req, {my:'middle bottom', at:'middle top', of:this.search_get_query});

    },

    /**
     * The action function when do the search.
     */
    doAction: function(){
        
        this.calcShowSimpleSearch();
        var request = {};
            request.q = this.options.is_json_query ?this.current_query_json :this.current_query;
            request.w  = 'a';
            request.detail = 'ids';
            request.source = this.element.attr('id');
            request.search_realm = this.options.search_realm;
            
            window.hWin.HAPI4.SearchMgr.doSearch( this, request );
        
        this.closeDialog();
    },
    
    outerHeight: function(callback){
        
        var fs = this.element.children('fieldset');
        var eles = fs.children();
        if(!fs.is(':visible') || eles.length==0){
            var that = this;
            setTimeout(function(){that.outerHeight(callback)},500);
            return 246;
        }
        var h = fs.outerHeight();
        if(h<20){
            $.each(eles,function(i,ele){ h = h + $(ele).outerHeight();});
        }
        
        h = h + this.element.children('div.ui-heurist-header').outerHeight();
        
        callback.call(this, h);

        return h;
    }
    
});
