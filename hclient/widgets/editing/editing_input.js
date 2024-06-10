/**
* Widget for input controls on edit form
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
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
import "./editInputBlocktext.js";
import "./editInputGeo.js";
import "./editInputDate.js";
import "./editInputRecFile.js";
import "./editInputFile.js";

$.widget( "heurist.editing_input", {

    // default options
    options: {
        varid:null,  //id to create imput id, otherwise it is combination of index and detailtype id
        recID: null,  //record id for current record - required for relation marker and file
        recordset:null, //reference to parent recordset 
        editing:null, //reference to parent editing form

        rectypeID: null, //only for recDetail to get field description from $Db.rst
        dtID: null,      // field type id (for recDetails) or field name (for other Entities)

        //  it is either from window.hWin.HEURIST4.rectype.typedefs.dtFields - retrieved with help rectypeID and dtID
        // object with some mandatory field names
        dtFields: null,

        values: null,
        readonly: false,
        title: '',  //label (overwrite Display label from record structure)
        suppress_repeat: false, //true,false or 'force_repeat'
        showclear_button: true,
        showedit_button: true,
        show_header: true, //show/hide label
        suppress_prompts:false, //supress help, error and required features
        useHtmlSelect: false, //NOTE !!!! native select produce double space for option  Chrome touch screen
        detailtype: null,  //overwrite detail type from db (for example freetext instead of memo)
        
        change: null,  //onchange callback
        onrecreate: null, //onrefresh callback
        is_insert_mode:false,
        
        is_faceted_search:false, //is input used in faceted search or filter builder
        is_between_mode:false,    //duplicate input for freetext and dates for search mode 

        language: null, // language for term values (3 character ISO639-2 code)

        force_displayheight: null // for textareas height in rows
    },

    dtwidget:null, //widget name
    
    //newvalues:{},  //keep actual value for resource (recid) and file (ulfID)
    detailType:null,
    configMode:null, //configuration settings, mostly for enum and resource types (from field rst_FieldConfig)
    customClasses:null, //custom classes to manipulate visibility and styles in editing
       
    isFileForRecord:false,

    is_disabled: false,
    new_value: '', // value for new input

    selObj: null, //shared selector for all enum field values
    child_terms: null, // array of child terms for current vocabulary 
    enum_buttons:null, // null = dropdown/selectmenu/none, radio or checkbox
    _enumsHasImages: false, 
    
    is_sortable: false, // values are sortable

    block_editing: false,

    _external_relmarker: {
        target: null,
        relation: null,
        callback: null
    }, // pre-select a record target, possible relation type and setup a callback for relmarkers handled from external lookup

    // the constructor
    _create: function() {

        //for recDetails field description can be taken from $Db.rst
        if(this.options.dtFields==null && this.options.dtID>0 && this.options.rectypeID>0) //only for recDetails
        {
            this.options.dtFields = window.hWin.HEURIST4.util.cloneJSON($Db.rst(this.options.rectypeID, this.options.dtID));
        
            //field can be removed from rst - however it is still in faceted search
            if(this.options.dtFields==null){
               this.options.dtFields = {}; 
            }
            
            if(this.options.is_faceted_search){
                
                if(window.hWin.HEURIST4.util.isempty(this.options['dtFields']['rst_FilteredJsonTermIDTree'])){
                    this.options['dtFields']['rst_FilteredJsonTermIDTree'] = $Db.dty(this.options.dtID,'dty_JsonTermIDTree');
                } 
                if(window.hWin.HEURIST4.util.isempty(this.options['dtFields']['rst_PtrFilteredIDs'])){
                    this.options['dtFields']['rst_PtrFilteredIDs'] = $Db.dty(this.options.dtID,'dty_PtrTargetRectypeIDs');
                }
                this.options['dtFields']['rst_DefaultValue'] = '';
                this.options['dtFields']['rst_PointerMode'] = 'browseonly';
            }

        }

        if(window.hWin.HAPI4.sysinfo['dbconst']['DT_TIMELINE_FIELDS'] &&
           this.options.dtID==window.hWin.HAPI4.sysinfo['dbconst']['DT_TIMELINE_FIELDS']){

            this.options.detailtype = 'resource';
            this.options['dtFields']['rst_FieldConfig']= {entity:'DefDetailTypes',csv:true};
        }

        
        if(this.options.dtFields==null){ //field description is not defined
            return;
        }
        
        if(this.options.suppress_repeat=='force_repeat'){
            this.options['dtFields']['rst_MaxValues'] = 100;
            this.options.suppress_repeat = false;
        }
        
        this.detailType = this.options.detailtype ?this.options.detailtype :this.f('dty_Type');
        
        if((!(this.options.rectypeID>0)) && this.options.recordset){ //detect rectype for (heurist data) Records/recDetails
            this.options.rectypeID = this.options.recordset.fld(this.options.recID, 'rec_RecTypeID'); //this.options.recordset.getFirstRecord()
        }
        
        //custom classes to manipulate visibility and styles in editing space separated
        this.customClasses = this.f('rst_Class'); 
        if(!window.hWin.HEURIST4.util.isempty(this.customClasses)){
            this.element.addClass(this.customClasses);
        }
        
        //configuration settings, mostly for enum and resource types (from field rst_FieldConfig)
        this.configMode = this.f('rst_FieldConfig');
        if(!window.hWin.HEURIST4.util.isempty(this.configMode)){
            this.configMode = window.hWin.HEURIST4.util.isJSON(this.configMode);
            if(this.configMode===false) this.configMode = null;
        }
        //by default
        if((this.detailType=="resource" || this.detailType=='file') 
            && window.hWin.HEURIST4.util.isempty(this.configMode))
        {
            this.configMode= {entity:'records'};
        }

        this.isFileForRecord = (this.detailType=='file' && this.configMode.entity=='records');
        if(this.isFileForRecord){
            this.configMode = {
                    entity:'recUploadedFiles',
            };
        }
        
        this.options.showclear_button = this.options.showclear_button && !this.isReadonly();

        var that = this;

        var required = "";
        if(this.isReadonly()) {
            required = "readonly";
        }else{
            if(!this.options.suppress_prompts && this.f('rst_Display')!='hidden'){
                required = this.f('rst_RequirementType');
            }
        }
        
        var lblTitle = (window.hWin.HEURIST4.util.isempty(this.options.title)?this.f('rst_DisplayName'):this.options.title);

        //header
        if(true){ // || this.options.show_header
            this.header = $( "<div>")
            .addClass('header '+required)
            //.css('width','150px')
            .css({'vertical-align':'top'})  //, 'line-height':'initial'
            .html('<label>' + lblTitle + '</label>')
            .appendTo( this.element );

            // Apply user pref font size
            var usr_font_size = window.hWin.HAPI4.get_prefs_def('userFontSize', 0);
            if(usr_font_size != 0){
                usr_font_size = (usr_font_size < 8) ? 8 : (usr_font_size > 18) ? 18 : usr_font_size;
                this.header.css('font-size', usr_font_size+'px');
            }
        }
        
        this.is_sortable = false;
       
        //repeat button        
        if(this.isReadonly()) {

            //spacer
            $( "<span>")
            .addClass('editint-inout-repeat-button')
            .css({'min-width':'40px', display:'table-cell'})
            .appendTo( this.element );

        }else{

            //hardcoded list of fields and record types where multivalues mean translation (multilang support)
            var is_translation = this.f('rst_MultiLang') || 
               ((that.options.rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'] ||
                that.options.rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'])
                && that.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME']);
            
            //saw TODO this really needs to check many exist
            var repeatable = (Number(this.f('rst_MaxValues')) != 1  || is_translation)? true : false;
            
            if(!repeatable || this.options.suppress_repeat){  
                //spacer
                $( "<span>")
                .addClass('editint-inout-repeat-button editint-inout-repeat-container')
                .css({'min-width':'40px', display:'table-cell'})
                .appendTo( this.element );
                
            }else{ //multiplier button
            
                this.is_sortable = !that.is_disabled && !that.isReadonly() 
                        && (this.detailType!="relmarker") && !that.options.is_faceted_search; 
            
                var btn_cont = $('<span>', {class: 'editint-inout-repeat-container'})
                    .css({display:'table-cell', 'vertical-align':'top', //'padding-top':'2px',
                            'min-width':'22px',  'border-color':'transparent'})
                    .appendTo( this.element );

                //translation for text field only    
                let rec_translate = this.options.recordset && this.options.recordset.entityName == 'Records' && !is_translation
                                    && (this.detailType == 'freetext' || this.detailType == 'blocktext');

                let styles = {
                    display:'block', 
                    'font-size': (is_translation?'1em':'1.9em'), cursor:'pointer', 
                    //'vertical-align':'top', //'padding-top':'2px',
                    'min-width':(rec_translate ? '16px' : '22px'),
                    'margin-top': '5px',
                    //outline_suppress does not work - so list all these props here explicitely
                    outline: 'none','outline-style':'none', 'box-shadow':'none'
                }
                    
                this.btn_add = $( "<span>")
                    .addClass('smallbutton editint-inout-repeat-button ui-icon ui-icon-'
                        +(is_translation?'translate':'circlesmall-plus'))
                    .appendTo( btn_cont )
                //.button({icon:"ui-icon-circlesmall-plus", showLabel:false, label:'Add another ' + lblTitle +' value'})
                .attr('tabindex', '-1')
                .attr('title', 'Add another ' + window.hWin.HEURIST4.util.stripTags(lblTitle) +(is_translation?' translation':' value' ))                    
                .css(styles);

                if(rec_translate){ // add translate icon

                    styles['font-size'] = '1.1em';
                    styles['float'] = 'left';

                    $('<span>')
                        .addClass('smallbutton editint-inout-repeat-button ui-icon ui-icon-translate')
                        .attr('tabindex', '-1')
                        .attr('title', 'Add another translation')
                        .css(styles)
                        .prependTo(btn_cont);

                    this.btn_add = btn_cont.find('span');
                }

                if(this.detailType=="blocktext"){
                    this.btn_add.css({'margin-top':'3px'});    
                }
                
                //this.btn_add.find('span.ui-icon').css({'font-size':'2em'});
                
                // bind click events
                this._on( this.btn_add, {
                    click: function(event){

                        if(this.is_disabled) return;

                        if(is_translation){

                            if(typeof translationSupport!=='undefined' && window.hWin.HUL.isFunction(translationSupport)){
                                translationSupport(this); //see editing_exts
                            }
                            
                        }else if($(event.target).hasClass('ui-icon-translate') && (that.detailType == 'freetext' || that.detailType == 'blocktext')){ // request language, then create new input with language prefix

                            let $dlg;
                            let msg = 'Language: <select id="selLang"></select><br>';

                            let first_val = that.inputs.length > 0 ? that.inputs[0].val() : '';

                            let btns = {};

                            let labels = {
                                title: window.HR('Insert translated value')
                            };

                            if(!window.hWin.HEURIST4.util.isempty(first_val) && window.hWin.HAPI4.sysinfo.api_Translator){ // allow external API translations

                                msg += '<span style="display:inline-block;margin-top:10px;">Translate will translate the first value</span>';

                                btns[window.HR('Translate')] = function(){

                                    let source = '';
                                    let target = $dlg.find('#selLang').val();
    
                                    if(first_val.match(/^\w{3}:/)){ // check for a source language
    
                                        // Pass as source language
                                        source = first_val.match(/^\w{3}:/)[0];
                                        source = source.slice(0, -1);
    
                                        first_val = first_val.slice(4); // remove lang prefix
                                    }
    
                                    let request = {
                                        a: 'translate_string',
                                        string: first_val,
                                        target: target,
                                        source: source
                                    };

                                    window.hWin.HAPI4.SystemMgr.translate_string(request, function(response){

                                        $dlg.dialog('close');

                                        if(response.status == window.hWin.ResponseStatus.OK){
                                            that.new_value = target + ':' + response.data;
                                            $(that.btn_add[1]).trigger('click'); // 'click' normal repeat
                                            that.onChange(); // trigger change
                                        }else{
                                            window.hWin.HEURIST4.msg.showMsgErr(response);
                                        }
                                    });
                                };

                                labels['ok'] = window.HR('Translate');
                            }else{
                                msg += '<span style="display:inline-block;margin-top:10px;">'
                                        + 'To enable automatic translation please ask your system administrator to<br>'
                                        + 'add a Deepl free or paid account API key to Heurist configuration'
                                     + '</span>';
                            }

                            btns[window.HR('Insert blank')] = function(){
                                that.new_value = $dlg.find('#selLang').val() + ':';
                                $dlg.dialog('close');
                                $(that.btn_add[1]).trigger('click'); // 'click' normal repeat
                            };
                            labels['yes'] = window.HR('Insert blank');

                            btns[window.HR('Cancel')] = function(){
                                $dlg.dialog('close');
                            };
                            labels['cancel'] = window.HR('Cancel');

                            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, labels, {default_palette_class: 'ui-heurist-populate'});

                            window.hWin.HEURIST4.ui.createLanguageSelect($dlg.find('#selLang'), null, null, true);

                            $dlg.parent().find('.ui-dialog-buttonpane button').css({
                                'margin-left': '10px', 'margin-right': '10px'
                            });

                        }else{
                            
                            if(window.hWin.HEURIST4.util.isempty(this.new_value) && this.new_value != '') this.new_value = '';

                            if( !(Number(this.f('rst_MaxValues'))>0)  || this.inputs.length < this.f('rst_MaxValues')){
                                this._addInput(this.new_value);
                                this._refresh();
                                
                                if(window.hWin.HUL.isFunction(this.options.onrecreate)){
                                    this.options.onrecreate.call(this);
                                }
                            }
                            
                        }
                }});
            }
            
            
            if(this.options.dtID != 'rst_DefaultValue_resource'){
                if(this.detailType=="resource" && this.configMode.entity=='records'){
                    
                    $('<div style="float:right;padding-top:1px;width: 14px;"><span class="ui-icon ui-icon-triangle-1-e"></span></div>')                
                        .appendTo( this.header );
                        this.header.css({'padding-right':0, width:154});
                        this.header.find('label').css({display:'inline-block', width: 135});
                        
                }else if(this.detailType=="relmarker"){
                    
                    $('<div style="float:right;padding-top:1px;width: 14px;"><span style="font-size:11px" class="ui-icon ui-icon-triangle-2-e-w"></span></div>')                
                        .appendTo( this.header )
                        this.header.css({'padding-right':0, width:154});
                        this.header.find('label').css({display:'inline-block', width: 135});
                }
            }
        }


        //input cell
        this.input_cell = $( "<div>")
                            .addClass('input-cell')
                            .appendTo( this.element );
        if(this.is_sortable){

            this.input_cell.sortable({
                //containment: "parent",
                delay: 250,
                items: '.input-div',
                axis: 'y',
                stop:function(event, ui){
                    
                    var isparententity = (that.f('rst_CreateChildIfRecPtr')==1);
                    if(isparententity){ //remove parent entity flag to avoid autosave
                        that.fset('rst_CreateChildIfRecPtr', 0);
                    }
                    
                    //reorganize
                    that.isChanged(true);
                    that.onChange();
                    that.btn_cancel_reorder.show();
                    
                    if(isparententity){//restore parent entity flag
                        that.fset('rst_CreateChildIfRecPtr', 1);
                    }
                    
                }});            
                
                
            $('<br>').appendTo( this.header );
            this.btn_cancel_reorder = $("<div title='Cancel reorder'>")
                .appendTo( this.header ).hide()
                .css({'padding':'1px', 'margin-top':'4px', 'font-size':'0.7em', width: '40px', float: 'right'})
                .button({label:'Cancel'});
            this._on( this.btn_cancel_reorder, {
                click: this._restoreOrder} );
        } 
        
        //add hidden error message div
        this.firstdiv = $( "<div>").hide().appendTo( this.input_cell );
        
        this.error_message = $( "<div>")
        .hide()
        .addClass('heurist-prompt ui-state-error')
        .css({'height': 'auto',
            'width': 'fit-content',
            'padding': '0.2em',
            'border': 0,
            'margin-bottom': '0.2em'})
        .appendTo( this.input_cell );

        //add prompt/help text
        var help_text = window.hWin.HEURIST4.ui.getRidGarbageHelp(this.f('rst_DisplayHelpText'));
        
        this.input_prompt = $( "<div>")
            .html( help_text && !this.options.suppress_prompts ?help_text:'' )
            .addClass('heurist-helper1').css('padding','0.2em 0');
        this.input_prompt.appendTo( this.input_cell );

        // Add extended description, if available, viewable via clicking more... and collapsible with less...
        var extend_help_text = window.hWin.HEURIST4.util.htmlEscape(this.f('rst_DisplayExtendedDescription'));
        if(help_text && !this.options.suppress_prompts 
            && extend_help_text && this.options.recordset && this.options.recordset.entityName == 'Records'){

            var $extend_help_eles = $("<span id='show_extended' style='color:blue;cursor:pointer;'> more...</span>"
                + "<span id='extended_help' style='display:none;font-style:italic;'><br>"+ extend_help_text +"</span>"
                + "<span id='hide_extended' style='display:none;color:blue;cursor:pointer;'> less...</span>")
                .appendTo(this.input_prompt);

            // Toggle extended description
            this._on($extend_help_eles, {
                'click': function(event){
                    $extend_help_eles.toggle()
                }
            });
        }

        //values are not defined - assign default value
        var values_to_set;
        
        if( !Array.isArray(this.options.values) ){
            var def_value = this.f('rst_DefaultValue');
            
            var isparententity = (this.f('rst_CreateChildIfRecPtr')==1);

            if( !this.options.is_insert_mode || window.hWin.HEURIST4.util.isempty(def_value) || isparententity){
                // reset default value - default value for new record only
                // do not assign default values in edit mode                
                values_to_set = [''];        
            }else if(Array.isArray(def_value)){
                //exclude duplication
                values_to_set = window.hWin.HEURIST4.util.uniqueArray(def_value);//.unique();
            }else{
                values_to_set = [def_value];
            }
            
            if(values_to_set=='increment_new_values_by_1'){
                
                   //find incremented value on server side
                   window.hWin.HAPI4.RecordMgr.increment(this.options.rectypeID, this.options.dtID, 
                     function(response){
                      if(!window.hWin.HEURIST4.util.isnull(response)){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                
                                that.setValue(response.result);
                                that.options.values = that.getValues();
                                that._refresh();
                                
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                      }
                  });
                  this.setValue(0);
                  this.options.values = [0]; //zero value
                  return;
            }
            
        }else {
            values_to_set = this.options.values; //window.hWin.HEURIST4.util.uniqueArray(this.options.values); //.slice();//.unique();
        }
        
        //recreate input elements and assign given values
        this.setValue(values_to_set);
        this.options.values = this.getValues();

        this._refresh();

        if(this.f('rst_MayModify') == 'discouraged'){ // && !window.hWin.HAPI4.is_admin()

            this.block_editing = true;

            that.setDisabled(true);

            this.input_cell.find('.ui-state-disabled').removeClass('ui-state-disabled'); // remove gray 'cover'

            if(this.input_cell.sortable('instance') !== undefined){ // disable sorting, if sortable
                this.input_cell.sortable('disable');
            }

            let $eles = this.element.find('.input-cell, .editint-inout-repeat-button');
            this._on($eles, {
                click: function(event){

                    if(!that.block_editing || $(event.target).hasClass('ui-icon-extlink')){
                        return;
                    }

                    window.hWin.HEURIST4.util.stopEvent(event);
                    event.preventDefault();

                    let msg = 'The designer of this database suggests that you do not edit the value in this field (<strong>'+ lblTitle +'</strong>)<br>'
                        + 'unless you are very sure of what you are doing.<br><br>'
                        + '<label><input type="checkbox" id="allow_edit"> Let me edit this value</label>';

                    let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, function(){

                        that.block_editing = false;
                        //that._off($eles, 'click'); - Also removes click from repeat button

                        that.setDisabled(false);
                        if(that.input_cell.sortable('instance') !== undefined){ // re-enable sorting inputs
                            that.input_cell.sortable('enable');
                        }
                    }, {title: 'Editing is discouraged', yes: 'Proceed', no: 'Cancel'}, {default_palette_class: 'ui-heurist-populate'});

                    window.hWin.HEURIST4.util.setDisabled($dlg.parent().find('.ui-dialog-buttonpane button:first-child'), true);
                    $dlg.find('#allow_edit').on('change', function(){
                        window.hWin.HEURIST4.util.setDisabled($dlg.parent().find('.ui-dialog-buttonpane button:first-child'), !$dlg.find('#allow_edit').is(':checked'));
                    });
                }
            });
        }else if(this.isReadonly()){
            this.input_cell.attr('title', 'This field has been marked as non-editable');
        }
    }, //end _create------------------------------------------------------------

    /* private function */
    _refresh: function(){
        if(this.f('rst_Display')=='hidden'){
            this.element.hide();    
        }else{
            this.element.show();    
        }
        
        if(this.options.showedit_button){
            this.element.find('.btn_add_term').css({'visibility':'visible','max-width':16});
        }else{
            this.element.find('.btn_add_term').css({'visibility':'hidden','max-width':0});
        }
        
        if(this.options.showclear_button){
            this.element.find('.btn_input_clear').css({'visibility':'visible','max-width':16});
        }else{
            this.element.find('.btn_input_clear').css({'visibility':'hidden','max-width':0});
        }
        
        this._setVisibilityStatus();
    
        if(this.options.show_header){
            if(this.header.css('display')=='none'){
                this.header.css('display','table-cell');//show();
            }
        }else{
            this.header.hide();
        }      
        
        //refresh filter for resourse popup 
        var val = this.f('rst_FieldConfig');
        if(!window.hWin.HEURIST4.util.isempty(val)){
            val = window.hWin.HEURIST4.util.isJSON(val);
            if(val!==false && this.configMode.entity){
                this.configMode.initial_filter = val.initial_filter;
                this.configMode.search_form_visible = val.search_form_visible;
            }
        }
    },
    
    _setOptions: function( ) {
        this._superApply( arguments );
        
        if(this.options.recreate===true){
            this.options.recreate = null;
            this.element.empty();
            this._destroy();
            this._create();
        }else{
            this._refresh();    
        }
    },

    //
    //
    //    
    addTooltip: function(id, tooltip){
        if(!this.tooltips) this.tooltips = {};
        this.tooltips[id] = tooltip; 
    },
    
    //
    //
    //
    removeTooltip: function(id){

        if(this.tooltips && this.tooltips[id]){
            var $tooltip = this.tooltips[id];
            if($tooltip && $tooltip.tooltip('instance') != undefined){
                $tooltip.tooltip('destroy');
                $tooltip = null;
            }
            this.tooltips[id] = null;
            delete this.tooltips[id];
        }
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        if(this.btn_add){
            this.btn_add.remove();
        }
        // remove generated elements
        if(this.imagelib_select_dialog){
            this.imagelib_select_dialog.remove();
        }
        if(this.header){
            this.header.remove();
        }
        this._off(this.element.find('span.field-visibility'), 'click');
        this._off(this.element.find('div.field-visibility2'), 'click');
        this.element.find('span.field-visibility').remove();
        this.element.find('div.field-visibility2').remove();
        
        var that = this;
        if(this.inputs){
            $.each(this.inputs, function(index, input){ 

                    that.removeTooltip(input.attr('id'));

                    if($(input).hSelect('instance')!==undefined) $(input).hSelect('destroy');
                    
                    //check for "between" input (date and freetext)
                    that.element.find('#'+$(input).attr('id')+'-2').remove();
                    
                    input.remove();
                    
                    
            } );
            this.input_cell.remove();
        }
        this.tooltips = {};
    },

    /**
    * get value for given record type structure field
    *
    * dtFields - json with parameters that describes this input field
    *            for recDetails it is taken from $Db.rst for other entities from config files in hserv/entities
    * 
    dty_Type,
    rst_DisplayName,  //label
    rst_DisplayHelpText  (over dty_HelpText)           //hint
    rst_DisplayExtendedDescription  (over dty_ExtendedDescription) //rollover

    rst_RequirementType,  //requirement
    rst_MaxValues     //repeatability

    rst_DisplayWidth - width in characters

    rst_PtrFilteredIDs (over dty_PtrTargetRectypeIDs)
    rst_FilteredJsonTermIDTree  (over dty_JsonTermIDTree)     
    
    rst_TermIDTreeNonSelectableIDs   
    dty_TermIDTreeNonSelectableIDs
    *
    *
    * @param fieldname
    */
    f: function(fieldname){

        var val = this.options['dtFields'][fieldname]; //try get by name
        
        if(window.hWin.HEURIST4.util.isnull(val) && this.options.dtID>0 && this.options.rectypeID>0){ //try get from $Db
            val = $Db.rst(this.options.rectypeID, this.options.dtID, fieldname);
        }
        if(window.hWin.HEURIST4.util.isempty(val)){ //some default values
            if(fieldname=='rst_RequirementType') val = 'optional'
            else if(fieldname=='rst_MaxValues') val = 1
            else if(fieldname=='dty_Type') val = 'freetext'
            else if(fieldname=='rst_DisplayHeight' && this.f('dty_Type')=='blocktext') 
                val = 8 //height in rows
            else if(fieldname=='rst_DisplayWidth'
                && (this.f('dty_Type')=='freetext' || this.f('dty_Type')=='url' || 
                    this.f('dty_Type')=='blocktext' || this.f('dty_Type')=='resource'))   
                        val = this.f('dty_Type')=='freetext'?20:80;  //default minimum width for input fields in ex
            else if(fieldname=='rst_TermsAsButtons')
                val = 0;
            else if(fieldname=='rst_Spinner')
                val = 0;
            else if(fieldname=='rst_SpinnerStep')
                val = 1;
        }
        if(window.hWin.HEURIST4.util.isempty(val)){
            return null;
        }else{
            return val;    
        }
        

    },
    
    //
    // assign parameter by fieldname
    //
    fset: function(fieldname, value){
        this.options['dtFields'][fieldname] = value;
    },

    //
    //
    //
    _removeInput: function(input_id){

        var that = this;

        this.removeTooltip(input_id);        

        if(this.inputs.length>1 && this.enum_buttons == null){

            //find in array
            $.each(this.inputs, function(idx, item){

                var $input = $(item);
                if($input.attr('id')==input_id){
                    if(that.newvalues[input_id]){
                        delete that.newvalues[input_id];
                    }
                    
                    if(that.detailType=='file'){
                        
                        var $parent = that._getInputDiv($input);
                        $input.remove();
                        $parent.remove();
                        
                        that.entity_image_already_uploaded = false;
                    }else{
                        if($input.hSelect('instance')!==undefined) $input.hSelect('destroy');

                        //remove element
                        that._getInputDiv($input).remove();
                    }
                    //remove from array
                    that.inputs.splice(idx,1);
                    that.onChange();
                    return;
                }

            });

        }
        else if(this.inputs.length >= 1 && this.enum_buttons == 'checkbox'){ // uncheck all checkboxes

            var $input;

            $(this.inputs[0]).val(''); // Set first value to empty

            if(this.inputs.length > 1){

                for (var i = 1; i < this.inputs.length; i++) {
                    
                    $input = $(this.inputs[i]);

                    this._off($input, 'change');

                    this._getInputDiv($input).remove();

                    that.inputs.splice(i,1);
                    i--;
                }
            }

            this._getInputDiv($(this.inputs[0]))
                .find('input[type="checkbox"]').prop('checked', false);

            that.onChange();
        }else{  
            //and clear last one
            this._clearValue(input_id, '');
            if(this.options.is_between_mode){
                this.newvalues[input_id+'-2'] = '';
                this.element.find('#'+input_id+'-2').val('');
            }
        }
        
    },
    
    //
    //
    //
    _setAutoWidth: function(){

        if(this.options.is_faceted_search) return;

        let units = this.options.recordset && this.options.recordset.entityName == 'Records' ? 'ch' : 'ex';
        let $parent_container = this.inputs.length > 0 ? $(this.inputs[0]).parents('.editForm.recordEditor') : [];

        //auto width
        if ( this.detailType=='freetext' || this.detailType=='integer' || 
             this.detailType=='float' || this.detailType=='url' || this.detailType=='file'){

            let max_w = $parent_container.length > 0 ? $parent_container.width() - 280 : 600;
            max_w = Math.floor(!max_w || max_w <= 0 ? 600 : max_w);
            
            var that = this;
            this.inputs.forEach(function(input){

                input = $(input);

                let ow;

                if(that.dtwidget!=null && input[that.dtwidget]('instance')){
                    input[that.dtwidget]('setAutoWidth', units, max_w);
                }else{
                    let ow = input.width(); // current width

                    if(Math.ceil(ow) < max_w){

                        let nw = `${input.val().length+3}${units}`;
                        $(input).css('width', nw);

                        if(input.width() < ow) input.width(ow); // we can only increase - restore
                        else if(input.width() > max_w) input.width(max_w); // set to max
                    }
                }
            });
        }
        
    },
    
    //
    // returns max width for input element
    //
    getInputWidth: function(){
        
        if(this.detailType=='file' && this.configMode.use_assets){
            return 300;
        }
        
        var maxW = 0;
        $.each(this.inputs, function(index, input){ 
            maxW = Math.max(maxW, $(input).width());
        });
        return maxW;
    },
   
    //
    //
    //
    onChange: function(event){
    
        let repeatable = (Number(this.f('rst_MaxValues')) != 1); 
        if(this.options.values && this.options.values.length>1 && !repeatable && this.f('rst_MultiLang')!=1){
            this.showErrorMsg('Repeated value for a single value field - please correct');
        }else{
            this.showErrorMsg(null);
        }
        
        this._setAutoWidth();
        
        if(window.hWin.HUL.isFunction(this.options.change)){
            this.options.change.call( this );    
        }
    },

    /**
    * add input according field type
    *
    * @param value
    * @param idx - index for repetative values
    */
    _addInput: function(value) {

        if(!this.inputs){//init
            this.inputs = [];
            this.newvalues = {};
        }

        var that = this;

        var $input = null;
        //@todo check faceted search!!!!! var inputid = 'input'+(this.options.varid?this.options.varid :idx+'_'+this.options.dtID);
        //repalce to uniqueId() if need
        value = window.hWin.HEURIST4.util.isnull(value)?'':value;

        var $inputdiv = $( "<div>" ).addClass('input-div').insertBefore(this.error_message); //was this.input_prompt

        // Apply user pref font size
        var usr_font_size = window.hWin.HAPI4.get_prefs_def('userFontSize', 0);
        if(usr_font_size != 0){
            usr_font_size = (usr_font_size < 8) ? 8 : (usr_font_size > 18) ? 18 : usr_font_size;
            $inputdiv.css('font-size', usr_font_size+'px');
        }

        if(this.detailType=='blocktext'){//----------------------------------------------------

            this.dtwidget = 'editInputBlocktext';
            $input = $inputdiv.editInputBlocktext({ container:this, value:value });

        }
        // || this.options.dtID=='tag'
        else 
        if(this.detailType=='enum' || this.detailType=='relationtype'){//--------------------------------------

            var dwidth;
            if(this.configMode && this.configMode.entity!='records'){
                dwidth = this.f('rst_DisplayWidth');
                if(parseFloat(dwidth)>0){
                    dwidth = dwidth+'ex';
                }
            }

            $input = $('<select>').uniqueId()
                .addClass('text ui-widget-content ui-corner-all')
                .css('width',(dwidth && dwidth!='0')?dwidth:'0px')
                .val(value)
                .appendTo( $inputdiv );
            
            if(this.options.dtID=='access'){
                var sel_options = [
                    {key: '', title: ''}, 
                    {key: 'viewable', title: 'viewable'}, 
                    {key: 'hidden', title: 'hidden'}, 
                    {key: 'public', title: 'public'}, 
                    {key: 'pending', title: 'pending'}
                ];

                window.hWin.HEURIST4.ui.createSelector($input.get(0), sel_options);
                window.hWin.HEURIST4.ui.initHSelect($input, false);
            }
            else if(this.options.dtID=='tag'){
                var groups = [];
                var req = {};
                req['a'] = 'search';
                req['details'] = 'name'; // Get group id and name
                req['entity'] = 'sysGroups';
                req['request_id'] = window.hWin.HEURIST4.util.random();

                /* Retrieve List of User Groups, mostly the names for displaying */
                window.hWin.HAPI4.EntityMgr.doRequest(req, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            var recset = new hRecordSet(response.data);
                            if(recset.length()>0){
                                recset.each2(function(id, val){
                                    groups.push([val['ugr_ID'], val['ugr_Name']]);
                                });
                            }
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );

                var sel_options = [];
                var u_id = window.hWin.HAPI4.currentUser['ugr_ID'];

                req = {};
                req['a'] = 'search';
                req['details'] = 'name'; // Get tag id, name, and gorup id
                req['entity'] = 'usrTags';
                req['sort:tag_Text'] = 2; // Order tags by tag name
                req['request_id'] = window.hWin.HEURIST4.util.random();

                /* Retrieve Tags */
                window.hWin.HAPI4.EntityMgr.doRequest(req, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            var gIDs = [];
                            var recset = new hRecordSet(response.data);
                            if(recset.length()>0){
                                records = recset.getSubSetByRequest({'sort:tag_UGrpID':1});
                                
                                u_tags = records.getSubSetByRequest({'tag_UGrpID':'='+u_id});
                                u_tags.each2(function(id, val){ // Get User Tags first
                                    var tag_name = filter_val = val['tag_Text'];
                                    var tag_group = val['tag_UGrpID'];

                                    var values = {};
                                    values['key'] = filter_val;
                                    values['title'] = tag_name;

                                    sel_options.push(values);
                                });  

                                w_tags = records.getSubSetByRequest({'tag_UGrpID':'!='+u_id});
                                w_tags.each2(function(id, val){ // Get Workgroup Tags second
                                    var tag_name = filter_val = val['tag_Text'];
                                    var tag_group = val['tag_UGrpID'];

                                    for(var i=0; i<groups.length; i++){
                                        if(groups[i][0] == tag_group){
                                            tag_name = groups[i][1] + '.' + tag_name;
                                        }
                                    }

                                    var values = {};
                                    values['key'] = filter_val;
                                    values['title'] = tag_name;

                                    sel_options.push(values);
                                });
                                window.hWin.HEURIST4.ui.createSelector($input.get(0), sel_options);
                                window.hWin.HEURIST4.ui.initHSelect($input, false);
                            }else{ // No Tags Found
                                window.hWin.HEURIST4.ui.createSelector($input.get(0), [{key: '', title: 'No Tags Found'}]);
                            }
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
            }
            else{
                
                if(window.hWin.HEURIST4.util.isempty(this.f('rst_FieldConfig'))){

                    browseTerms(this, $input, value);

                    //window.hWin.HEURIST4.ui.initHSelect($input, false);
                    
                    $input.hSelect({
                        'open': (e) => {
                            window.hWin.HEURIST4.util.stopEvent(e);
                            e.preventDefault();

                            $input.trigger('click');
                            $input.hSelect('close');
                        }
                    });

                    $input.hSelect('widget').css({
                        'width': 'auto',
                        'min-width': '14em'
                    });

                }else{
                    $input = this._recreateSelector($input, value); //initial create
                }
            }
            $input = $($input);
            
            this._on( $input, {change:this._onTermChange} );
            
            var allTerms = this.f('rst_FieldConfig');    
            
            if($.isPlainObject(allTerms)){
                this.options.showclear_button = this.options.showclear_button && (allTerms.hideclear!=1);
            }
            
            //allow edit terms only for true defTerms enum and if not DT_RELATION_TYPE
            if(window.hWin.HEURIST4.util.isempty(allTerms)) {
                allTerms = this.f('rst_FilteredJsonTermIDTree');

                if (!(window.hWin.HEURIST4.util.isempty(allTerms) && 
                    this.options.dtID==window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'])) {

                    var isVocabulary = !isNaN(Number(allTerms)); 

                    var $btn_termsel = $( '<span>', {title: 'Select Term By Picture'})
                    .addClass('smallicon ui-icon ui-icon-image show-onhover')
                    .css({
                        'margin-top': '2px',
                        'cursor': 'pointer'
                    })
                    .appendTo( $inputdiv )
                    .hide();
                    
                    if(that.child_terms==null){
                        
                        var vocab_id = that.f('rst_FilteredJsonTermIDTree');    
                        that.child_terms = $Db.trm_TreeData(vocab_id, 'set');
                        
                        that._checkTermsWithImages(); //show hide $btn_termsel
                    }else if(that._enumsHasImages){
                        $btn_termsel.show();
                    }

                    this._on( $btn_termsel, { click: function(){

                        var vocab_id = Number(this.f('rst_FilteredJsonTermIDTree'));    
                        
                        if(this.is_disabled || !(vocab_id>0)) return;

                            var selectmode = that.enum_buttons == 'checkbox' ? 'select_multi' : 'select_single';
                            var dlg_title = 'Term selection for ' + that.f('rst_DisplayName');

                            window.hWin.HEURIST4.ui.showEntityDialog('defTerms', {
                                empty_remark: 'No terms available',
                                title: dlg_title,
                                hide_searchForm: true,
                                select_mode: selectmode, 
                                view_mode: 'icons',
                                initial_filter: vocab_id,
                                default_palette_class: 'ui-heurist-populate',
                                onselect:function(event, data){
                                    if(data && data.selection && data.selection.length > 0){

                                        if(selectmode == 'select_multi'){
                                            that.setValue(data.selection, false);
                                        }else{
                                            browseTerms(that, $input, data.selection[0]);                                    
                                        }
                                        that.onChange();
                                    }
                                }
                            });
                                                                    
                    }});

                    var vocab_id = Number(allTerms);

                    if(window.hWin.HAPI4.is_admin()){            
                        
                        var $btn_termedit2 = $( '<span>', {title: 'Edit term tree'})
                        .addClass('smallicon ui-icon ui-icon-gear btn_add_term show-onhover')
                        .css({'margin-top':'2px',cursor:'pointer'})
                        .appendTo( $inputdiv );
                        
                        this._on( $btn_termedit2,{ click: function(){ this._openManageTerms(vocab_id); }});
                            
                    }
                
                    if(!window.hWin.HAPI4.is_guest_user()){
                    
                    var $btn_termedit = $( '<span>', {title: 'Add new term to this list'})
                    .addClass('smallicon ui-icon ui-icon-plus btn_add_term show-onhover')
                    .css({'margin-top':'2px',cursor:'pointer','font-size':'11px'})
                    .appendTo( $inputdiv );

                    //
                    // open add term popup
                    //
                    this._on( $btn_termedit, { click: function(){
                        
                    if(this.is_disabled) return;
                    
                    //add new term to specified vocabulary
                    var rg_options = {
                            isdialog: true, 
                            select_mode: 'manager',
                            edit_mode: 'editonly',
                            height: 240,
                            rec_ID: -1,
                            trm_VocabularyID: vocab_id,
                            onClose: function(){
                                that._recreateEnumField(vocab_id);
                            }
                        };

                        window.hWin.HEURIST4.ui.showEntityDialog('defTerms', rg_options); // it recreates  

                        return;
                    }} ); //end btn onclick
                    
                    }//not guest user                    
                }
            }//allow edit terms only for true defTerms enum
            
            // Display term selector as radio buttons/checkboxes
            var asButtons = this.options.recordset && this.options.recordset.entityName=='Records' && this.f('rst_TermsAsButtons') == 1;
            if(asButtons && this.child_terms  && this.child_terms.length<=20){

                    this.enum_buttons = (Number(this.f('rst_MaxValues')) != 1) ? 'checkbox' : 'radio';
                    var inpt_id = $input.attr('id');
                    var dtb_res = false;

                    if(this.enum_buttons == 'checkbox' && $inputdiv.parent().find('input:checkbox').length > 0){ // Multi value, check if checkboxes exist

                        $inputdiv.parent().find('input:checkbox[data-id="'+value+'"]').prop('checked', true); // Check additional value
                        $inputdiv.hide();

                        dtb_res = true;
                    }else{ // Create input elements
                        dtb_res = this._createEnumButtons(false, $inputdiv, [value]);
                    }

                    if(dtb_res){

                        if($input.hSelect('instance') != undefined){
                            $input.hSelect('destroy');
                        }
                        this._off($input, 'change');
                        $input.remove();

                        $input = $('<input type="text" class="text ui-widget-content ui-corner-all">')
                                    .attr('id', inpt_id)
                                    .val(value)
                                    .prependTo($inputdiv)
                                    .hide();

                        this._on( $input, {change:this.onChange} );

                        if(this.btn_add){
                            this.btn_add.hide(); // Hide repeat button, removeClass('smallbutton ui-icon-circlesmall-plus')
                        }
                    }
            }
        }
        else if(this.detailType=='boolean'){//----------------------------------------------------

            $input = $( '<input>',{type:'checkbox'} )
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('vertical-align','-3px')
            .on('change',function(){that.onChange();})
            .appendTo( $inputdiv );
            
            if(Array.isArray(this.configMode)){
                $input.prop('value', this.configMode[0]);
                $input.prop('checked', (this.configMode.indexOf(value)==0) );
            }else{
                $input.prop('value', '1');
                if(!(value==false || value=='0' || value=='n')){
                    $input.prop('checked','checked');
                }
            } 

        }
        else if(this.detailType=='rectype'){  //@todo it seems NOT USED, need refer via resource type and entity mgr

            $input = $( "<select>" )
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .appendTo( $inputdiv );

            window.hWin.HEURIST4.ui.createRectypeSelect($input.get(0),null, this.f('cst_EmptyValue'), true);
            if(value){
                $input.val(value);
            }
            $input.on('change',function(){that.onChange();})

        }
        else if(this.detailType=="user"){ //special case - only groups of current user

            $input = $( "<select>")
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .on('change',function(){that.onChange();})
            .appendTo( $inputdiv );
            var mode = null;
            
            if(this.configMode && (this.configMode.mode=='all_users' || this.configMode.mode=='all_users_and_groups')){
                topOptions = this.configMode.topOptions;
                mode = this.configMode.mode;
            }else{
                topOptions = [{key:'',title:window.hWin.HR('select user/group...')},
                    {key:window.hWin.HAPI4.currentUser['ugr_ID'], title:window.hWin.HAPI4.currentUser['ugr_FullName'] }];
            }

            window.hWin.HEURIST4.ui.createUserGroupsSelect($input.get(0), mode, topOptions );
            if(value){
                $input.val(value);
            }
        }
        /* todo
        else if(this.detailType=="keyword"){ 

            $input = $( "<select>")
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .on('change',function(){that.onChange();})
            .appendTo( $inputdiv );

            window.hWin.HEURIST4.ui.createUserGroupsSelect($input.get(0),null,
                [{key:'',title:window.hWin.HR('select user/group...')},
                    {key:window.hWin.HAPI4.currentUser['ugr_ID'], title:window.hWin.HAPI4.currentUser['ugr_FullName'] }] );
            if(value){
                $input.val(value);
            }
            
        }*/
        else if(this.detailType=='relmarker'){ //---------------------------------------------------- 
            
                this.options.showclear_button = false;
                //$inputdiv.css({'display':'inline-block','vertical-align':'middle'});
                $inputdiv.css({'display': 'table','vertical-align': 'middle', 'border-spacing': '0px'}); //was '0px 4px'
            
                if(this.inputs.length==0){ //show current relations
                
                    //these are relmarker fields from other rectypes that points to this record
                    var isInwardRelation = (that.f('rst_DisplayOrder')>1000);
                
                
                    function __onRelRemove(){
                        var tot_links = that.element.find('.link-div').length;
                        var rev_links = that.element.find('.reverse-relation').length; 
                        if( tot_links-rev_links==0){ //hide this button if there are links
                            that.element.find('.rel_link').show();
                        }else{
                            that.element.find('.rel_link').hide();
                        }
                        if( rev_links==0){
                            that.element.find('.reverse-relation-header').remove();
                        }
                    }
                    
                    var isOpened = false;
                    
                    var rts = [];
                    var ptrset = that._prepareIds(that.f('rst_PtrFilteredIDs'));
                    
                    for (var k=0; k<ptrset.length; k++) {
                        var sname = $Db.rty(ptrset[k],'rty_Name');
                        if(!window.hWin.HEURIST4.util.isempty(sname)){
                            rts.push(sname);
                        }
                    }
                    
                
                    var __show_addlink_dialog = function(){
                        if(isOpened || that.is_disabled) return;
                        
                        isOpened = true;
                        
                        if(that.options.editing && (that.options.editing.editStructureFlag()===true)){
                            window.hWin.HEURIST4.msg.showMsgFlash('This feature is disabled in edit structure mode, you can use it in normal record editing',3000);                     return;
                        }
                        
                        function __onCloseAddLink(context){
                            isOpened = false;
                            
                            if(context && context.count>0){
                                
                                var link_info = isInwardRelation?context.source:context.target;
                                link_info.relation_recID = context.relation_recID; //existing relationship record
                                link_info.relmarker_field = that.options.dtID;
                                link_info.trm_ID = context.trm_ID;
                                link_info.is_inward = isInwardRelation;
                                
                                var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($inputdiv,
                                    link_info, true);
                                ele.insertBefore(that.element.find('.rel_link'));
                                that.element.find('.rel_link').hide();//hide this button if there are links
                                ele.on('remove', __onRelRemove);

                            }

                            if(window.hWin.HUL.isFunction(that._external_relmarker.callback)){
                                that._external_relmarker.callback(context);
                            }

                            // Reset relmarker details for lookups
                            that._external_relmarker = {
                                target: null,
                                relation: null,
                                callback: null
                            };
                        }
                        
                        var rty_names = '';
                        if(rts.length>0 && that.options.rectypeID>0){
                            rty_names = $Db.rty(that.options.rectypeID,'rty_Name') 
                                        + ' and ' + rts.join(', ');
                        }else{
                            rty_names = 'records';
                        }
                        
                        var opts = {
                            height:480, width:750, 
                            title: 'Create relationship between '+rty_names+' ( Field: "'
                                +$Db.dty(that.options.dtID, 'dty_Name')+'" )',
                            relmarker_dty_ID: that.options.dtID,
                            default_palette_class: 'ui-heurist-populate',
                            onClose: __onCloseAddLink 
                        };

                        if(isInwardRelation){
                            opts['source_AllowedTypes'] = that.f('rst_PtrFilteredIDs');
                            opts['target_ID'] = that.options.recID;
                        }else{
                            opts['source_ID'] = that.options.recID;
                        }

                        if(that._external_relmarker.target){ // setup from external source (currently from external lookup)
                            opts['target_ID'] = that._external_relmarker.target;
                        }
                        if(that._external_relmarker.relation){ // setup from external source (currently from external lookup)
                            opts['relationtype'] = that._external_relmarker.relation;
                        }

                        window.hWin.HEURIST4.ui.showRecordActionDialog('recordAddLink', opts);
                    };
                    
                    var sRels = '';
                    if(that.options.recordset){
                    
                    var relations = that.options.recordset.getRelations();
                  
                    if(relations && (relations.direct || relations.reverse)){
                        
                        var ptrset = that._prepareIds(that.f('rst_PtrFilteredIDs'));
                        
                        var vocab_id = this.f('rst_FilteredJsonTermIDTree');        

                        var ph_gif = window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif';
                        var headers = relations.headers;
                        var dtID = this.options.dtID;
                        
                        
                      if(!isInwardRelation){
                            var direct = relations.direct; //outward
                            
                        //take only those that satisify to allowed terms and pointer constraints
                        for(var k in direct){
                            //direct[k]['dtID']==this.options.dtID && 
                            if(direct[k]['trmID']>0){ //relation   
                            
                                
                                if($Db.trm_InVocab(vocab_id, direct[k]['trmID']))
                                { //it satisfies to allowed relationship types

                                        //verify that target rectype is satisfy to constraints and trmID allowed
                                        var targetID = direct[k].targetID;
                                        
                                        if(!headers[targetID]){
                                            //there is not such record in database
                                            continue;                                            
                                        }
                                        
                                        var targetRectypeID = headers[targetID][1];
                                        if( headers[targetID]['used_in_reverse'+dtID]!=1 &&
                                           (ptrset.length==0 || 
                                            window.hWin.HEURIST4.util.findArrayIndex(targetRectypeID, ptrset)>=0))
                                        {
                                            
                                            var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($inputdiv, 
                                                {rec_ID: targetID, 
                                                 rec_Title: headers[targetID][0], 
                                                 rec_RecTypeID: headers[targetID][1], 
                                                 relation_recID: direct[k]['relationID'], 
                                                 relmarker_field: that.options.dtID,
                                                 trm_ID: direct[k]['trmID'],
                                                 dtl_StartDate: direct[k]['dtl_StartDate'], 
                                                 dtl_EndDate: direct[k]['dtl_EndDate'],
                                                 is_inward: false
                                                }, !this.isReadonly());
                                            ele.on('remove', __onRelRemove);
                                            
                                            headers[targetID]['used_in_direct'+dtID] = 1;
                                        }
                                }
                            }
                        }
                        
                      }//!isInwardRelation

                        
                        //small subheader before reverse entries
                        var isSubHeaderAdded = isInwardRelation;
                        
                        //now scan all indirect /inward relations
                        var reverse = relations.reverse; //outward
                        //take only those that satisify to allowed terms and pointer constraints
                        for(var k in reverse){
                            //direct[k]['dtID']==this.options.dtID && 
                            if(reverse[k]['trmID']>0){ //relation   
                                
                                if($Db.trm_InVocab(vocab_id, reverse[k]['trmID']))
                                { //it satisfies to allowed relationship types
                                
                                        //verify that target rectype is satisfy to constraints and trmID allowed
                                        var targetID = reverse[k].sourceID;
                                        
                                        if(!headers[targetID]){
                                            //there is not such record in database
                                            continue;                                            
                                        }
                                        
                                        var targetRectypeID = headers[targetID][1];
                                        
                                        if (headers[targetID]['used_in_direct'+dtID]!=1 && (ptrset.length==0) ||
                                                (window.hWin.HEURIST4.util.findArrayIndex(targetRectypeID, ptrset)>=0))
                                        {
                                            if(!isSubHeaderAdded){
                                                isSubHeaderAdded = true;
//Removed 30 Jan 2021: not relevant to distinguish relationships on the basis of which side is source and which side is target           //                                                $('<div>Referenced by</div>') //Reverse relationships
//                                                        .css('padding-top','4px')
//                                                        .addClass('header reverse-relation-header')
//                                                        .appendTo($inputdiv);
                                            }
                                            
                                            //var invTermID = window.hWin.HEURIST4.dbs.getInverseTermById(reverse[k]['trmID']);
                                            
                                            var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($inputdiv, 
                                                {rec_ID: targetID, 
                                                 rec_Title: headers[targetID][0], 
                                                 rec_RecTypeID: targetRectypeID, 
                                                 relation_recID: reverse[k]['relationID'], 
                                                 relmarker_field: that.options.dtID,
                                                 trm_ID: reverse[k]['trmID'], //invTermID,
                                                 dtl_StartDate: reverse[k]['dtl_StartDate'], 
                                                 dtl_EndDate: reverse[k]['dtl_EndDate'],
                                                 is_inward: true
                                                }, !this.isReadonly());
                                            ele.addClass('reverse-relation', 1)
                                                .on('remove', __onRelRemove);
                                            
                                            headers[targetID]['used_in_reverse'+dtID] = 1;
                                        }
                                }
                            }
                        }
                        
                        
                    }
                }
                
                    /*
                    $input = $( "<div>")
                        .uniqueId()
                        .html(sRels)
                        //.addClass('ui-widget-content ui-corner-all')
                        .appendTo( $inputdiv );
                   */  
                if(this.isReadonly()){
                   return 0; 
                }else{
                   $inputdiv
                        .uniqueId();
                   $input = $inputdiv;

                   var rty_names = '';
                   if(rts.length>0){
                        rty_names = '<div class="truncate" style="max-width:200px;display:inline-block;vertical-align:top">&nbsp;to '
                                +rts.join(', ') +'</div>';
                   }else{
                        rty_names = '';
                   }
                   
                   
                   //define explicit add relationship button
                   var $btn_add_rel_dialog = $( "<button>", {title: "Click to add new relationship"})
                        .addClass("rel_link") //.css({display:'block'})
                        .button({icons:{primary: "ui-icon-circle-plus"},label:'&nbsp;&nbsp;&nbsp;Add Relationship'
                                +rty_names});
                       
                   var rheader = that.element.find('.reverse-relation-header');     
                   if(rheader.length>0){
                        $btn_add_rel_dialog.insertBefore( rheader );
                   }else{
                        $btn_add_rel_dialog.appendTo( $inputdiv );   
                   }
                        
                   this._on($btn_add_rel_dialog,{click:__show_addlink_dialog});
                   //$btn_add_rel_dialog.on('click',function(){__show_addlink_dialog()});
                   
                   __onRelRemove();                   
                   /*if( this.element.find('.link-div').length>0){ //hide this button if there are links
                        $btn_add_rel_dialog.hide();
                   }*/
                }//not readonly   
                
                }else{
                    //this is second call - some links are already defined
                    //show popup dialog at once
                    //IJ ASKS to disbale it __show_addlink_dialog();
                    if(this.element.find('.rel_link').is(':visible')){
                        window.hWin.HEURIST4.msg.showMsgFlash('Please define the first relationship before adding another', 2000);                        
                    }
                    
                    this.element.find('.rel_link').show();
                    
                    return;
                }

            /* IJ asks to show button                 
            if( this.element.find('.link-div').length>0){ //hide this button if there are links
                $inputdiv.find('.rel_link').hide();
            }else{
                $inputdiv.find('.rel_link').show();
            }                
            */
                

        }
        else if(this.detailType=='resource' && this.configMode.entity=='records'){//---------------------------------

            /*
            if(value=='' && this.element.find('.sel_link2').is(':visible')){
                window.hWin.HEURIST4.msg.showMsgFlash('Please select record before adding another pointer',2000);
                return;
            }
            */
            
            var isparententity = (that.f('rst_CreateChildIfRecPtr')==1);
            
            //replace input with div
            $input = $( "<div>").css({'display':'inline-block','vertical-align':'middle','min-wdith':'25ex'})
                            .uniqueId().appendTo( $inputdiv );
                            
            var ptrset = that._prepareIds(that.f('rst_PtrFilteredIDs'));
            
            var rts = [];
            for (var k=0; k<ptrset.length; k++) {
                var sname = $Db.rty(ptrset[k],'rty_Name');
                if(!window.hWin.HEURIST4.util.isempty(sname)){
                    rts.push(sname);
                }
            }
            rts = (rts.length>0)?rts.join(', '):'record';
            let classes = 'sel_link2';

            if(isparententity){
                classes = classes + ' child_rec_fld';
                $input.addClass('child_rec_input');    
            }

            
            //define explicit add resource button
            $( "<button>", {title: "Select record to be linked"})
                        .button({icon:"ui-icon-triangle-1-e",
                               label:('&nbsp;&nbsp;&nbsp;<span style="color: #55555566;">'+(isparententity?'create child':'select')+'&nbsp: '
                               +'<div class="truncate" style="max-width:200px;display:inline-block;vertical-align:middle">'
                               +rts+'</div></span>')})
                        .addClass(classes).css({'max-width':'300px'}) //, 'background': 'lightgray'})
                        .appendTo( $inputdiv );
            
            var __show_select_function = null;
            if(typeof browseRecords!=='undefined' && window.hWin.HUL.isFunction(browseRecords)){
                __show_select_function = browseRecords(that, $input);//see editing_exts
            }
            
            that._findAndAssignTitle($input, value, __show_select_function);
            
            if(value){
                this.newvalues[$input.attr('id')] = value;  //for this type assign value at init    
                $input.attr('data-value', value);
            } 
        } 
        
        else if(this.detailType=='resource' && 
                (this.configMode.entity=='DefRecTypes' || this.configMode.entity=='DefDetailTypes')){ //-----------
            //it defines slightly different select dialog for defRecTypes
            __show_select_dialog = function(event){
        
                if(that.is_disabled) return;
                event.preventDefault();
                
                var sels = that.newvalues[$input.attr('id')];//$(event.target).attr('id')];
                
                var rg_options = {
                    select_mode: (this.configMode.csv!==false?'select_multi':'select_single'),
                    edit_mode: 'popup',
                    isdialog: true,
                    width: 440,
                    selection_on_init:sels?sels.split(','):[],
                    parent_dialog: this.element.closest('div[role="dialog"]'),
                    onselect:function(event, data){
                        
                        if(data && data.selection){
                            var newsel = data.selection;
                            that._findAndAssignTitle($input, newsel);
                            that.newvalues[$input.attr('id')] = newsel.join(',');
                            that.onChange();
                        }
                    }
                }

                if(this.options.dtID==window.hWin.HAPI4.sysinfo['dbconst']['DT_TIMELINE_FIELDS']){
                    rg_options['filters']= {types: ['date','year']};
                }
                
                window.hWin.HEURIST4.ui.showEntityDialog(this.configMode.entity, rg_options);
            }
            
            //replace input with div
            $input = $( "<div>").css({'display':'inline-block','vertical-align':'middle','min-wdith':'25ex'})
                            .uniqueId().appendTo( $inputdiv );
                            
                            
            //define explicit add relationship button
            $( "<button>", {title: "Select"})
                        .button({icon:"ui-icon-triangle-1-e",
                               label:('&nbsp;&nbsp;&nbsp;select')})
                        .addClass('sel_link2').hide()
                        .appendTo( $inputdiv );
            
            var $input_img, $gicon;
            var select_return_mode = 'ids';
            
            var icon_for_button = 'ui-icon-pencil'; //was -link
            if(this.configMode.select_return_mode &&
               this.configMode.select_return_mode!='ids'){
                 select_return_mode = 'recordset'
            }
                
            $gicon = $('<span class="ui-icon ui-icon-triangle-1-e sel_link" '
            +'style="display:inline-block;vertical-align:top;margin-left:8px;margin-top:2px;cursor:hand"></span>')
            .insertBefore( $input );
            
            $input.addClass('entity_selector').css({'margin-left': '-24px'});

            $input.css({'min-wdith':'22ex'});

            $input.hide();
            that._findAndAssignTitle($input, value);

            //no more buttons this._on( $btn_rec_search_dialog, { click: __show_select_dialog } );
            this._on( $input, { keypress: __show_select_dialog, click: __show_select_dialog } );
            this._on( $gicon, { click: __show_select_dialog } );
            this._on( $inputdiv.find('.sel_link2'), { click: __show_select_dialog } );
            
            if(value){
                this.newvalues[$input.attr('id')] = value;  //for this type assign value at init  
                $input.attr('data-value', value);
            }
        
        }
        else if(this.detailType=='resource') //----------------------------------------------------
        {
            //replace input with div
            $input = $( "<div>").css({'display':'inline-block','vertical-align':'middle','min-wdith':'25ex'})
                            .uniqueId().appendTo( $inputdiv );
                            
                            
            //define explicit add relationship button
            $( "<button>", {title: "Select"})
                        .button({icon:"ui-icon-triangle-1-e",
                               label:('&nbsp;&nbsp;&nbsp;select')})
                        .addClass('sel_link2').hide()
                        .appendTo( $inputdiv );
            
            var $input_img, $gicon;
            var select_return_mode = 'ids';
            
            var icon_for_button = 'ui-icon-pencil'; //was -link
            if(this.configMode.select_return_mode &&
               this.configMode.select_return_mode!='ids'){
                 select_return_mode = 'recordset'
            }
                
            $gicon = $('<span class="ui-icon ui-icon-triangle-1-e sel_link" '
            +'style="display:inline-block;vertical-align:top;margin-left:8px;margin-top:2px;cursor:hand"></span>')
            .insertBefore( $input );
            
            $input.addClass('entity_selector').css({'margin-left': '-24px'});

            $input.css({'min-wdith':'22ex'});

            var ptrset = that.f('rst_PtrFilteredIDs');

            var __show_select_dialog = null;
            /* 2017-11-08 no more buttons
            var $btn_rec_search_dialog = $( "<span>", {title: "Click to search and select"})
            .addClass('smallicon ui-icon '+icon_for_button)
            .appendTo( $inputdiv );
            */
            //.button({icons:{primary: icon_for_button},text:false});

            var popup_options = {
                isdialog: true,
                select_mode: (this.configMode.csv==true?'select_multi':'select_single'),
                select_return_mode:select_return_mode, //ids or recordset(for files)
                filter_group_selected:null,
                filter_groups: this.configMode.filter_group,
                filters: this.configMode.filters,
                onselect:function(event, data){

                    if(data){

                        if(select_return_mode=='ids'){


                            var newsel = window.hWin.HEURIST4.util.isArrayNotEmpty(data.selection)?data.selection:[];

                            //config and data are loaded already, since dialog was opened
                            that._findAndAssignTitle($input, newsel);
                            newsel = newsel.join(',')
                            that.newvalues[$input.attr('id')] = newsel;
                            $input.attr('data-value', newsel);

                            that.onChange();

                        }else if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                            //todo

                        }
                    }//data

                }
            };//popup_options
            
            $input.hide();
            that._findAndAssignTitle($input, value);

            __show_select_dialog = function(event){
                
                    if(that.is_disabled) return;

                    event.preventDefault();
                    
                    var usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_'+this.configMode.entity, 
                        {width: null,  //null triggers default width within particular widget
                        height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });
        
                    popup_options.width = usrPreferences.width;
                    popup_options.height = usrPreferences.height;
                    var sels = this.newvalues[$input.attr('id')];//$(event.target).attr('id')];
                    /*if(!sels && this.options.values && this.options.values[0]){
                         sels = this.options.values[0];
                    }*/ 
                    
                    if(!window.hWin.HEURIST4.util.isempty(sels)){
                        popup_options.selection_on_init = sels.split(',');
                    } else {
                        popup_options.selection_on_init = null;    
                    }                                
                    
                    if(this.configMode.initial_filter){
                        popup_options.initial_filter = this.configMode.initial_filter;    
                    }
                    if(!window.hWin.HEURIST4.util.isnull(this.configMode.search_form_visible)){
                        popup_options.search_form_visible = this.configMode.search_form_visible;    
                    }

                    var popup_options2 = popup_options;
                    if(this.configMode.popup_options){
                         popup_options2  = $.extend(popup_options, this.configMode.popup_options);
                    }
                    //init dialog to select related entities
                    window.hWin.HEURIST4.ui.showEntityDialog(this.configMode.entity, popup_options2);
            }
            
            
            //no more buttons this._on( $btn_rec_search_dialog, { click: __show_select_dialog } );
            this._on( $input, { keypress: __show_select_dialog, click: __show_select_dialog } );
            this._on( $gicon, { click: __show_select_dialog } );
            this._on( $inputdiv.find('.sel_link2'), { click: __show_select_dialog } );
            
            if(value){
                this.newvalues[$input.attr('id')] = value;  //for this type assign value at init  
                $input.attr('data-value', value);
            }

        }
        else 
        if(this.detailType=='geo'){   //----------------------------------------------------

            this.dtwidget = 'editInputGeo';
            $input = $inputdiv.editInputGeo({ container:this, value:value });
                
        }
        else 
        if(this.isFileForRecord){   //----------------------------------------------------
        
            this.dtwidget = 'editInputRecFile';
            $input = $inputdiv.editInputRecFile({ container:this, value:value });
        }        
        else
        if( this.detailType=='file' ){ //----------------------------------------------------

            this.options.showclear_button = this.options.showclear_button && (this.configMode.hideclear!=1);
        
            this.dtwidget = 'editInputFile';
            $input = $inputdiv.editInputFile({ container:this, value:value });
        
        }
        else 
        if(this.detailType=='date'){   //----------------------------------------------------
        
            this.dtwidget = 'editInputDate';
            $input = $inputdiv.editInputDate({ container:this, value:value });
        
        }
        else{              //----------------------------------------------------
            $input = $( "<input>")
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .val(value)
            .on('keyup', function(){that.onChange();})
            .on('change',function(){
                    that.onChange();
            })
            .appendTo( $inputdiv );
            
            window.hWin.HEURIST4.ui.disableAutoFill( $input );
            
            if(!(this.detailType=='resource' || 
                 this.detailType=='action')){
                     
                $input.on('keydown',function(e){  //Ctrl+A - select all
                    if (e.keyCode == 65 && e.ctrlKey) {
                                        e.target.select()
                    }    
                });
                if(this.detailType=='password'){
                    $input.prop('type','password');
                }
            }
            
            if(this.options.dtID=='rec_URL' || this.detailType=='url'){//----------------------------------
                
                    var $btn_extlink = null, $btn_editlink = null;
                
                    function __url_input_state(force_edit){
                    
                        if($input.val()=='' || force_edit===true){
                            $input.removeClass('rec_URL').addClass('text').attr("readonly",false);
                            that._off( $input, 'click');
                            if(!window.hWin.HEURIST4.util.isnull( $btn_extlink)){
                                
                                //$btn_editlink.remove();
                                //$btn_extlink = null;
                                if($btn_editlink!=null){
                                    $btn_editlink.remove();
                                    $btn_editlink = null;
                                }
                            }
                            if(force_edit===true){
                                $input.trigger('focus');   
                            }
                        }else if(window.hWin.HEURIST4.util.isnull($btn_extlink)){
                            
                            if($input.val()!='' && !($input.val().indexOf('http://')==0 || $input.val().indexOf('https://')==0)){
                                $input.val( 'https://'+$input.val());
                            }
                            $input.addClass('rec_URL').removeClass('text').attr('readonly','readonly');
                            
                            $btn_editlink = $( '<span>', {title: 'Edit URL'})
                                .addClass('smallicon ui-icon ui-icon-pencil')
                                .appendTo( $inputdiv );
                                //.button({icons:{primary: 'ui-icon-pencil'},text:false});
                        
                            that._on( $btn_editlink, { click: function(){ __url_input_state(true) }} );
                        }
                
                    }

                    if($btn_extlink==null){
                        $btn_extlink = $( '<span>', {title: 'Open URL in new window'})
                            .addClass('smallicon ui-icon ui-icon-extlink')
                            .appendTo( $inputdiv );
                    
                        that._on( $btn_extlink, { click: function(){ window.open($input.val(), '_blank') }} );
                        that._on( $input, { click: function(){ if ($input.val()!='') window.open($input.val(), '_blank') }} );
                    }

                    //$input.focusout( __url_input_state ); 
                    __url_input_state(true);               
                
            }
            else if(this.detailType=="integer" || this.detailType=="year"){//-----------------------------------------

                 
                $input.on('keypress', function (e) {
                    var code = e.charCode || e.keyCode;
                    var charValue = String.fromCharCode(code);
                    var valid = false;

                    if(charValue=='-' && this.value.indexOf('-')<0){
                        this.value = '-'+this.value;
                    }else{
                        valid = /^[0-9]+$/.test(charValue);
                    }

                    if(!valid){
                        window.hWin.HEURIST4.util.stopEvent(e);
                        e.preventDefault();
                        window.hWin.HEURIST4.msg.showTooltipFlash(window.hWin.HR('Numeric field'),1000,$input);
                    }

                });
                
                $input.on('paste', function(e){
                    if(!Number.isInteger(+e.originalEvent.clipboardData.getData('text'))){
                        window.hWin.HEURIST4.util.stopEvent(e);
                        e.preventDefault();
                        window.hWin.HEURIST4.msg.showTooltipFlash(window.hWin.HR('Numeric field'),1000,$input);
                    }
                });

                if(this.f('rst_Spinner') == 1){

                    let spinner_step = this.f('rst_SpinnerStep');

                    $input.prop('type', 'number').prop('step', spinner_step);

                    // Set minimum and maximum values
                    let max_val = this.f('rst_MaxValue');
                    let min_val = this.f('rst_MinValue');
    
                    if(window.hWin.HEURIST4.util.isNumber(min_val)){
                        $input.prop('min', min_val);
                    }
                    if(window.hWin.HEURIST4.util.isNumber(max_val)){
                        $input.prop('max', max_val);
                    }
                }

            }else
            if(this.detailType=="float"){//----------------------------------------------------

                $input.on('keypress', function (e) {
                    var code = e.charCode || e.keyCode; //(e.keyCode ? e.keyCode : e.which);
                    var charValue = String.fromCharCode(code);
                    var valid = false;


                    if(charValue=='-' && this.value.indexOf('-')<0){
                        this.value = '-'+this.value;
                    }else if(charValue=='.' && this.value.indexOf('.')<0){
                        valid = true;
                    }else{
                        valid = /^[0-9]+$/.test(charValue);
                    }

                    if(!valid){
                        window.hWin.HEURIST4.util.stopEvent(e);
                        e.preventDefault();
                        window.hWin.HEURIST4.msg.showTooltipFlash(window.hWin.HR('Numeric field'),1000,$input);
                    }

                });

                $input.on('paste', function(e){
                    if(!window.hWin.HEURIST4.util.isNumber(e.originalEvent.clipboardData.getData('text'))){
                        window.hWin.HEURIST4.util.stopEvent(e);
                        e.preventDefault();
                        window.hWin.HEURIST4.msg.showTooltipFlash(window.hWin.HR('Numeric field'),1000,$input);
                    }
                });

                if(this.f('rst_Spinner') == 1){

                    let spinner_step = this.f('rst_SpinnerStep');

                    $input.prop('type', 'number').prop('step', spinner_step);

                    // Set minimum and maximum values
                    let max_val = this.f('rst_MaxValue');
                    let min_val = this.f('rst_MinValue');
    
                    if(window.hWin.HEURIST4.util.isNumber(min_val)){
                        $input.prop('min', min_val);
                    }
                    if(window.hWin.HEURIST4.util.isNumber(max_val)){
                        $input.prop('max', max_val);
                    }
                }

            }
            else  //----------------------------------------------------
            if( this.detailType=='folder' ){ //----------------------------------------------------
                
                $input.css({'padding-left':'30px'});
                
                var $gicon = $('<span>').addClass('ui-icon ui-icon-gear')
                    .css({position:'absolute',margin:'2px 0 0 8px',cursor:'hand'})
                    .insertBefore($input);
                var $select_folder_dlg = $('<div>').hide().appendTo( $inputdiv );
                
                that.newvalues[$input.attr('id')] = value;
                    
                this._on( $gicon, { click: function(){                                 
                       $select_folder_dlg.selectFolders({
                       onselect:function(newsel){
                            if(newsel){
                                var newsel = newsel.join(';');
                                that.newvalues[$input.attr('id')] = newsel;
                                $input.val(newsel);
                                that.onChange();
                            }
                        }, 
                       selectedValues: that.newvalues[$input.attr('id')], 
                       multiselect: that.configMode && that.configMode.multiselect});
                    }} );
            }
            else //------------------------------------------------------------------------------------
            if(this.detailType=='action'){
                
                $input.css({'width':'62ex','padding-left':'30px',cursor:'hand'});
                   
                var $gicon = $('<span>').addClass('ui-icon ui-icon-gear')
                    .css({position:'absolute',margin:'2px 0 0 8px',cursor:'hand'})
                    .insertBefore($input);
            
                //parse and return json object
                that.newvalues[$input.attr('id')] = window.hWin.HEURIST4.util.isJSON(value);
                if(that.newvalues[$input.attr('id')]==false){
                    that.newvalues[$input.attr('id')] = {};
                }
                $input.val(JSON.stringify(that.newvalues[$input.attr('id')])).css('cursor','hand');
                
                      
                var __show_action_dialog = function (event){
                        event.preventDefault();
                        
                        if(that.is_disabled) return;
                        
                        var dlg_options = that.newvalues[$input.attr('id')];
                        if(  window.hWin.HEURIST4.util.isempty(dlg_options) ){
                            dlg_options = {};
                        }
                        dlg_options.title = that.configMode.title;
                        dlg_options.get_params_only = true;
                        dlg_options.onClose = function(value){
                            if(value){
                                that.newvalues[$input.attr('id')] = window.hWin.HEURIST4.util.isJSON(value);
                                if(that.newvalues[$input.attr('id')]==false){
                                    that.newvalues[$input.attr('id')] = {};
                                }
                                $input.val(JSON.stringify(that.newvalues[$input.attr('id')])).trigger('change');
                            }
                        };
                        
                        window.hWin.HEURIST4.ui.showRecordActionDialog( this.configMode.actionName, dlg_options );
                };

                this._on( $input, { keypress: __show_action_dialog, click: __show_action_dialog } );
                this._on( $gicon, { click: __show_action_dialog } );
            }
            else if(this.configMode && this.configMode['colorpicker']){ //-----------------------------------------------

                $input.colorpicker({
                    hideButton: false, //show button right to input
                    showOn: "both",
                    val:value
                }).css('max-width', '130px');

                $input.parent('.evo-cp-wrap').css({display:'inline-block',width:'180px'});

            }
            else 
            if(this.options.dtID && this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_BOOKMARK']){ 
                // Geo Bookmark, five input form, experimental 

                $input.css({cursor:'hand'});

                __show_geoBookmark_dialog = function(event) {
                    event.preventDefault();

                    if(that.is_disabled) return;

                    var current_val = $input.val();

                    // split current_val into parts based on , 
                    var setup_val = current_val.split(",");

                    var $dlg = null;

                    var pdiv = '<div style="display:grid;grid-template-columns:100%;">'

                            + '<div style="margin-bottom:10px;display:grid;grid-template-columns:150px 200px;">'
                            + '<label class="required">Bookmark Name:</label><input type="text" id="bkm_name"></div>'

                            + '<div style="margin-bottom:10px;display:grid;grid-template-columns:150px 200px;">'
                            + '<label class="required">Bottom left (X, Y):</label><input type="text" id="bkm_long" class="bkm_points" style="cursor:pointer;"></div>'

                            + '<div style="margin-bottom:10px;display:grid;grid-template-columns:150px 200px;">'
                            + '<label class="required">Top right (X, Y):</label><input type="text" id="bkm_lat" class="bkm_points" style="cursor:pointer;"></div>'

                            + '<div style="margin-bottom:10px;display:grid;grid-template-columns:150px 200px;">'
                            + '<label style="color:#6A7C99">Starting Date:</label><input type="text" id="bkm_sdate"></div>'

                            + '<div style="margin-bottom:10px;display:grid;grid-template-columns:150px 200px;">'
                            + '<label style="color:#6A7C99">Ending Date:</label><input type="text" id="bkm_edate"></div>'

                    var popele = $(pdiv);

                    popele.find('input[class="bkm_points"]').on('click',function(e){
                        var url = window.hWin.HAPI4.baseURL 
                            +'viewers/map/mapDraw.php?db='+window.hWin.HAPI4.database;

                        var wkt_points = $('input[id="bkm_long"]').val() + ',' + $('input[id="bkm_lat"]').val();
                        var points = wkt_points.split(/[\s,]+/);

                        var geo_points = points[0] + ',' + points[2] + ' ' + points[1] + ',' + points[3];

                        var wkt_params = {'wkt': geo_points};
                        wkt_params['tool_option'] = 'rectangle';

                        var d_width = (window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
                        d_height = (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95;

                        window.hWin.HEURIST4.msg.showDialog(url, {
                            height:that.options.is_faceted_search?540:d_height,
                            width:that.options.is_faceted_search?600:d_width,
                            window: window.hWin,  //opener is top most heurist window
                            dialogid: 'map_digitizer_dialog',
                            default_palette_class: 'ui-heurist-populate',
                            params: wkt_params,
                            title: window.hWin.HR('Heurist map digitizer'),
                            callback: function(location){
                                if( !window.hWin.HEURIST4.util.isempty(location) ){
                                    
                                    var geovalue = window.hWin.HEURIST4.geo.wktValueToDescription(location.type+' '+location.wkt, true);
                                    var geocode = geovalue.summary;
                                    geocode = geocode.replace('X', '');
                                    geocode = geocode.replace('Y', '');
                                    geocode = geocode.replace(' ', '');

                                    var points = geocode.split(/[\s,]+/);

                                    $('input[id="bkm_long"]').val(points[0] + ',' + points[2]).trigger('change');
                                    $('input[id="bkm_lat"]').val(points[1] + ',' + points[3]).trigger('change');
                                }
                            }
                        } );
                    });

                    popele.find('input[id="bkm_name"]').val(setup_val[0]);
                    popele.find('input[id="bkm_long"]').val(setup_val[1] +','+ setup_val[2]);
                    popele.find('input[id="bkm_lat"]').val(setup_val[3] +','+ setup_val[4]);

                    if(setup_val.length == 7){
                        popele.find('input[id="bkm_sdate"]').val(setup_val[5]);
                        popele.find('input[id="bkm_edate"]').val(setup_val[6]);
                    }

                    var btns = [
                        {text:window.hWin.HR('Apply'),
                            click: function(){

                                var title = popele.find('input[id="bkm_name"]').val();
                                var long_points = popele.find('input[id="bkm_long"]').val();
                                var lat_points = popele.find('input[id="bkm_lat"]').val();
                                var sdate = popele.find('input[id="bkm_sdate"]').val();
                                var edate = popele.find('input[id="bkm_edate"]').val();

                                var geo_points = long_points + ',' + lat_points;

                                if(window.hWin.HEURIST4.util.isempty(title) || window.hWin.HEURIST4.util.isempty(geo_points)){
                                    window.hWin.HEURIST4.msg.showMsgFlash('A title and map points must be provided', 2500);
                                    return;
                                }

                                var points = geo_points.split(/[\s,]+/);

                                if(points.length != 4){
                                    window.hWin.HEURIST4.msg.showMsgFlash('You need 2 sets of geographical points', 2500);
                                    return;
                                }

                                geo_points = "";
                                for(var i = 0; i < points.length; i++){
                                    var n = points[i];
                                    geo_points = geo_points + ',' + parseFloat(n).toFixed(2);
                                }

                                var has_start_date = window.hWin.HEURIST4.util.isempty(sdate);
                                var has_end_date = window.hWin.HEURIST4.util.isempty(edate);

                                if(has_start_date && has_end_date){
                                    $input.val(title + geo_points);
                                }
                                else if(!has_start_date && !has_end_date){
                                    $input.val(title + geo_points +','+ sdate +','+ edate);
                                }
                                else{
                                    window.hWin.HEURIST4.msg.showMsgFlash('You must provide both a start and end date, or neither', 2500);
                                    return;
                                }

                                $dlg.dialog('close');
                            }
                        },
                        {text:window.hWin.HR('Close'),
                            click: function() { $dlg.dialog('close'); }
                        }
                    ];

                    $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({
                        window:  window.hWin, //opener is top most heurist window
                        title: window.hWin.HR('Geographical bookmark form'),
                        width: 575,
                        height: 260,
                        element:  popele[0],
                        resizable: false,
                        buttons: btns,
                        default_palette_class: 'ui-heurist-populate'
                    });                 
                }
                this._on( $input, { keypress: __show_geoBookmark_dialog, click: __show_geoBookmark_dialog } );   
            } // end of geo bookmark
            
        }//end if by detailType

        //----------------- color or symbology editor
        if( this.options.dtID > 0 && this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY']){

                if(that.options.rectypeID!=window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER']){
                    $input.attr('readonly','readonly');
                }
                
                if(this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME']){
                    
                        //custom/user heurist theme
                        var $btn_edit_switcher2 = $( '<span>open editor</span>', {title: 'Open color sheme editor'})
                            .addClass('smallbutton btn_add_term')
                            .css({'line-height': '20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
                            .appendTo( $inputdiv );

                        var $btn_edit_clear2 = $( '<span>reset colors</span>', {title: 'Reset default color settings'})
                            .addClass('smallbutton btn_add_term')
                            .css({'line-height': '20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
                            .appendTo($inputdiv )
                            .on( { click: function(){ $input.val('');that.onChange(); } });
                            
                        function __openThemeDialog(){
                                var current_val = window.hWin.HEURIST4.util.isJSON( $input.val() );
                                if(!current_val) current_val = {};
                                window.hWin.HEURIST4.ui.showEditThemeDialog(current_val, false, function(new_value){
                                    $input.val(JSON.stringify(new_value));
                                    that.onChange();
                                });
                        }                
                        
                        $input.css({'max-width':'400px'}).on({ click: __openThemeDialog });
                        $btn_edit_switcher2.on( { click: __openThemeDialog });
                    
                }else{
                
                    var $btn_edit_switcher = $( '<span>style editor</span>', {title: 'Open symbology editor'})
                        //.addClass('smallicon ui-icon ui-icon-gear btn_add_term')
                        .addClass('smallbutton btn_add_term')
                        .css({'line-height': '20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
                        .appendTo( $inputdiv );
                    
                    this._on( $btn_edit_switcher, { click: function(){
                        
                            var mode_edit = 0;
                            var current_val = window.hWin.HEURIST4.util.isJSON($input.val());
                            if(!current_val) current_val = {};
                        
                            if(that.options.rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER']){
                                
                                //get query from linked datasource
                                var ele = that.options.editing.getFieldByName(window.hWin.HAPI4.sysinfo['dbconst']['DT_DATA_SOURCE']);
                                var vals = ele.editing_input('getValues');
                                var dataset_record_id = vals[0];
                                
                                if(dataset_record_id>0){
                                    
                                    const DT_QUERY_STRING = window.hWin.HAPI4.sysinfo['dbconst']['DT_QUERY_STRING'];
                                
                                    var server_request = {
                                        q: 'ids:'+dataset_record_id,
                                        restapi: 1,
                                        columns: 
                                        ['rec_ID', 'rec_RecTypeID', DT_QUERY_STRING],
                                        zip: 1,
                                        format:'json'};
                    
                                    //perform search see record_output.php       
                                    window.hWin.HAPI4.RecordMgr.search_new(server_request,
                                        function(response){
                                               if(window.hWin.HEURIST4.util.isJSON(response)) {
                                                    let hquery = null;
                                                    mode_edit = 3;
                                                    if(response['records'] && response['records'].length>0){
                                                        let rectype = response['records'][0]['rec_RecTypeID']; 
                                                        
                                                        if (rectype==window.hWin.HAPI4.sysinfo['dbconst']['RT_IMAGE_SOURCE']
                                                        || rectype==window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']){
                                                            //show image filter dialogue
                                                            window.hWin.HEURIST4.ui.showImgFilterDialog(current_val, function(new_value){
                                                                $input.val(JSON.stringify(new_value));
                                                                that.onChange();
                                                            });
                                                            return;                                                            
                                                        }else if (rectype==window.hWin.HAPI4.sysinfo['dbconst']['RT_QUERY_SOURCE']){
                                                            
                                                            var res = response['records'][0]['details'];
                                                            if(res[DT_QUERY_STRING]){
                                                                //{12:{4407:"t:10"}}
                                                                hquery = res[DT_QUERY_STRING][ Object.keys(res[DT_QUERY_STRING])[0] ];
                                                            }
                                                            
                                                        }
                                                    }
                                                    
                                                    current_val.maplayer_query = hquery;
                                                    
                                                    window.hWin.HEURIST4.ui.showEditSymbologyDialog(current_val, 
                                                            hquery==null?1:3, function(new_value){
                                                        $input.val(JSON.stringify(new_value));
                                                        that.onChange();
                                                    });
                                                    
                                                }
                                            });
                                    return;        
                                }
                            }
                        
                            window.hWin.HEURIST4.ui.showEditSymbologyDialog(current_val, mode_edit, function(new_value){
                                $input.val(JSON.stringify(new_value));
                                that.onChange();
                            });
                    }});
            
                }
             
        }//end color/symbol editor
        
        else if( this.options.dtID > 0 && this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_WORLDFILE']){

            let $btn_edit_switcher = $( '<span>calculate extent</span>', 
                {title: 'Get image extent based on worldfile parameters and image width and height'})
                    .addClass('smallbutton btn_add_term')
                    .css({'line-height': '20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
                    .appendTo( $inputdiv );

            this._on( $btn_edit_switcher, { click: function(){
                calculateImageExtentFromWorldFile( that.options.editing );
            }});
        }

        // Freetext value that is a url
        let freetext_url = this.detailType=="freetext" && ($input.val().indexOf('http://')==0 || $input.val().indexOf('https://')==0);
        // Semantic url links, separated by semi-colons, for RecTypes, Vocab+Terms, DetailTypes
        let semantic_uri = this.options.dtID && (typeof this.options.dtID === 'string' || this.options.dtID instanceof String)
                            && this.options.dtID.indexOf('ReferenceURL') !== -1;
        if(freetext_url || semantic_uri){

            $btn_extlink = $( '<span>', {title: 'Open URL(s) in new window'})
                .addClass('smallicon ui-icon ui-icon-extlink')
                .appendTo( $inputdiv );

            that._on($btn_extlink, { 
                click: function(){
                    var cur_val = $input.val();
                    if(!window.hWin.HEURIST4.util.isempty(cur_val)){ // check for value
                        var urls = cur_val.split(';');
                        urls = urls.map((url, idx) => { 
                            if(!window.hWin.HEURIST4.util.isempty(url)){
                                url = url.trim();
                                window.open(url);
                                return url;
                            }
                        });
                    }
                } 
            });
        }

        //for calculated field
        if(window.hWin.HAPI4.is_admin() && this.options.dtFields && this.options.dtFields['rst_CalcFunctionID']>0){            
            
            var $btn_calcfield = $( '<span>', {title: 'Edit calculated field formula'})
            .addClass('smallicon ui-icon ui-icon-calculator-b btn_add_term')
            .css({'margin-top':'2px',cursor:'pointer'})
            .appendTo( $inputdiv );
            
            var that = this;
            
            this._on( $btn_calcfield,{ click: function(){ 
                window.hWin.HEURIST4.dbs.editCalculatedField( this.options.dtFields['rst_CalcFunctionID'], 
                    function(){
                        //refresh value
                        if(!(that.options.recID>0)) return;

                        var request = request = {q: 'ids:'+that.options.recID, w: 'all', detail:[that.options.dtID] };

                        window.hWin.HAPI4.RecordSearch.doSearchWithCallback( request, function( recordset )
                            {
                                if ( recordset!=null ){
                                    var val = recordset.fld(recordset.getFirstRecord(), that.options.dtID);
                                    that.setValue(val);
                                    that.options.values = that.getValues();
                                }
                        });
                        
                        
                    } );
            }});
                            
        }
        
        
        this.inputs.push($input);
        
        var dwidth = this.f('rst_DisplayWidth');
        
        if(this.dtwidget!=null && $input[this.dtwidget]('instance')){
            $input[this.dtwidget]('setWidth', dwidth);
        }else
        if( typeof dwidth==='string' && dwidth.indexOf('%')== dwidth.length-1){ //set in percents
            
            $input.css('width', dwidth);
            
        }else if ( this.detailType=='freetext' || this.detailType=='url'   
                || this.detailType=='integer' || this.detailType=='float') {  

              //if the size is greater than zero
              var nw = (this.detailType=='integer' || this.detailType=='float')?40:120;
              if (parseFloat( dwidth ) > 0){ 
                  nw = Math.round( 3+Number(dwidth) );
                    //Math.round(2 + Math.min(120, Number(dwidth))) + "ex";
              }
              $input.css({'min-width':nw+'ex','width':nw+'ex'}); //was *4/3

        }
        
        if(this.options.is_faceted_search){
            
            if(this.options.is_between_mode && 
                (this.detailType=='freetext' || this.detailType=='date'
                || this.detailType=='integer' || this.detailType=='float')){

                    
                this.addSecondInput( $input.attr('id') );
                    
            }else {
                this.options.is_between_mode = false;
                if(this.detailType!='date') 
                        $input.css({'max-width':'33ex','min-width':'33ex'});
            }
        }
        
        //if(this.detailType!='blocktext')
        //    $input.css('max-width', '600px');


        //name="type:1[bd:138]"
        
        //clear button
        //var $btn_clear = $( "<div>")
        if(this.options.showclear_button && this.options.dtID!='rec_URL')
        {
            if(!(this.detailType == 'enum' && this.inputs.length > 1 && this.enum_buttons == 'checkbox')){

                var $btn_clear = $('<span>')
                .addClass("smallbutton ui-icon ui-icon-circlesmall-close btn_input_clear show-onhover")//   ui-icon
                .attr('tabindex', '-1')
                .attr('title', 'Clear entered value')
                .attr('data-input-id', $input.attr('id'))
                .appendTo( $inputdiv )
                //.button({icons:{primary: "ui-icon-circlesmall-close"},text:false});
                .css({'margin-top': '3px', position: 'absolute',
                     cursor:'pointer',             //'font-size':'2em',
    //outline_suppress does not work - so list all these props here explicitely                
                        outline: 'none','outline-style':'none', 'box-shadow':'none',  'border-color':'transparent'
                });
    			
                if($inputdiv.find('#btn_clear_container').length > 0){ // Check if button needs to be placed within a container, or appended to input
                    $inputdiv.find('#btn_clear_container').replaceWith( $btn_clear );
                }			
                
                // bind click events
                this._on( $btn_clear, {
                    click: function(e){
                                                
                        if(that.is_disabled) return;
                        
                        //if empty
                        if(that.getValues()[0] == '') { 

                            let delete_images = that.configMode && that.configMode.entity == 'defTerms' && that.input_img && // only for defTerms for now
                                                    !window.hWin.HEURIST4.util.isempty(that.input_img.find('img').attr('src'));
                            if($(that.inputs[0]).fileupload('instance') !== undefined && delete_images){

                                // Check there is an image to delete
                                window.hWin.HAPI4.checkImage(that.configMode.entity, that.options.recID, 'thumb', function(response){
                                    if(response.data=='ok'){
                                        that.newvalues[$input.attr('id')] = 'delete'; // tell php script to delete image files
                                        that.input_img.find('img').attr('src', ''); // remove from field input
        
                                        that.onChange(); // trigger modified flag
                                    }
                                });
                            }

                            return;
                        }

                        var input_id = $(e.target).attr('data-input-id');  //parent(). need if button
console.log('remove ', input_id);                        
                        if(that.detailType=="resource" && that.configMode.entity=='records' 
                                && that.f('rst_CreateChildIfRecPtr')==1){
                            that._clearChildRecordPointer( input_id );
                        }else{
                            that._removeInput( input_id );
                        }
                        
                        
                        that.onChange(); 
                    }
                });
            }
        }
        
        // add visible icon for dragging/sorting field values
        if(this.is_sortable && !that.isReadonly() && !this.is_disabled 
            && (this.detailType!="relmarker")
            && !this.enum_buttons && this.f('rst_MultiLang')!=1){

            var $btn_sort = $('<span>')
                .addClass('ui-icon ui-icon-arrow-2-n-s btn_input_move smallicon')
                .attr('title', 'Drag to re-arrange values')
                .css('display', 'none');

            if($inputdiv.find('.btn_input_clear').length > 0){
                $btn_sort.insertBefore($inputdiv.find('.btn_input_clear'));
            }else{
                $btn_sort.appendTo($inputdiv);
            }

            this._on($inputdiv, {
                'mouseenter': function(){
                    if(that.is_disabled) return;
                    if($inputdiv.parent().find('.input-div').length > 1){
                        $inputdiv.find('.btn_input_move').css('display', 'inline-block');
                    }
                },
                'mouseleave': function(){           
                    if(that.is_disabled) return;
                    $inputdiv.find('.btn_input_move').css('display', 'none');
                }
            });
        }
        
        if(!that.isReadonly()){
        
        //adds individual field visibility button
        var btn_field_visibility = $( '<span>', {title: 'Show/hide value from public'})
                    .addClass('field-visibility smallicon ui-icon ui-icon-eye-open')
                    .attr('data-input-id', $input.attr('id'))
                    .css({
                        'margin-top': '3px',
                        'cursor': 'pointer',
                        'vertical-align': 'top'
                    });


        if($inputdiv.find('.btn_input_clear').length > 0){
           btn_field_visibility.insertBefore($inputdiv.find('.btn_input_clear'));
        }else{
           btn_field_visibility.insertAfter( $input );
        }
        btn_field_visibility.hide();
                    
                    
        var chbox_field_visibility = $( '<div><span class="smallicon ui-icon ui-icon-check-off" style="font-size:1em"></span> '
                    +'Hide this value from public</div>', 
                    {title: 'Per record visibility'})
                    .addClass('field-visibility2 graytext')
                    .attr('data-input-id', $input.attr('id'))
                    .css({
                        'margin-top': '2px',
                        'cursor': 'pointer',
                        'font-size': '10px',
                        'font-style': 'italic',
                        'padding-left': '10px'
                    })
                    .appendTo( $inputdiv )
                    .hide();   //$inputdiv.find('.smallicon:first')

        this._on(chbox_field_visibility, {
            'click': function(e){
                if(that.is_disabled) return;

                var chbox = $(e.target);
                if(chbox.is('span')) chbox = chbox.parent();
                
                var btn = this.element.find('span.field-visibility[data-input-id="'+chbox.attr('data-input-id')+'"]');
                
                btn.trigger('click');
            }});
                    
                    
        this._on(btn_field_visibility, {
            'click': function(e){

                let vis_mode = this.f('rst_NonOwnerVisibility');

                if(that.is_disabled || vis_mode == 'viewable' || vis_mode == 'hidden') return;
                
                var btn = $(e.target);
                
                if(btn.attr('hide_field')=='1'){
                    btn.attr('hide_field',0);
                }else{
                    btn.attr('hide_field',1);
                }
                
                this._setVisibilityStatus(btn.attr('data-input-id'));
                
                this.isChanged(true);
                this.onChange();
            }
        });

        }

        //move term error message to last 
        var trm_err = $inputdiv.find('.term-error-message');
        if(trm_err.length>0){
           trm_err.appendTo($inputdiv);
        }

        return $input.attr('id');

    }, //addInput
    
    //
    //
    //
    _setVisibilityStatus: function(input_id){

        var vis_mode = this.f('rst_NonOwnerVisibility');

        if(this.options.showedit_button && this.detailType!="relmarker" &&
           (this.options.recordset && this.options.recordset.entityName == 'Records') && 
           (!window.hWin.HEURIST4.util.isempty(vis_mode)))
        {
        
            var that = this;
            var vis_btns = this.element.find('span.field-visibility'+
                    (input_id?'[data-input-id="'+input_id+'"]':'')); 
            
            $.each(vis_btns, function(idx, btn){

                btn = $(btn);
                var chbox = that.element.find('div.field-visibility2[data-input-id="'+btn.attr('data-input-id')+'"]');
                var $input_div =  btn.parent('.input-div');

                let $first_icon = $input_div.find('.show-onhover:first');
                if($first_icon.length == 1 && !$first_icon.hasClass('field-visibility')){ // make eye the first icon
                    $first_icon.before(btn);
                    if($input_div.find('.vis_text_help').length > 0){
                        $first_icon.before($input_div.find('.vis_text_help'));
                    }
                }
                
                if(btn.attr('hide_field')=='1' || vis_mode == 'viewable' || vis_mode == 'hidden'){

                    if($input_div.find('.link-div').length > 0){
                        $input_div.find('.link-div').css('background-color', ''); // remove existing property (it's set to important)
                    }

                    $input_div.find('.text, .sel_link2, .link-div, .ui-selectmenu-button').addClass('grayed');
                    btn.removeClass('ui-icon-eye-open');            
                    btn.addClass('ui-icon-eye-crossed');
                    btn.attr('title', 'This value is not visible to the public');
                    

                    if(vis_mode=='public' || vis_mode == 'viewable' || vis_mode == 'hidden'){ 

                        btn.removeClass('show-onhover'); //show always for invisible field   
                        btn.css('display','inline-block');

                        if(vis_mode != 'public'){ // change rollover for eye icon

                            const mini_text = vis_mode == 'viewable' ? 'logged-in only' : 'owner only';
                            const vis_title = vis_mode == 'viewable' ? 'This value is only visible to logged-in users' : 'This value is only visible to the owner/owner group';

                            btn.attr('title', vis_title);

                            if($input_div.find('.vis_text_help').length == 0){
                                let $vis_text = $('<span>', {title: vis_title, class: 'vis_text_help', style: 'vertical-align: 3px; padding-left: 5px; font-size: 10px; color: #999;'})
                                                .text(mini_text)
                                                .appendTo($input_div); //insertAfter(btn) - inserts multiple instances
                                $vis_text.before(btn);
                            }
                        }
                    }else{
                        chbox.find('span.ui-icon').removeClass('ui-icon-check-off').addClass('ui-icon-check-on');
                    }
                }else{

                    if($input_div.find('.link-div').length > 0){
                        $input_div.find('.link-div')[0].style.setProperty('background-color', '#F4F2F4', ' !important');
                    }

                    $input_div.find('.text, .sel_link2, .link-div, .ui-selectmenu-button').removeClass('grayed');
                    btn.removeClass('ui-icon-eye-crossed');            
                    btn.addClass('ui-icon-eye-open');
                    btn.attr('title', 'Show/hide value from public');
                    chbox.find('span.ui-icon').removeClass('ui-icon-check-on').addClass('ui-icon-check-off');

                    if(vis_mode=='public'){
                        btn.css('display','');    
                        btn.addClass('show-onhover');
                    }
                }

                if(vis_mode=='public' || vis_mode == 'viewable' || vis_mode == 'hidden'){
                    chbox.hide();
                }else{
                    //pending
                    chbox.show();
                    btn.removeClass('show-onhover'); //show always for pending
                    btn.css('display','inline-block');                        
                }

            });//each
        
        }else{
            //hide for all exept public status
            this.element.find('span.field-visibility').hide();
            this.element.find('div.field-visibility2').hide();
        }  
    },

    //
    //
    //
    _clearChildRecordPointer: function( input_id ){
        
            var that = this;
        
            var popele = that.element.find('.child_delete_dlg');
            if(popele.length==0){
                var sdiv = '<div class="child_delete_dlg">'
                +'<div style="padding:15px 0">You are deleting a pointer to a child record, that is a record which is owned by/an integral part of the current record, as identified by a pointer back from the child to the current record.</div>'
                //Actions:<br>
                +'<div><label><input type="radio" value="1" name="delete_mode" style="outline:none"/>'
                            +'Delete connection between parent and child</label><br><br>'
                        +'<label><input type="radio" value="2" name="delete_mode" checked="checked" style="outline:none"/>'
                            +'Delete the child record completely</label></div>'
                +'<div style="padding:15px 0">Warning: If you delete the connection between the parent and child, this will often render the child record useless as it may lack identifying information.</div></div>';
                
//<label><input type="radio" value="0" name="delete_mode"/>Leave child record as-is</label><br>
//<p style="padding:0 0 15px 0">If you leave the child record as-is, it will remain as a child of the current record and retain a pointer allowing the parent record information to be used in the child\'s record title, custom reports etc.</p>                
                popele = $(sdiv).appendTo(that.element);
            }
            
            var $dlg_pce = null;
            
            var btns = [
                    {text:window.hWin.HR('Proceed'),
                          click: function() { 
                          
                          var mode = popele.find('input[name="delete_mode"]:checked').val();     
                          if(mode==2){
                              //remove child record
                              var child_rec_to_delete = that.newvalues[input_id];
                              window.hWin.HAPI4.RecordMgr.remove({ids: child_rec_to_delete}, 
                                function(response){
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        
                                        var delcnt = response.data.deleted.length, msg = '';
                                        if(delcnt>1){
                                            msg = delcnt + ' records have been removed.';
                                            if(response.data.bkmk_count>0 || response.data.rels_count>0){
                                               msg = ' as well as '+
                                                (response.data.bkmk_count>0?(response.data.bkmk_count+' bookmarks'):'')+' '+
                                                (response.data.rels_count>0?(response.data.rels_count+' relationships'):'');
                                            }
                                        }else{
                                            msg = 'Child record has been removed';
                                        }
                                        window.hWin.HEURIST4.msg.showMsgFlash(msg, 2500);
                                        
                                        that._removeInput( input_id );
                                    }
                                });
                          } else {
                              that._removeInput( input_id );
                          }
                          
                          $dlg_pce.dialog('close'); 
                    }},
                    {text:window.hWin.HR('Cancel'),
                          click: function() { $dlg_pce.dialog('close'); }}
            ];            
            
            $dlg_pce = window.hWin.HEURIST4.msg.showElementAsDialog({
                window:  window.hWin, //opener is top most heurist window
                title: window.hWin.HR('Child record pointer removal'),
                width: 500,
                height: 300,
                element:  popele[0],
                resizable: false,
                buttons: btns
            });
        
    },

    //
    // assign title of resource record or file name or related entity
    //
    _findAndAssignTitle: function(ele, value, selector_function){
        
        var that = this;
        
        if(this.dtwidget!=null && ele[this.dtwidget]('instance')){
            res = ele[this.dtwidget]('findAndAssignTitle', value);
                    
        }else if(this.configMode.entity==='records'){     //RECORD
        
                var isChildRecord = that.f('rst_CreateChildIfRecPtr');
        
                //assign initial display value
                if(Number(value)>0){
                    var sTitle = null;
                    if(that.options.recordset){
                        var relations = that.options.recordset.getRelations();
                        if(relations && relations.headers && relations.headers[value]){
                            
                            sTitle = relations.headers[value][0];
                            
                            ele.empty();
                            window.hWin.HEURIST4.ui.createRecordLinkInfo(ele, 
                                            {rec_ID: value, 
                                             rec_Title: relations.headers[value][0], 
                                             rec_RecTypeID: relations.headers[value][1],
                                             rec_IsChildRecord: isChildRecord,
                                             rec_OwnerUGrpID: relations.headers[value][2],
                                             rec_NonOwnerVisibility: relations.headers[value][3]
                                             },
                                             selector_function);
                                             
                            //window.hWin.HEURIST4.ui.setValueAndWidth(ele, rec_Title);
                        }
                    }
                    if(!sTitle){
                        window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+value, w: "e", f:"header"},  //search for temp also
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    ele.empty();

                                    var recordset = new hRecordSet(response.data);
                                    if(recordset.length()>0){
                                        var record = recordset.getFirstRecord();
                                        var rec_Title = recordset.fld(record,'rec_Title');
                                        if(!rec_Title) {rec_Title = 'New record. Title is not defined yet.';}
                                        
                                        var rec_RecType = recordset.fld(record,'rec_RecTypeID');
                                        window.hWin.HEURIST4.ui.createRecordLinkInfo(ele, 
                                                {rec_ID: value, 
                                                 rec_Title: rec_Title, 
                                                 rec_RecTypeID: rec_RecType,
                                                 rec_IsChildRecord: isChildRecord
                                                 }, selector_function);
                                                 
                                       ele.show();
                                       ele.parent().find('.sel_link').show();
                                       ele.parent().find('.sel_link2').hide(); //hide big button to select new link
                                                 
                                    }else{
                                        //it was that._removeInput( ele.attr('id') );
                                        window.hWin.HEURIST4.ui.createRecordLinkInfo(ele, 
                                                {rec_ID: value, 
                                                 rec_Title: 'Target record '+value+' does not exist', 
                                                 rec_RecTypeID: 0,
                                                 rec_IsChildRecord: isChildRecord
                                                 }, selector_function);
                                        ele.show();
                                        ele.parent().find('.sel_link2').hide(); //hide big button to select new link
                                    }
                                    //window.hWin.HEURIST4.ui.setValueAndWidth(ele, rec_Title);
                                }
                            }
                        );
                    }
                    
                    
                }else{
                    window.hWin.HEURIST4.ui.setValueAndWidth(ele, '');
                }
                
                //hide this button if there are links
                if( ele.parent().find('.link-div').length>0 ){ 
                    ele.show();
                    ele.parent().find('.sel_link2').hide();
                }else{
                    ele.hide();
                    ele.parent().find('.sel_link2').show();
                }
                    
                
        }
        else{    
            //related entity                 
            if(window.hWin.HEURIST4.util.isempty(value)) value = [];
            value = Array.isArray(value)?value
                :((typeof  value==='string')?value.split(','):[value]);
                
            if(value.length==0){
                ele.empty();
                ele.hide();
                ele.parent().find('.sel_link').hide();
                ele.parent().find('.sel_link2').show();
                
            }else{
                window.hWin.HAPI4.EntityMgr.getTitlesByIds(this.configMode.entity, value,
                   function( display_value ){
                       ele.empty();
                       var hasValues = false;
                       if(display_value && display_value.length>0){
                           for(var i=0; i<display_value.length; i++){
                               if(display_value[i]){
                                    $('<div class="link-div">'+display_value[i]+'</div>').appendTo(ele);     
                                    hasValues = true;
                               }
                           }
                       }
                       if(hasValues){
                           ele.show();
                           ele.parent().find('.sel_link').show();
                           ele.parent().find('.sel_link2').hide();
                       }else{
                           ele.hide();
                           ele.parent().find('.sel_link').hide();
                           ele.parent().find('.sel_link2').show();
                       }
                       
                        ///var rec_Title  = display_value.join(',');           
                        //window.hWin.HEURIST4.ui.setValueAndWidth(ele, rec_Title, 10);
                   });
            }
        }
        
    },
    
    //
    //
    //
    _onTermChange: function( orig, data ){
        
        var $input = (orig.target)? $(orig.target): orig;
                
                if(! $input.attr('radiogroup')){
                
                    if($input.hSelect("instance")!=undefined){
                        
                        var opt = $input.find('option[value="'+$input.val()+'"]');
                        var parentTerms = opt.attr('parents');
                        if(parentTerms){
                            $input.hSelect("widget").find('.ui-selectmenu-text').html( parentTerms+'.'+opt.text() );    
                        }    
                           
                    }else{
                        //restore plain text value               
                        $input.find('option[term-view]').each(function(idx,opt){
                            $(opt).text($(opt).attr('term-view'));
                        });
                        
                        //assign for selected term value in format: parent.child 
                        var opt = $input.find( "option:selected" );
                        var parentTerms = opt.attr('parents');
                        if(parentTerms){
                             opt.text(parentTerms+'.'+opt.attr('term-orig'));
                        }
                    }
                }

                //hide individual error                
                $input.parent().find('.term-error-message').hide();

                this.onChange();
    },
    
    //
    // Open defTerms manager
    //
    _openManageTerms: function( vocab_id ){
        
        var that = this;
        
        var rg_options = {
            height:800, width:1300,
            selection_on_init: vocab_id,
            innerTitle: false,
            innerCommonHeader: $('<div>'
                +(that.options.dtID>0?('<span style="margin-left:260px">Field: <b>'+$Db.dty(that.options.dtID,'dty_Name')+'</b></span>'):'')
                +'<span style="margin-left:110px">This field uses vocabulary: <b>'+$Db.trm(vocab_id,'trm_Label')+'</b></span></div>'),
            onInitFinished: function(){
                var that2 = this;
                setTimeout(function(){
                    that2.vocabularies_div.manageDefTerms('selectVocabulary', vocab_id);
                },500);
            },
            onClose: function(){
                that._recreateEnumField(vocab_id);
            }
        };
        
        window.hWin.HEURIST4.ui.showEntityDialog('defTerms', rg_options);
    },

    //
    // recreate SELECT for enum/relation type
    //
    _recreateSelector: function($input, value){

        var that = this;

        if(value===true){
            //keep current
            value = ($input)?$input.val():null;
        }

        if($input) $input.empty();

        var allTerms = this.f('rst_FieldConfig');

        if(!window.hWin.HEURIST4.util.isempty(allTerms)){

            if($.isPlainObject(this.configMode))    { //this is not vocabulary ID, this is something more complex

                if(this.configMode.entity){ //this lookup for entity
                    
                    //create and fill SELECT
                    //this.configMode.entity
                    //this.configMode.filter_group
                    //if($input==null || $input.length==0) $input = $('<select>').uniqueId();

                    var selObj = window.hWin.HEURIST4.ui.createEntitySelector($input.get(0), this.configMode, 'select...', null);
                    window.hWin.HEURIST4.ui.initHSelect(selObj, false); 
                    
                    //add add/browse buttons
                    if(this.configMode.button_browse){

                    }
                
                }else{
                    //type: select, radio, checkbox
                    //hideclear   
                    //values                 
                    $input = window.hWin.HEURIST4.ui.createInputSelect($input, allTerms);
                    
                }
                

            }
            else{

                if (!Array.isArray(allTerms) && !window.hWin.HEURIST4.util.isempty(allTerms)) {
                    //is it CS string - convert to array
                    allTerms = allTerms.split(',');
                }

                if(window.hWin.HEURIST4.util.isArrayNotEmpty(allTerms)){
                    if(window.hWin.HEURIST4.util.isnull(allTerms[0]['key'])){
                        //plain array
                        var idx, options = [];
                        for (idx=0; idx<allTerms.length; idx++){
                            options.push({key:allTerms[idx], title:allTerms[idx]});
                        }
                        allTerms = options;
                    }
                    //add empty value as a first option
                    //allTerms.unshift({key:'', title:''});
                    
                    //array of key:title objects
                    //if($input==null) $input = $('<select>').uniqueId();
                    var selObj = window.hWin.HEURIST4.ui.createSelector($input.get(0), allTerms);
                    window.hWin.HEURIST4.ui.initHSelect(selObj, this.options.useHtmlSelect);

                    // move menuWidget to current dialog/document 
                    // (sometimes, within CMS pages for example, it places it before the current dialog thus hiding it)
                    let $menu = $input.hSelect('menuWidget');
                    let $parent_ele = this.element.closest('div[role="dialog"]'); //$input_div.parents('[role="dialog"]');
                    $parent_ele = $parent_ele.length == 0 ? document : $parent_ele;

                    if($parent_ele.length > 0) $menu.parent().appendTo($parent_ele);
                }
            }
            
            if(!window.hWin.HEURIST4.util.isnull(value)){
                
                if($($input).attr('radiogroup')){
                    $($input).find('input[value="'+value+'"]').attr('checked', true);
                }else {
                    $($input).val(value); 
                }
            }  
            if($($input).hSelect("instance")!=undefined){
                           $($input).hSelect("refresh"); 
            }

        }
        else{ //this is usual enumeration from defTerms
            
            //show error message on init -----------                   
            //ART0921 - todo in browseTerms
            var err_ele = $input.parent().find('.term-error-message');
            if(err_ele.length>0){
                err_ele.remove();
            }
            
            //value is not allowed
            if( !window.hWin.HEURIST4.util.isempty(allTerms) &&
                window.hWin.HEURIST4.util.isNumber(value) && $input.val()!=value){
                
                this.error_message.css({'font-weight': 'bold', color: 'red'});    
                var sMsg = null;
                var name = $Db.trm(value,'trm_Label');
                if(window.hWin.HEURIST4.util.isempty(name)){
                    //missed
                    sMsg = 'The term code '+value+' recorded for this field is not recognised. Please select a term from the dropdown.';
                }else{
                    //exists however in different vocabulary
                    //get name for this vocabulary
                    var vocName = $Db.trm(allTerms,'trm_Label');
                    //get name for term vocabulary
                    var vocId2 = $Db.getTermVocab(value);
                    var vocName2 = $Db.trm(vocId2, 'trm_Label');
                    //check that the same name vocabulary exists in this vocabualry
                    var code2 = $Db.getTermByLabel(allTerms, name);
                    
                    sMsg = '';
                    if(code2>0){
                        
                        sMsg = '<span class="heurist-prompt ui-state-error">'
                            +'This term references a duplicate outside the <i>'
                            +vocName+'</i> vocabulary used by this field. ';
                            
                        if(window.hWin.HAPI4.is_admin()){
                            sMsg = sMsg + '<a href="#" class="term-sel" '
                                +'data-term-replace="'+value+'" data-vocab-correct="'+allTerms
                                +'" data-vocab="'+vocId2+'" data-term="'+code2+'">correct</a></span>';
                        }else{
                            sMsg = sMsg 
                            +'</span><br><span>Either ask database manager to replace term for all records</span>';    
                        }

                    }else{
                        
                        sMsg = '<span class="heurist-prompt ui-state-error">'
                            +'This term is not in the <i>'+vocName+'</i> vocabulary used by this field. '                        
                        
                        if(window.hWin.HAPI4.is_admin()){
                            
                            sMsg = sMsg + '<a href="#" class="term-fix" '
                                +'data-term="'+value+'" data-vocab-correct="'+allTerms
                                +'" data-vocab="'+vocId2+'" data-dty-id="'+this.options.dtID
                                +'">correct</a></span>';
                        }else {
                            sMsg = sMsg + 
                            '.</span><br><span>Ask database manager to correct this vocabulary</span>';    
                        }
                    }
                    
                    var opt = window.hWin.HEURIST4.ui.addoption($input[0], value, '!!! '+name); 
                    $(opt).attr('ui-state-error',1);
                    $input.val(value);
                    $input.hSelect('refresh');
 
                    this.error_message.css({'font-weight': 'normal', color: '#b15f4e'}); 
                }

                if(!window.hWin.HEURIST4.util.isempty(sMsg)){
                    //add error message per every term
                    
                    err_ele = $( "<div>")
                        .addClass('term-error-message')
                        .html(sMsg)
                        .css({'height': 'auto',
                            'width': 'fit-content',
                            'padding': '0.2em',
                            'border': 0,
                            'margin-bottom': '0.2em'})
                        .appendTo( $input.parent() );
                    
                    err_ele.find('.ui-state-error')
                        .css({color:'red', //'#b36b6b',
                              background:'none',
                              border: 'none',
                             'font-weight': 'normal'        
                        });
                    
                
                    //this.showErrorMsg(sMsg);
                    

                    //this._on(this.error_message.find('.term-move'),{click:function(){}});
                    if(window.hWin.HAPI4.is_admin()){  

                        //
                        // select term (with the same name) in all fields
                        //
                        this._on(err_ele.find('.term-sel'),{click:function(e){
                            
                            var trm_id = $(e.target).attr('data-term');
                            var trm_id_re = $(e.target).attr('data-term-replace');
                            var fieldName = this.f('rst_DisplayName');
                            
                            var request = {a:'replace', rtyID:this.options.rectypeID,
                                dtyID:this.options.dtID, sVal:trm_id_re, rVal:trm_id, tag:0, recIDs:'ALL'};                
                                
                            window.hWin.HEURIST4.msg.showMsgDlg(
'<div  style="line-height:20px">'
+'<div>Term: <span id="termName" style="font-style:italic">'
    +$Db.trm(trm_id,'trm_Label')+'</span></div>'
+'<div>In vocabulary: <span id="vocabName" style="font-style:italic">'
    +$Db.trm($(e.target).attr('data-vocab'),'trm_Label')+'</span></div>'
+'<hr>'
+'<div>Vocabulary for this field is: <span id="vocabNameCorrect" style="font-style:italic">'
    +$Db.trm($(e.target).attr('data-vocab-correct'),'trm_Label')+'</span></div>'
+'<p>Use the version of the term in this vocabulary for this field in all records of this type</p></div>'
/*                                    'You are about to convert tag #'+trm_id_re+' to #'+trm_id
                                    +' in field "'+fieldName+'" for all records'
                                    + '<br><br>Are you sure?'*/,
                                    function(){
                                        window.hWin.HEURIST4.msg.bringCoverallToFront();                                             
                            
                                        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                                            window.hWin.HEURIST4.msg.sendCoverallToBack();
                                            if(response.status == window.hWin.ResponseStatus.OK){
                                                
                                                $input.val(trm_id);
                                                $input.hSelect('refresh');
                                                $input.trigger('change');
                                                
                                            }else{
                                                $('#div_result').hide();
                                                window.hWin.HEURIST4.msg.showMsgErr(response.message);
                                            }
                                        });
                                    },
                                    {title:'Correction of invalid term',yes:'Apply',no:'Cancel'});
            
                        }});
                    
                        this._on(err_ele.find('.term-fix'),{click:function(e){
                            //see manageDefTerms.js
                            var trm_ID = $(e.target).attr('data-term');
                            correctionOfInvalidTerm(
                                trm_ID,
                                $(e.target).attr('data-vocab'),
                                $(e.target).attr('data-vocab-correct'),
                                $(e.target).attr('data-dty-id'),
                                function(newvalue){ //callback
                                    window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
                                        { source:this.uuid, type:'trm' });    
                                    if(!(newvalue>0)) newvalue = trm_ID;
                                    that._recreateSelector($input, newvalue); //after correction of invalid term
                                }
                            );
                            
                        }});
                        
                    }//is_admin
                }
                
                
            }    
            //end of error messages ---------------
        }                                                                   
        
        return $input;
    },//_recreateSelector
    
    //
    //
    //
    showErrorMsg: function(sMsg){
        if(!window.hWin.HEURIST4.util.isempty(sMsg)){
            this.error_message.html(sMsg).show();    
        }else{
            this.error_message.hide();
            $(this.inputs).removeClass('ui-state-error');
            
            $(this.element).find('.ui-state-error').each(function(idx,item){
               if(!$(item).hasClass('heurist-prompt')){
                    $(item).removeClass('ui-state-error');    
               }
            });
            
        }
    },

    showValueErrors: function(errors){

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(errors)){
            return;
        }

        $.each(this.inputs, (idx, ele) => {

            if(idx >= errors.length){
                return false;
            }

            const err = errors[idx];
            if(window.hWin.HEURIST4.util.isempty(err)){
                return;
            }

            ele = $(ele).parents('.input-div');

            $('<div>', {class: `heurist-prompt ui-state-error`, style: 'margin-bottom: 0.2em; padding: 2px; width: fit-content;'})
                .text(err)
                .insertAfter(ele);
        });
    },
    
    //
    // internal - assign display value for specific input element
    //
    _clearValue: function(input_id, value, display_value){

        var that = this;
        $.each(this.inputs, function(idx, item){

            var $input = $(item);
            if($input.attr('id')==input_id){
                if(that.newvalues[input_id]){
                    that.newvalues[input_id] = '';
                }
                $input.removeAttr('data-value');
                
                if(that.dtwidget!=null && $input[that.dtwidget]('instance')){
                    $input[that.dtwidget]('clearValue'); //clear display value
                }else{
                    if(that.detailType=='resource'){
                        
                        $input.parent().find('.sel_link').hide();
                        $input.parent().find('.sel_link2').show();
                        $input.empty();
                        $input.hide();
                        
                    }else if(that.detailType=='relmarker'){    
                        this.element.find('.rel_link').show();
                    }else{
                        $input.val( display_value?display_value :value);    

                        if(that.detailType=='enum' || that.detailType=='relationtype'){    
                            //selectmenu
                            if($($input).hSelect("instance")!=undefined){
                               $($input).hSelect("refresh"); 
                            }

                            if(that.enum_buttons != null){
                                $input.parent().find('input:'+that.enum_buttons).prop('checked', false);
                            }
                        }
                    }
                
                    that.onChange();
                }
                
                return;
            }

        });

    },

    //
    // recreate input elements and assign values
    //
    setValue: function(values, make_as_nochanged){
        //clear ALL previous inputs
        this.input_cell.find('.input-div').remove();
        this.inputs = [];
        this.newvalues = {};
        
        if(!Array.isArray(values)) values = [values];

        var isReadOnly = this.isReadonly();
        
        var i;
        for (i=0; i<values.length; i++){
            if(isReadOnly && !(this.detailType=='relmarker' || this.detailType=='geo')){
                this._addReadOnlyContent(values[i]);
            }else{
                var inpt_id = this._addInput(values[i]);
            }
        }
        if (isReadOnly || (make_as_nochanged==true)) {
            this.options.values = values;
        }

        var repeatable = (Number(this.f('rst_MaxValues')) != 1);
        if(values.length>1 && !repeatable && this.f('rst_MultiLang')!=1){
            this.showErrorMsg('Repeated value for a single value field - please correct');
        }else{
            //this.showErrorMsg(null);
        }
        
        this._setAutoWidth();            
        
        if(window.hWin.HUL.isFunction(this.options.onrecreate)){
            this.options.onrecreate.call(this);
        }
        
        /*
        if(make_as_nochanged){
            this._setAutoWidth();            
        }else{
            this.onChange();
        }
        */
    },
    
    //
    // get value for particular input element
    //  input_id - id or element itself
    //
    _getValue: function(input_id){

        if(this.detailType=="relmarker") return null;
        
        var res = null;
        var $input = $(input_id);
        
        if(this.dtwidget!=null && $input[this.dtwidget]('instance')){
            res = $input[this.dtwidget]('getValue');
        }else
        if(this.detailType!="resource")
        {
            if($input.attr('radiogroup')>0){
                res = $input.find('input:checked').val();
            }else if(this.detailType=='boolean'){
                if(Array.isArray(this.configMode) && this.configMode.length==2) {
                    res = this.configMode[ $input.is(':checked')?0:1 ];
                }else{
                    res = $input.is(':checked') ?$input.val() :0;        
                }       
                
            }else{
                res = $input.val();
            }
            
            if(!window.hWin.HEURIST4.util.isnull(res) && res!=''){
                res = res.trim();

                // strip double spacing from freetext fields
                res = this.detailType == 'freetext' ? res.replaceAll(/  +/g, ' ') : res;
            }
        }else {
            res = this.newvalues[$input.attr('id')];    
            if(!res && $input.attr('data-value')){
                res = $input.attr('data-value');
            }
        }

        return res;
    },

    
    //
    //
    //
    getConfigMode: function(){
        return this.configMode;
    },

    setConfigMode: function(newval){
        return this.configMode = newval;
    },
    
    //
    //restore original order of repeatable elements
    //    
    _restoreOrder: function(){
        
        this.btn_cancel_reorder.hide();
        
        if(this.isReadonly()) return;
        var idx, ele_after = this.firstdiv; //this.error_message;
        for (idx in this.inputs) {
            var ele = this._getInputDiv(inputs[idx]);
            ele.insertAfter(ele_after);
            ele_after = ele;
        }    
    },
    
    //
    // returns individual visibilities (order is respected)
    //
    getVisibilities: function(){
        
        var ress2 = [];
        var visibility_mode = this.f('rst_NonOwnerVisibility');
        if(visibility_mode=='public' || visibility_mode=='pending')
        {
            var idx;
            var ress = {};
            
            
            for (idx in this.inputs) {
                var $input = this.inputs[idx];
                
                var val = this._getValue($input);
                if(!window.hWin.HEURIST4.util.isempty( val )){                 
                
                    let res = 0;
                    
                    var ele = this.element.find('span.field-visibility[data-input-id="'+$input.attr('id')+'"]');
                    res = (ele.attr('hide_field')=='1')?1:0; //1: hide this field from public
                                        
                    var ele = this._getInputDiv($input);
                    var k = ele.index();
                    ress[k] = res;
                }
            }
            
            ress2 = [];
            for(idx in ress){
                ress2.push(ress[idx]);
            }
        }
        
        return ress2;  
    },

    //
    // applies visibility status 
    //
    setVisibilities: function(vals){
        
        var vis_mode = this.f('rst_NonOwnerVisibility');
        
        if(this.options.showedit_button && this.detailType!="relmarker" && 
            !window.hWin.HEURIST4.util.isempty(vis_mode))
        {
            var idx, k=0;
            
            for (idx in this.inputs) {

                var $input = this.inputs[idx];
                var btn = this.element.find('span.field-visibility[data-input-id="'+$input.attr('id')+'"]');
                
                if(vals && k<vals.length && vals[k]==1){
                    btn.attr('hide_field',1);

                    this._setHiddenField($input, this.is_disabled);
                }else{
                    btn.attr('hide_field',0);
                }
                k++;
            }

            this._setVisibilityStatus();

        }else{
            this.element.find('span.field-visibility').hide();
            this.element.find('div.field-visibility2').hide();
        }
    },
    
    //
    //
    //
    getDetailType: function(){
        return this.detailType;
    },
    
    //
    //
    //
    isReadonly: function(){
        return this.options.readonly || this.f('rst_Display')=='readonly' || this.f('rst_MayModify')=='locked';
    },
    
    //
    // get all values (order is respected)
    //
    getValues: function( ){

        if(this.isReadonly()){
            return this.options.values;
        }else{
            var idx;
            var ress = {};
            var ress2 = [];
            
            for (idx in this.inputs) {
                var $input = this.inputs[idx];
                
                var res = this._getValue($input);

                if(!window.hWin.HEURIST4.util.isempty( res )){ 

                    if(this.options.is_between_mode){
                        var res2;
                        if(this.detailType=='date'){
                            res2 = this.newvalues[$input.attr('id')+'-2'];    
                        }else{
                            res2 = this.element.find('#'+$input.attr('id')+'-2').val();
                        }
                        if(window.hWin.HEURIST4.util.isempty( res2 )){ 
                            if(this.detailType!='date') res = '';
                        }else{
                            if(this.detailType=='date'){
                                res  = res+'/'+res2;
                            }else{
                                res  = res+'<>'+res2;   
                            }
                        }
                    }
                 
                    //to keep order                   
                    var ele = this._getInputDiv($input);
                    var k = ele.index();
                    
                    ress[k] = res;
                    //ress2.push(res);
                }
            }
            
            ress2 = [];
            for(idx in ress){
                ress2.push(ress[idx]);
            }
            if(ress2.length==0) ress2 = [''];//at least one empty value

            return ress2;
        }

    },
    
    _getInputDiv: function($input){
        if($input.hasClass('input-div')){
            return $input;
        }else{
            return $input.parents('.input-div');    
        }
    },
    
    _setHiddenField($input, is_hidden){
     
        if(is_hidden){
            $input.addClass('input-with-invisible-text');   
            if($input.is('select')){
                $input.nextAll('.ui-selectmenu-button').addClass('input-with-invisible-text');
            }
        }else{
            $input.removeClass('input-with-invisible-text');       
            if($input.is('select')){
                $input.nextAll('.ui-selectmenu-button').removeClass('input-with-invisible-text');
            }
        }
        
    },

    
    //
    //
    //
    setDisabled: function(is_disabled){
        //return;
        if(!this.isReadonly()){
            
            var check_ind_visibility = this.options.showedit_button 
                    && this.detailType!="relmarker"
                    && !window.hWin.HEURIST4.util.isempty(this.f('rst_NonOwnerVisibility'));
            
            var idx;
            for (idx in this.inputs) {
                if(!this.isFileForRecord) { 
                    var input_id = this.inputs[idx];
                    var $input = $(input_id);
                    window.hWin.HEURIST4.util.setDisabled($input, is_disabled);
                    
                    if(check_ind_visibility){
                        var btn = this.element.find('span.field-visibility[data-input-id="'+$input.attr('id')+'"]');

                        this._setHiddenField($input, (is_disabled && btn.attr('hide_field')==1));
                    }
                }
            }
            this.is_disabled = is_disabled;
            
            if(this.input_cell.sortable('instance')){
               this.input_cell.sortable('option', 'disabled', is_disabled );
            }
        }

    },
    
    //
    //
    //
    isChanged: function(value){

        if(value===true){
            this.options.values = [''];
            
            return true;
        }else{

            if(this.isReadonly()){
                return false;
            }else{
                if(this.options.values.length!=this.inputs.length){
                    return true;
                }
                
                var idx;
                for (idx in this.inputs) {
                    var res = this._getValue(this.inputs[idx]);
                    //both original and current values are not empty
                    if (!(window.hWin.HEURIST4.util.isempty(this.options.values[idx]) && window.hWin.HEURIST4.util.isempty(res))){
                        
                        if($.isPlainObject(this.options.values[idx]) && $.isPlainObject(res)){
                            return JSON.stringify(this.options.values[idx]) !== JSON.stringify(res);
                        }else
                        if (this.options.values[idx]!=res){
                            return true;
                        }
                    }
                }
            }

            return false;
        }
    },

    //
    //   Restore values
    //    
    setUnchanged: function(){
        
        if(this.isReadonly()) return;
        
        this.options.values = [];
                
        var idx;
        for (idx in this.inputs) {
            this.options.values.push(this._getValue(this.inputs[idx]));
        }
    },
    
    //
    // returns array of input elements
    //
    getInputs: function(){
        return this.inputs;
    },
    
    //
    // execute method for editInputXXX widget
    //
    actionInput: function(args){
        if(this.inputs && this.inputs[0] && this.dtwidget!=null && args && args.length>0){
            let input = this.inputs[0];    
            if (input[this.dtwidget]('instance')){
                input[this.dtwidget](args);                
            }
        }
    },
    
    //
    //
    //
    validate: function(){

        if (this.f('rst_Display')=='hidden' || this.isReadonly()) return true;
        
        var req_type = this.f('rst_RequirementType');
        var max_length = this.f('dty_Size');
        var data_type = this.f('dty_Type');
        var errorMessage = '';

        if(req_type=='required'){
            
            if(data_type=='relmarker'){
                    if(this.element.find('.link-div').length==0){
                        $(this.inputs[0]).addClass( "ui-state-error" );
                        //add error message
                        errorMessage = 'Define a relationship. It is required.';
                    }
            }else{
                var ress = this.getValues();

                if(ress.length==0 || window.hWin.HEURIST4.util.isempty(ress[0]) || 
                    ($.isPlainObject(ress[0]) &&  $.isEmptyObject(ress[0])) || 
                    ( (typeof ress[0] ==='string') && ress[0].trim()=='')) {
                    
                    
                    if( data_type=='file' && !this.isFileForRecord && this.entity_image_already_uploaded){
                        //special case for entity image
                        
                    }else{
                    
                        //error highlight
                        $(this.inputs[0]).addClass( "ui-state-error" );
                        //add error message
                        errorMessage = 'Field is required';
                    }

                }else if((data_type=='freetext' || data_type=='url' || data_type=='blocktext') && ress[0].length<4){
                    //errorMessage = 'Field is required';
                }
            }
        }
        //verify max alowed size
        if(max_length>0 &&
            (data_type=='freetext' || data_type=='url' || data_type=='blocktext')){

            var idx;
            for (idx in this.inputs) {
                var res = this._getValue(this.inputs[idx]);
                if(!window.hWin.HEURIST4.util.isempty( res ) && res.length>max_length){
                    //error highlight
                    $(this.inputs[idx]).addClass( "ui-state-error" );
                    //add error message
                    errorMessage = 'Value exceeds max length: '+max_length;
                }
            }
        }
        if(data_type=='integer' || this.detailType=='year'){
            //@todo validate 
            
        }else if(data_type=='float'){
            
            
        }else if(data_type=='resource'){
            
            var ptrset = this._prepareIds(this.f('rst_PtrFilteredIDs'));
            
            var idx, snames = [];
            if(ptrset.length>0){
                for (idx in ptrset) {
                    snames.push($Db.rty(ptrset[idx],'rty_Name'));
                }
            }
            snames = snames.join(', ');
            
            for (idx in this.inputs) {
                var res = this._getValue(this.inputs[idx]);
                //check record type
                var rty_ID = $(this.inputs[idx]).find('.related_record_title').attr('data-rectypeid')
                
                if(rty_ID>0  && ptrset.length>0 && 
                    window.hWin.HEURIST4.util.findArrayIndex(rty_ID, ptrset)<0)
                {
                    //error highlight
                    $(this.inputs[idx]).addClass( "ui-state-error" );
                    //add error message
                    errorMessage = 'Target type "'+$Db.rty(rty_ID,'rty_Name')+'" is not allowed.'
                    +' Field expects target type'+((ptrset.length>1)?'s ':' ')+snames;
                    
                }
            }
            
        }
        

        this.showErrorMsg(errorMessage);

        return (errorMessage=='');
    },

    //
    //
    //
    focus: function(){
        if(!this.isReadonly() && this.inputs && this.inputs.length>0 
            && $(this.inputs[0]).is(':visible') 
            && !$(this.inputs[0]).hasClass('ui-state-disabled') )
        {
            $(this.inputs[0]).trigger('focus');   
            return $(this.inputs[0]).is(':focus');
        } else {
            return false;
        }
    },

    //
    //
    //
    _addReadOnlyContent: function(value, idx) {

        var disp_value ='';
        

        var $inputdiv = $( "<div>" ).addClass('input-div')
                .css({'font-weight':'bold','padding-top':'4px'})
                .insertBefore(this.input_prompt);

        var dwidth = this.f('rst_DisplayWidth');
        if (parseFloat( dwidth ) > 0 
            &&  this.detailType!='boolean' && this.detailType!='date' && this.detailType!='resource' ) {
             $inputdiv.css('max-width', Math.round(2 + Math.min(80, Number(dwidth))) + "ex");
        }
                
        if(Array.isArray(value)){

            disp_value = value[1]; //record title, relation description, filename, human readable date

        }else if(this.detailType=="enum" || this.detailType=="relationtype"){

            disp_value = $Db.getTermValue(value);

            if(window.hWin.HEURIST4.util.isempty(value)) {
                disp_value = 'No value'; //'term missing. id '+value
            }
        } else if(this.detailType=='file'){

            $inputdiv.addClass('truncate').css({'max-width':'400px'});
            
            this._findAndAssignTitle($inputdiv, value);
            return;

        } else if(this.detailType=="resource"){

            $inputdiv.html("....resource "+value);

            this._findAndAssignTitle($inputdiv, value);
            return;

        } else if(this.detailType=="relmarker"){  //combination of enum and resource

            disp_value = ''; //not used 

        } else if(this.detailType=="url"){

            var def_value = this.f('rst_DefaultValue');
            if(window.hWin.HEURIST4.util.isempty(value)) value = def_value;
            
            if(!window.hWin.HEURIST4.util.isempty(value) &&
               !(value.indexOf('http://')==0 || value.indexOf('https://')==0)){
                value = 'https://'+ value;
            }
            disp_value = '<a href="'+value+'" target="_blank" title="'+value+'">'+value+'</a>';
            
            $inputdiv.addClass('truncate').css({'max-width':'400px'});
        }else{
            disp_value = value;
            
            $inputdiv.addClass('truncate').css({'max-width':'400px'});
        }

        if(this.detailType=="blocktext"){
            this.input_cell.css({'padding-top':'0.4em'});
        }

        $inputdiv.html(disp_value);

    },//_addReadOnlyContent
    
    //
    //
    //
    _prepareIds: function(ptrset)
    {
        if(!Array.isArray(ptrset)){
            if(window.hWin.HEURIST4.util.isempty(ptrset)){
                ptrset = [];
            }else if(window.hWin.HEURIST4.util.isNumber(ptrset)){
                ptrset = [ptrset];
            }else
                ptrset = ptrset.split(',')
        }
        return ptrset;
    },
    

    //
    //
    //
    setBetweenMode: function(mode_val){
        
        if(this.options.is_faceted_search && 
           this.options.is_between_mode!=mode_val && 
                (this.detailType=='freetext' || this.detailType=='date'
                || this.detailType=='integer' || this.detailType=='float')){
            
           this.options.is_between_mode = mode_val;
           
           if(this.options.is_between_mode){
                this.addSecondInput();           
           }else{
               var that = this;
               this.element.find('.span-dash').remove();
               $.each(this.inputs, function(idx, item){
                    var id = $(item).attr('id')+'-2';
                    that.element.find('#'+id).remove();
                    if(that.detailType=='date') {
                        that.element.find('input[data-picker="'+id+'"]').remove();
                        that.element.find('span[data-picker="'+id+'"]').remove();
                    }
               });
           }
        }
    },
    
    //
    //
    //
    addSecondInput: function(input_id){

        var that = this;
        $.each(this.inputs, function(idx, item){

            var $input = $(item);
            if(input_id==null || $input.attr('id')==input_id){
                
                var $inputdiv = that._getInputDiv($input);
                
                var edash = $('<span class="span-dash">&nbsp;-&nbsp;</span>')
                //duplicate input for between mode
                if(that.detailType=='date') {
                    
                    
                    //var dpicker = that.element.find('input[data-picker="'+$input.attr('id')+'"]');
                    var dpicker_btn = that.element.find('span[data-picker="'+$input.attr('id')+'"]');
                    
                    edash.insertAfter(dpicker_btn);
                    
                    var inpt2 = $('<input>').attr('id',$input.attr('id')+'-2')
                            .addClass('text ui-widget-content ui-corner-all')
                            .on('change',function(){
                                that.onChange();
                            })
                            .insertAfter(edash);
                            
                    window.hWin.HEURIST4.ui.disableAutoFill( inpt2 );
                            
                    that._createDateInput(inpt2, $inputdiv);
            
                    /*
                    var opts = window.hWin.HEURIST4.util.cloneJSON(that.options);
                    opts.showclear_button = false;
                    opts.is_between_mode = false;
                    opts.suppress_repeat = false;
                    $('<div>').attr('id',$input.attr('id')+'-2').editing_input(that.options).insertAfter(edash);    
                    */
                }else{
                    edash.insertAfter($input);
                    
                    $input.css({'max-width':'20ex','min-width':'20ex'});   
                    
                    $input.clone(true).attr('id',$input.attr('id')+'-2').insertAfter(edash);
                }
                if(input_id!=null){
                    return false;
                }
            }
        });
        
    },
	
    
	//
	// Recreate dropdown or checkboxes|radio buttons, called by adding new term and manage terms onClose
	//
	_recreateEnumField: function(vocab_id){

        var that = this;

        this.child_terms = $Db.trm_TreeData(vocab_id, 'set'); //refresh
        var asButtons = this.options.recordset && this.options.recordset.entityName=='Records' && this.f('rst_TermsAsButtons') == 1;

        if(asButtons && this.child_terms.length <= 20){ // recreate buttons/checkboxes

            this.enum_buttons = (Number(this.f('rst_MaxValues')) != 1) ? 'checkbox' : 'radio';
            var dtb_res = this._createEnumButtons(true);

            if(dtb_res){

                // Change from select to input text
                $.each(this.inputs, function(idx, input){

                    var $input = $(input);
                    var value = $input.val();
                    var inpt_id = $input.attr('id');

                    if($input.is('select')){

                        if($input.hSelect('instance') != undefined){
                            $input.hSelect('destroy');
                        }
                        that._off($input, 'change');
                        var $inputdiv = $input.parent();
                        $input.remove();

                        $input = $('<input type="text" class="text ui-widget-content ui-corner-all">')
                                    .attr('id', inpt_id)
                                    .val(value)
                                    .prependTo($inputdiv)
                                    .hide();

                        $inputdiv.find('input[data-id="'+ value +'"]').prop('checked', true);

                        that._on( $input, {change:that.onChange} );

                        that.inputs[idx] = $input;

                        if(idx != 0){
                            $inputdiv.hide();
                        }

                        if(that.btn_add){
                            that.btn_add.hide(); // Hide repeat button, removeClass('smallbutton ui-icon-circlesmall-plus')
                        }
                    }
                });
            }
        }else{

            this.enum_buttons = null;

            $.each(this.inputs, function(idx, input){ 

                var $input = $(input);
                var value = $input.val();

                if($input.is('input')){

                    that._off($input, 'change');
                    var $inputdiv = $input.parent();
                    if(idx == 0){
                        $inputdiv.find('label.enum_input, br').remove();
                        $inputdiv.find('.smallicon').css({'top': '', 'margin-top': '2px'});
                    }
                    $input.remove();

                    $input = $('<select>')
                                .attr('id', inpt_id)
                                .addClass('text ui-widget-content ui-corner-all')
                                .prependTo( $inputdiv );

                    $inputdiv.show();

                    that.inputs[idx] = $input;

                    if(that.btn_add){
                        that.btn_add.show(); // Show repeat button, removeClass('smallbutton ui-icon-circlesmall-plus')
                    }
                }

                if(window.hWin.HEURIST4.util.isempty(value) && value != ''){
                    value = true;
                }

                $input.css('width','auto');
                
                if(window.hWin.HEURIST4.util.isempty(that.f('rst_FieldConfig'))) {


                    if(that.selObj) {
                        that.selObj.remove();    
                        that.selObj = null;
                    }
                    browseTerms(that, $input, value);
                    
                }else{
                    $input = that._recreateSelector($input, value); //in _recreateEnumField
                    $input.hSelect('widget').css('width','auto');
                }
                
                that._on( $input, {change:that._onTermChange} );
            });//each
        }

        
        if(that.input_cell.find('.ui-icon-image').length>0){ //if edit allowed
            this._checkTermsWithImages();    
        }
    },

    
    //
    // Show/Hide select by picture  
    //
    _checkTermsWithImages: function(){ 
                           
        this._enumsHasImages = false;
        
        if(this.child_terms.length>0){

                var trm_img_req = {
                    'a': 'search',
                    'entity': 'defTerms',
                    'details': 'list',
                    'trm_ID': this.child_terms.join(','),
                    'withimages': 1,
                    'request_id': window.hWin.HEURIST4.util.random()
                };

                var that = this;

                window.hWin.HAPI4.EntityMgr.doRequest(trm_img_req, function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        var recset = new hRecordSet(response.data);
                        that._enumsHasImages = (recset.length() > 0);
                        if(that._enumsHasImages){
                            that.input_cell.find('.ui-icon-image').show();
                        }else{
                            that.input_cell.find('.ui-icon-image').hide();
                        }
                    }
                });
                
          }

          this.input_cell.find('.ui-icon-image').hide();
    },

    
    //
    // Set up checkboxes/radio buttons for enum field w/ rst_TermsAsButtons set to 1
    // Params:
    //	isRefresh (bool): whether to clear $inputdiv first
    //	terms_list (array): array of term ids
    //	$inputdiv (jQuery Obj): element where inputs will be placed
    //	values (array): array of existing values to check by default
    //
    _createEnumButtons: function(isRefresh, $inputdiv, values){

        var that = this;
        
        var terms_list = this.child_terms;

        if($inputdiv == null){
            $inputdiv = $(this.inputs[0]).parent();
        }
        if(values == null){

            values = [];

            $.each(that.inputs, function(idx, ele){
                var $ele = $(ele);

                values.push($ele.val());
            });
        }

        if(window.hWin.HEURIST4.util.isempty(terms_list) || window.hWin.HEURIST4.util.isempty($inputdiv)){
            // error
            return false;
        }

        if(isRefresh){

            var $eles = $(this.inputs[0]).parent().find('label.enum_input, br');

            if($eles.length > 0){
                $eles.remove();
            }
        }

        // input div's width
        var f_width = parseInt(this.f('rst_DisplayWidth'));
        f_width = (window.hWin.HEURIST4.util.isempty(f_width) || f_width < 100) ? 110 : f_width + 10; // +10 for extra room

		//var labelWidth = 25; // label+input width
        //var taken_width = this.input_cell.parent().find('span.editint-inout-repeat-button').width() + this.input_cell.parent().find('div.header').width() + 20;
        //var row_limit = Math.floor(f_width / labelWidth);

        $inputdiv.css({'max-width': (f_width + 20) + 'ex', 'min-width': f_width + 'ex'});

        for(var i = 0; i < terms_list.length; i++){

            var trm_label = $Db.trm(terms_list[i], 'trm_Label');
            var trm_id = terms_list[i];
            var isChecked = (values && values.includes(trm_id)) ? true : false;

            var $btn = $('<input>', {'type': this.enum_buttons, 'title': trm_label, 'value': trm_id, 'data-id': trm_id, 'checked': isChecked, name: this.options.dtID})
                .on('change',function(event){ 

                    var isNewVal = false;
                    var changed_val = $(event.target).val();

                    if($(event.target).is(':checked')){
                        isNewVal = true;
                    }

                    if(that.enum_buttons == 'radio'){

                        if(isNewVal){
                            $(that.inputs[0]).val(changed_val);
                        }else{
                            $(that.inputs[0]).val('');
                        }
                    }else{

                        if(!isNewVal){

                            if(that.inputs.length == 1){
                                $(that.inputs[0]).val('');
                            }else{
                                $.each(that.inputs, function(idx, ele){

                                    var $ele = $(ele);

                                    if($ele.val() == changed_val){

                                        if(idx != 0){
                                            $ele.parents('.input-div').remove();
                                            that.inputs.splice(idx, 1);

                                            return false;
                                        }else{

                                            var last_idx = that.inputs.length - 1;
                                            var $last_ele = $(that.inputs[last_idx]);

                                            $(that.inputs[0]).val($last_ele.val());

                                            that._getInputDiv($last_ele).remove();
                                            that.inputs.splice(last_idx, 1);

                                            return false;
                                        }
                                    }
                                });
                            }
                        }else{

                            that.new_value = changed_val;

                            that.btn_add.trigger('click');
                        }
                    }

                    that.onChange();
                });

            $('<label>', {'title': trm_label, append: [$btn, trm_label]})
                    .addClass('truncate enum_input')
                    .css({
                        'max-width': '120px',
                        //'min-width': '120px',
                        'display': 'inline-block',
                        'margin-right': '15px'
                    })
                    .appendTo($inputdiv);
        }

        var $other_btns = $inputdiv.find('.smallicon, .smallbutton');

        if($other_btns.length > 0){
            $other_btns.appendTo($inputdiv);

            $other_btns.filter('.smallicon').css({'top': '-4px', 'margin-top': ''});
        }

        return true;
    },

    //
    // In preparation for creating a new relationship marker from an external lookup
    //
    setup_Relmarker_Target: function(target_id, relation_value, callback){

        target_id = parseInt(target_id, 10);

        if(isNaN(target_id) || target_id < 1){
            return;
        }

        this._external_relmarker.target = target_id;

        this._external_relmarker.relation = relation_value;

        this._external_relmarker.callback = callback;
    },
    
});