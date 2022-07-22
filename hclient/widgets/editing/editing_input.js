/**
* Widget for input controls on edit form
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
        is_between_mode:false    //duplicate input for freetext and dates for search mode 
    },

    //newvalues:{},  //keep actual value for resource (recid) and file (ulfID)
    detailType:null,
    configMode:null, //configuration settings, mostly for enum and resource types (from field rst_FieldConfig)
    customClasses:null, //custom classes to manipulate visibility and styles in editing
       
    isFileForRecord:false,
    entity_image_already_uploaded: false,

    enum_buttons:null, // null = dropdown/selectmenu/none, radio or checkbox

    is_disabled: false,
    new_value: '', // value for new input

    linkedImgInput: null, // invisible textbox that holds icon/thumbnail value
    linkedImgContainer: null, // visible div container displaying icon/thumbnail
    
    // the constructor
    _create: function() {

        //for recDetails field description can be taken from $Db.rst
        if(this.options.dtFields==null && this.options.dtID>0 && this.options.rectypeID>0) //only for recDetails
        {
            this.options.dtFields = window.hWin.HEURIST4.util.cloneJSON($Db.rst(this.options.rectypeID, this.options.dtID));
        
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

        var that = this;

        var required = "";
        if(this.options.readonly || this.f('rst_Display')=='readonly') {
            required = "readonly";
        }else{
            if(!this.options.suppress_prompts && this.f('rst_Display')!='hidden'){
                required = this.f('rst_RequirementType');
            }
        }
        
        var lblTitle = (window.hWin.HEURIST4.util.isempty(this.options.title)?this.f('rst_DisplayName'):this.options.title);

        //header
        if(true || this.options.show_header){
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
        
        var is_sortable = false;
       
        //repeat button        
        if(this.options.readonly || this.f('rst_Display')=='readonly') {

            //spacer
            $( "<span>")
            .addClass('editint-inout-repeat-button')
            .css({'min-width':'22px', display:'table-cell'})
            .appendTo( this.element );

        }else{

if(that.options.dtID==1) 
console.log('>>>> '+that.options.dtID+'  '+lblTitle);
            //hardcoded list of fields and record types where multivalues mean translation (multilang support)
            var is_translation = this.f('rst_MultiLang') || 
               (that.options.rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU']
                && that.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME']);
            
            //saw TODO this really needs to check many exist
            var repeatable = (Number(this.f('rst_MaxValues')) != 1  || is_translation)? true : false;
            
            if(!repeatable || this.options.suppress_repeat){  
                //spacer
                $( "<span>")
                .addClass('editint-inout-repeat-button')
                .css({'min-width':'22px', display:'table-cell'})
                .appendTo( this.element );
                
            }else{ //multiplier button
            
                is_sortable = !that.options.is_faceted_search; 
            
                var btn_cont = $('<span>')
                    .css({display:'table-cell', 'vertical-align':'top', //'padding-top':'2px',
                            'min-width':'22px',  'border-color':'transparent'})
                    .appendTo( this.element );
                    
                this.btn_add = $( "<span>")
                    .addClass('smallbutton editint-inout-repeat-button ui-icon ui-icon-'
                        +(is_translation?'translate':'circlesmall-plus'))
                    .appendTo( btn_cont )
                //.button({icon:"ui-icon-circlesmall-plus", showLabel:false, label:'Add another ' + lblTitle +' value'})
                .attr('tabindex', '-1')
                .attr('title', 'Add another ' + lblTitle +(is_translation?' translation':' value' ))                    
                .css({display:'block', 
                'font-size': (is_translation?'1em':'1.9em'), cursor:'pointer', 
                //'vertical-align':'top', //'padding-top':'2px',
                    'min-width':'22px','margin-top': '5px',
//outline_suppress does not work - so list all these props here explicitely                
                    outline: 'none','outline-style':'none', 'box-shadow':'none'
                });
                
                if(this.detailType=="blocktext"){
                    this.btn_add.css({'margin-top':'3px'});    
                }
                
                //this.btn_add.find('span.ui-icon').css({'font-size':'2em'});
                
                // bind click events
                this._on( this.btn_add, {
                    click: function(){

                        if(this.is_disabled) return;

                        if(is_translation){
                            
                            if(typeof translationSupport!=='undefined' && $.isFunction(translationSupport)){
                                __show_select_function = translationSupport(this); //see editing_exts
                            }
                            
                        }else{
                            
                            if(window.hWin.HEURIST4.util.isempty(this.new_value) && this.new_value != '') this.new_value = '';

                            if( !(Number(this.f('rst_MaxValues'))>0)  || this.inputs.length < this.f('rst_MaxValues')){
                                this._addInput(this.new_value);
                                this._refresh();
                                
                                if($.isFunction(this.options.onrecreate)){
                                    this.options.onrecreate.call(this);
                                }
                            }
                            
                        }
                }});
            }
            
            
            if(this.options.dtID != 'rst_DefaultValue_resource'){
                if(this.detailType=="resource" && this.configMode.entity=='records'){
                    
                    $('<div style="float:right;padding-top:1px;width: 14px;"><span class="ui-icon ui-icon-triangle-1-e"/></div>')                
                        .appendTo( this.header );
                        this.header.css({'padding-right':0, width:154});
                        this.header.find('label').css({display:'inline-block', width: 135});
                        
                }else if(this.detailType=="relmarker"){
                    
                    $('<div style="float:right;padding-top:1px;width: 14px;"><span style="font-size:11px" class="ui-icon ui-icon-triangle-2-e-w"/></div>')                
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
        if(is_sortable){
                this.input_cell.sortable({
                    //containment: "parent",
                    delay: 250,
                    items: '.input-div',
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
        var extend_help_text = this.f('rst_DisplayExtendedDescription');
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
        
        if( !window.hWin.HEURIST4.util.isArray(this.options.values) ){
            var def_value = this.f('rst_DefaultValue');
            
            var isparententity = (this.f('rst_CreateChildIfRecPtr')==1);

            if( !this.options.is_insert_mode || window.hWin.HEURIST4.util.isempty(def_value) || isparententity){
                // reset default value - default value for new record only
                // do not assign default values in edit mode                
                values_to_set = [''];        
            }else if(window.hWin.HEURIST4.util.isArray(def_value)){
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
                  this.options.values = [0];
                  return;
            }
            
        }else if(this.detailType=='file' || this.detailType=='geo'){
            values_to_set = this.options.values;
        }else {
            values_to_set = this.options.values; //window.hWin.HEURIST4.util.uniqueArray(this.options.values); //.slice();//.unique();
        }
        
        //recreate input elements and assign given values
        this.setValue(values_to_set);
        this.options.values = this.getValues();
        this._refresh();
    }, //end _create

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
        
        if(this.options.show_header){
            this.header.css('display','table-cell');//show();
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
        var that = this;
        if(this.inputs){
            $.each(this.inputs, function(index, input){ 

                    if(that.detailType=='blocktext'){
                        var eid = '#'+input.attr('id')+'_editor';
                        //tinymce.remove('#'+input.attr('id')); 
                        tinymce.remove(eid);
                        $(eid).parent().remove(); //remove editor element
                        //$(eid).remove(); 
                        
                    }else if(that.detailType=='file'){
                        if($(input).fileupload('instance')!==undefined) $(input).fileupload('destroy');
                    }else{
                        if($(input).hSelect('instance')!==undefined) $(input).hSelect('destroy');
                    }
                    //check for "between" input
                    that.element.find('#'+$(input).attr('id')+'-2').remove();
                    
                    input.remove();
            } );
            this.input_cell.remove();
        }
    },

    /**
    * get value for given record type structure field
    *
    * dtFields - json with parameters that describes this input field
    *            for recDetails it is taken from $Db.rst for other entities from config files in hsapi/entities
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

        if(this.inputs.length>1 && this.enum_buttons == null){

            //find in array
            $.each(this.inputs, function(idx, item){

                var $input = $(item);
                if($input.attr('id')==input_id){
                    if(that.newvalues[input_id]){
                        delete that.newvalues[input_id];
                    }
                    
                    if(that.detailType=='file'){
                        if($input.fileupload('instance')){
                            $input.fileupload('destroy');
                        }
                        var $parent = $input.parents('.input-div');
                        $input.remove();
                        $parent.remove();
                        
                        that.entity_image_already_uploaded = false;
                    }else{
                        if($input.hSelect('instance')!==undefined) $input.hSelect('destroy');

                        //remove element
                        $input.parents('.input-div').remove();
                    }
                    //remove from array
                    that.inputs.splice(idx,1);
                    that.onChange();
                    return;
                }

            });

        }else if(this.inputs.length >= 1 && this.enum_buttons == 'checkbox'){ // uncheck all checkboxes

            var $input;

            $(this.inputs[0]).val(''); // Set first value to empty

            if(this.inputs.length > 1){

                for (var i = 1; i < this.inputs.length; i++) {
                    
                    $input = $(this.inputs[i]);

                    this._off($input, 'change');

                    $input.parents('.input-div').remove();

                    that.inputs.splice(i,1);
                    i--;
                }
            }

            $(this.inputs[0]).parents('.input-div').find('input[type="checkbox"]').prop('checked', false);

            that.onChange();
        }else{  //and clear last one
            this._clearValue(input_id, '');
        }
        
    },
    
    _setAutoWidth: function(){

        if(this.options.is_faceted_search) return;
        
        var that = this; 
        //auto width
        if ( this.detailType=='freetext' || this.detailType=='integer' || 
             this.detailType=='float' || this.detailType=='url' || this.detailType=='file'){
            $.each(this.inputs, function(index, input){ 
                var ow = $(input).width(); //current width
                if(ow<580){
                    var nw = ($(input).val().length+3)+'ex';
                    $(input).css('width', nw);
                    if($(input).width()<ow) $(input).width(ow); //we can only increase - restore
                    else if($(input).width()>600){
                        if($(input).parents('fieldset').width()>0){
                            $(input).width($(input).parents('fieldset').width()-20);    
                        }else{
                            $(input).width(600); 
                        }
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
    
        this.showErrorMsg(null);
        
        this._setAutoWidth();
        
        if($.isFunction(this.options.change)){
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

            $input = $( "<textarea>",{rows:2}) //min number of lines
            .uniqueId()
            .val(value)
            .addClass('text ui-widget-content ui-corner-all')
            .css({'overflow-x':'hidden'})
            .keydown(function(e){
                if (e.keyCode == 65 && e.ctrlKey) {
                    e.target.select()
                }    
            })
            .keyup(function(){that.onChange();})
            .change(function(){that.onChange();})
            .appendTo( $inputdiv );

            //IJ 2021-09-09 - from now dheight is max height in lines - otherwise the height is auto
            function __adjustTextareaHeight(){
                $input.attr('rows', 2);
                var dheight = that.f('rst_DisplayHeight');  //max height 
                var lht = parseInt($input.css('lineHeight'),10);
                if(!(lht>0)) lht = parseInt($input.css('font-size'))*1.3;
                var cnt = ($input.prop('scrollHeight') / lht).toFixed();            
                if(cnt>0){
                    if(cnt>dheight && dheight>2){
                        $input.attr('rows', dheight);    
                    }else{
                        $input.attr('rows', cnt);        
                    }
                }
            }
            
            //count number of lines
            setTimeout(__adjustTextareaHeight, 1000);
            
            if( this.options.dtID != window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY']
            //&& this.options.dtID != window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_WORLDFILE']
            && this.options.dtID > 0)
            {
                
                var eid = $input.attr('id')+'_editor';
                
                //hidden textarea for tinymce editor
                $editor = $( "<textarea>")
                .attr("id", eid)
                //.addClass('text ui-widget-content ui-corner-all')
                .css({'overflow':'auto',display:'flex',resize:'both'})
                .appendTo( $('<div>').css({'display':'inline-block'}).appendTo($inputdiv) );
                $editor.parent().hide();

                var $btn_edit_switcher;

                if(this.options.recordset && this.options.recordset.entityName == 'Records'){

                    var $clear_container = $('<span id="btn_clear_container"></span>').appendTo( $inputdiv );

                    $btn_edit_switcher = $('<div>').appendTo( $inputdiv );

                    $('<span>text</span>')
                        .attr('title', 'plain text or source, showing markup')
                        .addClass('smallbutton')
                        .css({cursor: 'pointer', 'text-decoration': 'underline'})
                        .appendTo($btn_edit_switcher);

                    $('<span>wysiwyg</span>')
                        .attr('title', 'rendering of the text, taken as html')
                        .addClass('smallbutton')
                        .css({cursor: 'pointer', 'margin-left': '10px'})
                        .appendTo($btn_edit_switcher);

                    $('<span>table</span>')
                        .attr('title', 'treats the text as a table/spreadsheet and opens a lightweight spreadsheet editor')
                        .addClass('smallbutton')
                        .css({cursor: 'pointer', 'margin-left': '10px'})
                        .hide() // currently un-available
                        .appendTo($btn_edit_switcher);
                }else{
                    $btn_edit_switcher = $( '<span>wysiwyg</span>', {title: 'Show/hide Rich text editor'})
                        //.addClass('smallicon ui-icon ui-icon-gear btn_add_term')      btn_add_term
                        .addClass('smallbutton')
                        .css({'line-height': '20px','vertical-align':'top', cursor:'pointer','text-decoration':'underline'})
                        .appendTo( $inputdiv );
                }

                function __showEditor(is_manual){

                    var eid = '#'+$input.attr('id')+'_editor';

                    $(eid).parent().css({display:'inline-block'}); //.height($input.height()+100)
                    //to show all toolbar buttons - minimum 768
                    $(eid).width(Math.max(768, $input.width())).height($input.height()).val($input.val()); 
                    //html($.parseHTML(   

                    var nw = $input.css('min-width');

                    tinymce.init({
                        //target: $editor, 
                        //selector: '#'+$input.attr('id'),
                        selector: eid,
                        menubar: false,
                        inline: false,
                        branding: false,
                        elementpath: false,
                        statusbar: true,        
                        resize: 'both', 

                        //relative_urls : false,
                        //remove_script_host : false,
                        //convert_urls : true, 
                        
                        relative_urls : true,
                        remove_script_host: false,
                        //document_base_url : window.hWin.HAPI4.baseURL,
                        urlconverter_callback : 'tinymceURLConverter',

                        entity_encoding:'raw',
                        inline_styles: true,
                        content_style: "body { font-size: 8pt; font-family: Helvetica,Arial,sans-serif; }",
                        //width: nw, // '120ex', 
                        height: ($input.height()+110),
                        setup:function(editor) {

                            if(editor.ui){
                                editor.ui.registry.addButton('customHeuristMedia', {
                                    icon: 'image',
                                    text: 'Media',
                                    onAction: function (_) {  //since v5 onAction in v4 onclick
                                        that._addHeuristMedia();
                                    }
                                });                                        
                            }else{
                                editor.addButton('customHeuristMedia', {
                                    icon: 'image',
                                    text: 'Media',
                                    onclick: function (_) {  //since v5 onAction in v4 onclick
                                        that._addHeuristMedia();
                                    }
                                });                                        
                            }
                            /*
                            editor.on('init', function(e) {
                                $('.tox-edit-area').css({border:'1px solid blue'});
                            });
                            */

                            editor.on('change', function(e) {

                                var newval = editor.getContent();
                                var nodes = $.parseHTML(newval);
                                if(nodes && nodes.length==1 &&  !(nodes[0].childElementCount>0) &&
                                    (nodes[0].nodeName=='#text' || nodes[0].nodeName=='P'))
                                { 
                                    //remove the only tag
                                    $input.val(nodes[0].textContent);
                                }else{
                                    $input.val(newval);     
                                }

                                //$input.val( ed.getContent() );
                                that.onChange();
                            });
                        },
                        plugins: [ //contextmenu, textcolor since v5 in core
                            'advlist autolink lists link image preview ', //anchor charmap print 
                            'searchreplace visualblocks code fullscreen',
                            'media table paste help'  //insertdatetime  wordcount
                        ],      
                        //undo redo | code insert  |  fontselect fontsizeselect |  forecolor backcolor | media image link | alignleft aligncenter alignright alignjustify | fullscreen            
                        toolbar: ['formatselect | bold italic forecolor | customHeuristMedia link | align | bullist numlist outdent indent | table | removeformat | help'],
                        content_css: [
                            '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i'
                            //,'//www.tinymce.com/css/codepen.min.css'
                        ]
                    });
                    $input.hide();
                } // _showEditor()

                var isCMS_content = (( 
                         this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'] ||
                         this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME']) &&
                        (this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'] || 
                         this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER'] || 
                         this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_FOOTER']));

                var cur_action = 'text';

                if( isCMS_content ){
                    
                    var fstatus = '';
                    var fname = 'Edit page content';
                    if(this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER']){
                        fname = 'Custom header';
                    }else if(this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_FOOTER']){
                        fname = 'Custom footer';
                        fstatus = (window.hWin.HEURIST4.util.isempty(value))
                            ?'No custom footer defined'
                            :'Delete html from this field to use default page footer.';
                    }
                                
                                
                    if (this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME']
                        && this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER'])
                        { // Only show this for the CMS Home record type
                            fstatus = (window.hWin.HEURIST4.util.isempty(value))
                            ?'No custom header defined'
                            :'Delete html from this field to use default page header.';
                        }
                    else if(this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME']
                        && this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION']){
                            fstatus = 'Leave this field blank if you wish the first menu entry to load automatically on startup.';    
                    }
                    
                    var div_prompt = $('<div style="line-height:20px"><b>Please use the '
                        + fname
                        + ' button in the <span>website editor</span> to edit this field.<br>'
                        + fstatus+'</b></div>')
                        .insertBefore($input);
                    $input.hide();
                    
                    $btn_edit_switcher.text('edit source').css({
                        'text-decoration': 'underline',
                        'display': 'inline-block',
                        'margin': '2px 10px 0px',
                        'cursor': 'pointer'
                    });
                    
                    var $btn_edit_switcher2 = $( '<div>edit wysiwyg</div>', {title: 'Show rich text editor'})
                        .addClass('smallbutton')
                        .css({'line-height': '20px', 'vertical-align': 'top', cursor: 'pointer', 'text-decoration': 'underline', 'display': 'inline-block'})
                        .insertAfter( $btn_edit_switcher );
                    var $label_edit_switcher = $('<span>Advanced users:</span>')
                        .css({'line-height': '20px','vertical-align':'top'}).addClass('smallbutton')
                        .insertBefore( $btn_edit_switcher );
                    
                    this._on( $btn_edit_switcher, { click: function(){
                        //$btn_edit_switcher.hide();
                        //$input.show();
                        $btn_edit_switcher2.hide();
                        $label_edit_switcher.hide();
                        var eid = '#'+$input.attr('id')+'_editor';                    
                        if($input.is(':visible')){
                            $btn_edit_switcher.text('text');
                            __showEditor(true); //show tinymce editor
                        }else{
                            $btn_edit_switcher.text('wysiwyg');
                            $input.show();
                            //tinymce.remove('#'+$input.attr('id'));
                            tinymce.remove(eid);
                            $(eid).parent().hide();
                            __adjustTextareaHeight();
                        }                        
                    }});
                    this._on( $btn_edit_switcher2, { click: function(){
                        $btn_edit_switcher2.hide();
                        $label_edit_switcher.hide();
                        $btn_edit_switcher.text('text');
                        __showEditor(true); //show tinymce editor
                    }});

                    
                    var $cms_dialog = window.hWin.HEURIST4.msg.getPopupDlg();
                    if($cms_dialog.find('.main_cms').length>0){ 
                        //opened from cms editor
                        //$btn_edit_switcher.hide();
                    }else{
                        //see manageRecords for event handler
                        div_prompt.find('span')
                            .css({cursor:'pointer','text-decoration':'underline'})
                            .attr('data-cms-edit', 1)
                            .attr('data-cms-field', this.options.dtID)
                            .attr('title','Edit website content in the website editor');   
                            
                    }
                }else if($btn_edit_switcher.is('div')){

                    this._on($btn_edit_switcher.find('span'), { 
                        click: function(event){

                            var sel_action = $(event.target).text();
                            if(cur_action == sel_action) return;

                            cur_action = sel_action;

                            $btn_edit_switcher.find('span').css('text-decoration', '');
                            $(event.target).css('text-decoration', 'underline');

                            var eid = '#'+$input.attr('id')+'_editor';

                            if(cur_action == 'wysiwyg'){
                                __showEditor(true); //show tinymce editor
                            }else if(cur_action == 'text'){
                                $input.show();
                                tinymce.remove(eid);
                                $(eid).parent().hide();
                                __adjustTextareaHeight();
                            }
                        }
                    });

                }else{
                    
                    this._on( $btn_edit_switcher, { 
                        click: function(){
                            var eid = '#'+$input.attr('id')+'_editor';                    
                            if($input.is(':visible')){
                                $btn_edit_switcher.text('text');
                                __showEditor(true); //show tinymce editor
                            }else{
                                $btn_edit_switcher.text('wysiwyg');
                                $input.show();
                                tinymce.remove(eid);
                                $(eid).parent().hide();
                                __adjustTextareaHeight();
                            }
                        }
                    });
                }

                //what is visible initially
                if( !isCMS_content && this.options.dtID != window.hWin.HAPI4.sysinfo['dbconst']['DT_KML'] ) {
                    var nodes = $.parseHTML(value);
                    if(nodes && (nodes.length>1 || (nodes[0] && nodes[0].nodeName!='#text'))){ //if it has html show editor at once
                        setTimeout(function(){

                            if($btn_edit_switcher.is('span')){
                                $btn_edit_switcher.text('text'); 
                            }else{
                                cur_action = 'wysiwyg';
                                $btn_edit_switcher.find('span').css('text-decoration', '');
                                $btn_edit_switcher.find('span:contains("wysiwyg")').css('text-decoration', 'underline');
                            }

                            __showEditor(); 
                        },1200); 
                    }
                }
                
            } 

        }
        // || this.options.dtID=='tag'
        else if(this.detailType=='enum' || this.detailType=='relationtype'){//--------------------------------------

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
                $input = this._recreateSelector($input, value);
				
                if(this.options.recordset && this.options.recordset.entityName == 'Records' && $input.hSelect("instance")!=undefined){
                    var tooltipDefined = false;
                    $input.hSelect("widget").on({
                        click: function(event, ui){ // selectmenu click extra handler

                            if(!tooltipDefined){
            					var trm_tooltip;

                                tooltipDefined = true;
            					
            					$input.hSelect("menuWidget").find('div').on({ // selectmenu's menu items
            						"mouseover": function(e){ // Retrieve information then display tooltip

            							var parent_container = $input.hSelect("menuWidget")[0];

            							var term_txt = $(e.target).text();
            							var vocab_id = that.f('rst_FilteredJsonTermIDTree');
            							var data = $Db.trm_TreeData(vocab_id, 'select');
            							
            							var details = "";

            							for(var i=0; i<data.length; i++){
            								if(data[i].title == term_txt){

            									var rec = $Db.trm(data[i].key);

            									if(!window.hWin.HEURIST4.util.isempty(rec.trm_Code)){
            										details += "<span style='text-align: center;'>Code &rArr; " + rec.trm_Code + "</span>";
            									}

            									if(!window.hWin.HEURIST4.util.isempty(rec.trm_Description)){

            										if(window.hWin.HEURIST4.util.isempty(details)){
            											details += "<span style='text-align: center;'>Code &rArr; N/A </span>";
            										}

            										details += "<hr/><span>" + rec.trm_Description + "</span>";
            									}
            								}
            							}

            							if(window.hWin.HEURIST4.util.isempty(details)){
            								details = "No Description Provided";
            							}

            							trm_tooltip = $(parent_container).tooltip({
            								items: "div.ui-state-active",
            								position: { // Post it to the right of menu item
            									my: "left+20 center",
            									at: "right center",
            									collision: "none"
            								},
            								show: { // Add delay to show
            									delay: 2000,
            									duration: 0
            								},
            								content: function(){ // Provide text
            									return details;
            								},
            								open: function(event, ui){ // Add custom CSS
            									ui.tooltip.css({
            										"width": "200px",
            										"background": "#D1E7E7",
            										"font-size": "1.1em"
            									});
            								}
            							});
            						},

            						"mouseleave": function(){ // Ensure tooltip closes
            							if(trm_tooltip.tooltip("instance")!=undefined){
            								trm_tooltip.tooltip("destroy");
            							}
            						}
            					});
                            }
                        }
                    });

                    if(this.detailType=='enum'){ // show hierarchy for the selected term, not within the menuWidget
                        $input.hSelect({
                            select: function(event, ui){
                                var term_id = ui.item.value;
                                var crafted_label = ui.item.label;
                                var trm_info = $Db.trm(term_id);

                                if(term_id && trm_info && trm_info.trm_ParentTermID != 0){
                                    
                                    while(1){

                                        trm_info = $Db.trm(trm_info.trm_ParentTermID);

                                        if(trm_info.trm_ParentTermID == 0){
                                            break;
                                        }else{
                                            crafted_label = trm_info.trm_Label + '.' + crafted_label;
                                        }
                                    }

                                    $input.hSelect('widget').find('span.ui-selectmenu-text').text(crafted_label);
                                }
                            }
                        });
                    }

                    // Craft initial label
                    var trm_info = $Db.trm(value);
                    if(value && Number.isInteger(+value) && trm_info){
                        var crafted_label = trm_info.trm_Label;

                        if(crafted_label != undefined && trm_info.trm_ParentTermID != 0){

                            while(1){

                                trm_info = $Db.trm(trm_info.trm_ParentTermID);

                                if(trm_info.trm_ParentTermID == 0){
                                    break;
                                }else{
                                    crafted_label = trm_info.trm_Label + '.' + crafted_label;
                                }
                            }

                            $input.hSelect('widget').find('span.ui-selectmenu-text').text(crafted_label);
                        }
                    }
                }
            }
            $input = $($input);
            
            this._on( $input, {change:this._onTermChange} );
            
            var allTerms = this.f('rst_FieldConfig');    
            
            if($.isPlainObject(allTerms)){
                this.options.showclear_button = (allTerms.hideclear!=1);
            }
            
            //allow edit terms only for true defTerms enum and if not DT_RELATION_TYPE
            if(window.hWin.HEURIST4.util.isempty(allTerms)) {
                allTerms = this.f('rst_FilteredJsonTermIDTree');

                if (!(window.hWin.HEURIST4.util.isempty(allTerms) && 
                    this.options.dtID==window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'])) {

                    var isVocabulary = !isNaN(Number(allTerms)); 

                    var $btn_termsel = $( '<span>', {title: 'Select Term By Picture'})
                    .addClass('smallicon ui-icon ui-icon-image')
                    .css({
                        'margin-top': '2px',
                        'cursor': 'pointer'
                    })
                    .appendTo( $inputdiv )
                    .hide();

                    var all_term_ids = $Db.trm_TreeData(allTerms, 'set');

                    var trm_img_req = {
                        'a': 'search',
                        'entity': 'defTerms',
                        'details': 'list',
                        'trm_ID': all_term_ids,
                        'withimages': 1,
                        'request_id': window.hWin.HEURIST4.util.random()
                    };

                    window.hWin.HAPI4.EntityMgr.doRequest(trm_img_req, function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            var recset = new hRecordSet(response.data);
                            if(recset.length() > 0){
                                $btn_termsel.hide();
                            }else{
                                $btn_termsel.hide();
                            }
                        }
                    });

                    this._on( $btn_termsel, { click: function(){

                        if(this.is_disabled) return;

                        trm_img_req['trm_ID'] = $Db.trm_TreeData(allTerms, 'set');

                        //@todo - rewrite to $Db.trm select_single

                        //select term by image
                        window.hWin.HAPI4.EntityMgr.doRequest(trm_img_req, function(response){ // check for terms with images

                            if(response.status == window.hWin.ResponseStatus.OK){
                                var recset = new hRecordSet(response.data);
                                if(recset.length()>0){                                  

                                    var selectmode = that.enum_buttons == 'checkbox' ? 'select_multi' : 'select_single';
                                    var dlg_title = 'Term selection for ' + that.f('rst_DisplayName');

                                    window.hWin.HEURIST4.ui.showEntityDialog('defTerms', {
                                        empty_remark: 'No terms available',
                                        title: dlg_title,
                                        hide_searchForm: true,
                                        select_mode: selectmode, 
                                        view_mode: 'icon',
                                        initial_filter: Number(allTerms),
                                        default_palette_class: 'ui-heurist-populate',
                                        onselect:function(event, data){
                                            if(data && data.selection && data.selection.length > 0){

                                                if(data.selection.length > 1 || selectmode == 'select_multi'){
                                                    that.setValue(data.selection, false);
                                                }else{
                                                    $input.val(data.selection[0]);
                                                    if($input.hSelect('instance') !== undefined) $input.hSelect('refresh');
                                                    that.onChange();
                                                }
                                            }
                                        }
                                    });
                                                                    
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgFlash('No terms images defined');
                                    that.input_cell.find('.ui-icon-image').hide(); // hide icon, accidental showing
                                }
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        });
                    }});

                    var vocab_id = Number(allTerms);

                    if(window.hWin.HAPI4.is_admin()){            
                        
                        var $btn_termedit2 = $( '<span>', {title: 'Edit term tree'})
                        .addClass('smallicon ui-icon ui-icon-gear btn_add_term')
                        .css({'margin-top':'2px',cursor:'pointer'})
                        .appendTo( $inputdiv );
                        
                        this._on( $btn_termedit2,{ click: function(){ this._openManageTerms(vocab_id); }});
                            
                    }
                
                    
                    var $btn_termedit = $( '<span>', {title: 'Add new term to this list'})
                    .addClass('smallicon ui-icon ui-icon-plus btn_add_term')
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
                }
            }//allow edit terms only for true defTerms enum
            
            // Display term selector as radio buttons/checkboxes
            var asButtons = this.options.recordset && this.options.recordset.entityName=='Records' && this.f('rst_TermsAsButtons');
            var vocab_id = that.f('rst_FilteredJsonTermIDTree');
            var child_terms = $Db.trm_TreeData(vocab_id, 'set');

            if(asButtons == 1 && child_terms.length <= 20){

                this.enum_buttons = (Number(this.f('rst_MaxValues')) != 1) ? 'checkbox' : 'radio';
                var inpt_id = $input.attr('id');
                var dtb_res = false;

                if(this.enum_buttons == 'checkbox' && $inputdiv.parent().find('input:checkbox').length > 0){ // Multi value, check if checkboxes exist

                    $inputdiv.parent().find('input:checkbox[data-id="'+value+'"]').prop('checked', true); // Check additional value
                    $inputdiv.hide();

                    dtb_res = true;
                }else{ // Create input elements
                    dtb_res = this._createEnumButtons(false, child_terms, $inputdiv, [value]);
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
            .change(function(){that.onChange();})
            .appendTo( $inputdiv );
            
            if($.isArray(this.configMode)){
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
            $input.change(function(){that.onChange();})

        }
        else if(this.detailType=="user"){ //special case - only groups of current user

            $input = $( "<select>")
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .change(function(){that.onChange();})
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
            .change(function(){that.onChange();})
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
                            }    
                            
                            
                            var rty_names = '';
                            if(rts.length>0 && that.options.rectypeID>0){
                                rty_names = $Db.rty(that.options.rectypeID,'rty_Name') 
                                            + ' and ' + rts.join(', ');
                            }else{
                                rty_names = 'records';
                            }
                            
                            var opts = {
                                height:280, width:750, 
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
                                        if( headers[targetID]['used_in_reverse']!=1 &&
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
                                                }, true);
                                            ele.on('remove', __onRelRemove);
                                            
                                            headers[targetID]['used_in_direct'] = 1;
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
                                        
                                        if (headers[targetID]['used_in_direct']!=1 && (ptrset.length==0) ||
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
                                                }, true);
                                            ele.addClass('reverse-relation', 1)
                                                .on('remove', __onRelRemove);
                                            
                                            headers[targetID]['used_in_reverse'] = 1;
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
                   $inputdiv
                        .uniqueId();
                   $input = $inputdiv;

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
                   //$btn_add_rel_dialog.click(function(){__show_addlink_dialog()});
                   
                   __onRelRemove();                   
                   /*if( this.element.find('.link-div').length>0){ //hide this button if there are links
                        $btn_add_rel_dialog.hide();
                   }*/

                
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
            
            //define explicit add resource button
            $( "<button>", {title: "Select record to be linked"})
                        .button({icon:"ui-icon-triangle-1-e",
                               label:('&nbsp;&nbsp;&nbsp;<span style="color: #55555566;">select'+(isparententity?' child':'')+'&nbsp: '
                               +'<div class="truncate" style="max-width:200px;display:inline-block;vertical-align:middle">'
                               +rts+'</div></span>')})
                        .addClass('sel_link2').css({'max-width':'300px'}) //, 'background': 'lightgray'})
                        .appendTo( $inputdiv );
            
            __show_select_function = null;
            if(typeof browseRecords!=='undefined' && $.isFunction(browseRecords)){
                __show_select_function = browseRecords(this, $input); //see editing_exts
            }
            
            that._findAndAssignTitle($input, value, __show_select_function);
            
            this.newvalues[$input.attr('id')] = value;  //for this type assign value at init  
        } 
        
        else if(this.detailType=='resource' && this.configMode.entity=='DefRecTypes'){ //-----------
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
                
                window.hWin.HEURIST4.ui.showEntityDialog('defRecTypes', rg_options);
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
            
            this.newvalues[$input.attr('id')] = value;  //for this type assign value at init  
        
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
                            that.newvalues[$input.attr('id')] = newsel.join(',');
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
            
            this.newvalues[$input.attr('id')] = value;  //for this type assign value at init  

        }
        else{              //----------------------------------------------------
            $input = $( "<input>")
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .val(value)
            .keyup(function(){that.onChange();})
            .change(function(){
                    that.onChange();
            })
            .appendTo( $inputdiv );
            
            window.hWin.HEURIST4.ui.disableAutoFill( $input );
            
            if(!(this.options.dtID=='file' || this.detailType=='resource' || 
                 this.detailType=='date' || this.detailType=='geo' || this.detailType=='action')){
                     
                $input.keydown(function(e){  //Ctrl+A - select all
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
                            $input.removeClass('rec_URL').addClass('text').removeAttr("readonly");
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
                                $input.focus();   
                            }
                        }else if(window.hWin.HEURIST4.util.isnull($btn_extlink)){
                            
                            if($input.val()!='' && !($input.val().indexOf('http://')==0 || $input.val().indexOf('https://')==0)){
                                $input.val( 'http://'+$input.val());
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
                            //.button({icons:{primary: 'ui-icon-extlink'},text:false});
                    
                        that._on( $btn_extlink, { click: function(){ window.open($input.val(), '_blank') }} );
                        that._on( $input, { click: function(){ if ($input.val()!='') window.open($input.val(), '_blank') }} );
                    }

                    //$input.focusout( __url_input_state ); 
                    __url_input_state(true);               
                
            }else if(this.detailType=="freetext" && ($input.val().indexOf('http://')==0 || $input.val().indexOf('https://')==0)){ // add link to external url

                var $btn_extlink = $('<span>')
                                    .attr('title', 'Open URL in new window')
                                    .addClass('smallicon ui-icon ui-icon-extlink')
                                    .css({
                                        'font-size': 'small',
                                        'color': 'blue'
                                    })
                                    .appendTo( $inputdiv )

                this._on($btn_extlink, {click: function(){ window.open($input.val(), '_blank'); }} );

            }else if(this.detailType=="integer" || this.detailType=="year"){//-----------------------------------------

                 
                $input.keypress(function (e) {
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
                
                /*
                if(this.options.is_faceted_search){
                    $input.css({'max-width':'13ex','min-width':'13ex'});
                }
                */
                
                
                /*$input.keyup(function () {
                if (this.value != this.value.replace(/[^0-9-]/g, '')) {
                this.value = this.value.replace(/[^0-9-]/g, '');  //[-+]?\d
                }
                });*/
            }else
            if(this.detailType=="float"){//----------------------------------------------------

                    $input.keypress(function (e) {
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
                        if(!$.isNumeric(e.originalEvent.clipboardData.getData('text'))){
                            window.hWin.HEURIST4.util.stopEvent(e);
                            e.preventDefault();
                            window.hWin.HEURIST4.msg.showTooltipFlash(window.hWin.HR('Numeric field'),1000,$input);
                        }
                    });

            }else
            if(this.detailType=='date'){//----------------------------------------------------
                
                this._createDateInput($input, $inputdiv);
                
                $input.val(value);    
                $input.change(); 

				// Add additional help text 
				var help_text = this.input_prompt.html();

				if(!window.hWin.HEURIST4.util.isempty(help_text)){

                    var additional_help = 'For older dates, type year first, then click the calendar. Dates can be yyyy, yyyy-mm or yyyy-mm-dd.';

                    if(help_text.indexOf(additional_help) < 0){
                        this.input_prompt.html(help_text + '<br>' + additional_help);

                        var $extended_desc = this.input_prompt.find('#show_extended, #extended_help, #hide_extended');
                        if($extended_desc.length > 0){
                            this.input_prompt.append($extended_desc);

                            // Toggle extended description
                            this._on($extended_desc, {
                                'click': function(event){
                                    $extended_desc.toggle()
                                }
                            });
                        }
                    }
				}
            }else 
            if(this.isFileForRecord){ //----------------------------------------------------
                
				var $input_img, $gicon;
                
                var icon_for_button = 'ui-icon-folder-open';
                var select_return_mode = 'recordset';

                /* File IDs, needed for processes below */
                var f_id = value.ulf_ID;
                var f_nonce = value.ulf_ObfuscatedFileID;

                var $clear_container = $('<span id="btn_clear_container"></span>').appendTo( $inputdiv );
                
                $input.css({'padding-left':'30px', cursor:'hand'});
                //folder icon in the begining of field
                $gicon = $('<span class="ui-icon ui-icon-folder-open"></span>')
                    .css({position: 'absolute', margin: '5px 0px 0px 8px', cursor:'hand'}).insertBefore( $input ); 
                
                /* Image and Player (enalrged image) container */
                $input_img = $('<br/><div class="image_input ui-widget-content ui-corner-all thumb_image" style="margin:5px 0px;border:none;background:transparent;">'
                + '<img id="img'+f_id+'" class="image_input" style="max-width:none;">'
                + '<div id="player'+f_id+'" style="min-height:100px;min-width:200px;display:none;"></div>'
                + '</div>')
                .appendTo( $inputdiv )
                .hide();

				/* Record Type help text for Record Editor */
				$small_text = $('<br /><div class="smallText" style="display:block;color:gray;font-size:smaller;">'
                    + 'Click image to freeze in place</div>')
                .clone()
                .insertAfter( $clear_container )
                .hide();

                /* urls for downloading and loading the thumbnail */
                var dwnld_link = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&debug=1&download=1&file='+f_nonce;
                var url = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&file='+f_nonce+'&mode=tag&origin=recview'; 

                /* Anchors (download and show thumbnail) container */
                $dwnld_anchor = $('<br /><div class="download_link">'
                    + '<a id="lnk'+f_id+'" href="#" oncontextmenu="return false;" style="display:none;padding-right:20px;text-decoration:underline;color:blue"'
                    + '>SHOW THUMBNAIL</a>'
                    + '<a id="dwn'+f_id+'" href="'+window.hWin.HEURIST4.util.htmlEscape(dwnld_link)+'" target="_surf" class="external-link image_tool'
                    + '"style="display:inline-block;text-decoration:underline;color:blue">DOWNLOAD</a>'
                    + '</div>')
                .clone()
                .appendTo( $inputdiv )
                .hide();
                
                /* Change Handler */
                $input.change(function(event){
					
                    /* new file values */
                    var val = that.newvalues[$input.attr('id')];

                    if(window.hWin.HEURIST4.util.isempty(val) || !(val.ulf_ID >0)){
                        $input.val('');
                    }else{
                        var n_id = val['ulf_ID'];
                        var n_nonce = val['ulf_ObfuscatedFileID'];
                        var n_dwnld_link = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&debug=2&download=1&file='+n_nonce;
                        var n_url = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&file='+n_nonce+'&mode=tag&origin=recview';
                    
                        if(f_id != n_id){// If the image has been changed from original/or has been newly added

                            $container = $(event.target.parentNode);

                            $show = $($container.find('a#lnk'+f_id)[0]);
                            $dwnld = $($container.find('a#dwn'+f_id)[0]);
                            $player = $($container.find('div#player'+f_id)[0]);
                            $thumbnail = $($container.find('img#img'+f_id)[0]);

                            $show.attr({'id':'lnk'+n_id});

                            $dwnld.attr({'id':'dwn'+n_id, 'href':n_dwnld_link});

                            $player.attr({'id':'player'+n_id});

                            $thumbnail.attr({'id':'img'+n_id});                       

                            f_id = n_id;
                            f_nonce = n_nonce;
                            dwnld_link = n_dwnld_link;
                            url = n_url;
                        }
                        
                    }
                    
                    //clear thumb rollover
                    if(window.hWin.HEURIST4.util.isempty($input.val())){
                        $input_img.find('img').attr('src','');
                    }

                    that.onChange(); 
                });
                
                /* Handler Variables */
                var hideTimer = 0, showTimer = 0;  //  Time for hiding thumbnail
                var isClicked = 0;  // Number of image clicks, one = freeze image inline, two = enlarge/srink

                /* Input element's hover handler */
                function __showImagePreview(event){

                    var imgAvailable = !window.hWin.HEURIST4.util.isempty($input_img.find('img').attr('src'));
                    var invalidURL = $inputdiv.find('div.smallText').hasClass('invalidImg');

                    if((imgAvailable || invalidURL) && isClicked == 0){
                        if (hideTimer) {
                            window.clearTimeout(hideTimer);
                            hideTimer = 0;
                        }
                        
                        if($input_img.is(':visible')){
                            $input_img.stop(true, true).show();    
                        }else{
                            if(showTimer==0){
                                showTimer = window.setTimeout(function(){
                                    $input_img.show();
                                    $inputdiv.find('div.smallText').show();
                                    showTimer = 0;
                                },500);
                            }
                        }
                    }
                }
                this._on($input,{mouseover: __showImagePreview});
                this._on($input_img,{mouseover: __showImagePreview}); //mouseover

                /* Input element's mouse out handler, attached and dettached depending on user preferences */
                function __hideImagePreview(event){
                    if (showTimer) {
                        window.clearTimeout(showTimer);
                        showTimer = 0;
                    }

                    if($input_img.is(':visible')){
                        
                        //var ele = $(event.target);
                        var ele = event.toElement || event.relatedTarget;
                        ele = $(ele);
                        if(ele.hasClass('image_input') || ele.parent().hasClass('image_input')){
                            return;
                        }
                                                
                        hideTimer = window.setTimeout(function(){
                            if(isClicked==0){
                                $input_img.fadeOut(1000);
                                $inputdiv.find('div.smallText').hide(1000);
                            }
                        }, 500);
                    }
                }
                this._on($input, {mouseout:__hideImagePreview});
                this._on($input_img, {mouseout:__hideImagePreview});

                /* Thumbnail's click handler */
                $input_img.click(function(event){

                    var elem = event.target;
                    
                    if (isClicked==0 && !$(elem.parentNode.parentNode).find('div.smallText').hasClass('invalidImg')){
                        isClicked=1;
                        
                        that._off($input_img,'mouseout');

                        $(elem.parentNode.parentNode).find('div.smallText').hide(); // Hide image help text

                        $dwnld_anchor = $($(elem.parentNode.parentNode).find('div.download_link')); // Find the download anchors
                        
                        $dwnld_anchor.show();

                        if ($dwnld_anchor.find('a#dwnundefined')){  // Need to ensure the links are setup
                            $dwnld_anchor.find('a#dwnundefined').attr({'id':'dwn'+f_id, 'href':dwnld_link});
                            $dwnld_anchor.find('a#lnkundefined').attr({'id':'lnk'+f_id, 'onClick':'window.hWin.HEURIST4.ui.hidePlayer('+f_id+', this.parentNode)'})
                        }

                        $input_img.css('cursor', 'zoom-in');

                        window.hWin.HAPI4.save_pref('imageRecordEditor', 1);
                    }
                    else if (isClicked==1) {

                        /* Enlarge Image, display player */
                        if ($(elem.parentNode).hasClass("thumb_image")) {
                            $(elem.parentNode.parentNode).find('.hideTumbnail').hide();

                            $input_img.css('cursor', 'zoom-out');

                            window.hWin.HEURIST4.ui.showPlayer(elem, elem.parentNode, f_id, url);
                        }
                        else {  // Srink Image, display thumbnail
                            $($input_img[1].parentNode).find('.hideTumbnail').show();

                            $input_img.css('cursor', 'zoom-in');
                        }
                    }
                }); 

				/* for closing inline image when 'frozen' */
                $hide_thumb = $('<span class="hideTumbnail" style="padding-left:5px;color:gray;cursor:pointer;font-size:smaller;">'
                                + 'CLOSE IMAGE</span>').appendTo( $dwnld_anchor ).show();
                $hide_thumb.on("click", function(event){

                    isClicked = 0;

                    that._on($input, {mouseout:__hideImagePreview});
                    that._on($input_img, {mouseout:__hideImagePreview});

                    $(event.target.parentNode).hide();

                    if($(event.target.parentNode).find('div.smallText').hasClass('invalidImg')){
                        $input_img.hide().css('cursor', '');
                    }else{
                        $input_img.hide().css('cursor', 'pointer');
                    }
                });

				/* Show Thumbnail handler */
                $('#lnk'+f_id).on("click", function(event){
                    window.hWin.HEURIST4.ui.hidePlayer(f_id, event.target.parentNode.parentNode.parentNode);
					
                    $(event.target.parentNode.parentNode).find('.hideTumbnail').show();
				});

                /* Check User Preferences, displays thumbnail inline by default if set */
                if (window.hWin.HAPI4.get_prefs_def('imageRecordEditor', 0)!=0 && value.ulf_ID) {
                    $input_img.show();
                    $dwnld_anchor.show();

                    $dwnld_anchor.appendTo( $inputdiv );

                    $input_img.css('cursor', 'zoom-in');
                    
                    $small_text.hide();

                    $input.off("mouseout");

                    isClicked=1;
                }

                var __show_select_dialog = null;
                 
                var isTiledImage = this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']     
                    && this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL'];
                 
                var popup_options = {
                    isdialog: true,
                    select_mode: 'select_single',
                    additionMode: isTiledImage?'tiled':'any',  //AAAA
                    edit_addrecordfirst: true, //show editor at once
                    select_return_mode:select_return_mode, //ids or recordset(for files)
                    filter_group_selected:null,
                    filter_groups: this.configMode.filter_group,
                    default_palette_class: 'ui-heurist-populate',
                    onselect:function(event, data){

                     if(data){
                        
                            if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                var recordset = data.selection;
                                var record = recordset.getFirstRecord();
                                
                                var newvalue = {ulf_ID: recordset.fld(record,'ulf_ID'),
                                                ulf_ExternalFileReference: recordset.fld(record,'ulf_ExternalFileReference'),
                                                ulf_OrigFileName: recordset.fld(record,'ulf_OrigFileName'),
                                                ulf_MimeExt: recordset.fld(record,'fxm_MimeType'),
                                                ulf_ObfuscatedFileID: recordset.fld(record,'ulf_ObfuscatedFileID')};
                                
                                that.newvalues[$input.attr('id')] = newvalue;
                                that._findAndAssignTitle($input, newvalue);
                                
                                /*
                                that.newvalues[$input.attr('id')] = recordset.fld(record,'ulf_ID');
                                
                                var rec_Title = recordset.fld(record,'ulf_ExternalFileReference');
                                if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                                    rec_Title = recordset.fld(record,'ulf_OrigFileName');
                                }
                                window.hWin.HEURIST4.ui.setValueAndWidth($input, rec_Title, 10);

                                //url for thumb
                                $inputdiv.find('.image_input > img').attr('src',
                                    window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&thumb='+
                                    recordset.fld(record,'ulf_ObfuscatedFileID'));
                                */
                                //if(newvalue.ulf_OrigFileName) $input.change();
                            }
                        
                     }//data

                    }
                };//popup_options

                that._findAndAssignTitle($input, value);

                __show_select_dialog = function(event){
                    
                        if(that.is_disabled) return;

                        event.preventDefault();
                        
                        var usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_'+this.configMode.entity, 
                            {width: null,  //null triggers default width within particular widget
                            height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });
            
                        popup_options.width = usrPreferences.width;
                        popup_options.height = usrPreferences.height;
                        var sels = this.newvalues[$(event.target).attr('id')];
                        if(!sels && this.options.values && this.options.values[0]){
                             sels = this.options.values[0];    //take selected value from options
                        } 

                        if($.isPlainObject(sels)){
                            popup_options.selection_on_init = [sels.ulf_ID];
                        }else if(!window.hWin.HEURIST4.util.isempty(sels)){
                            popup_options.selection_on_init = sels.split(',');
                        } else {
                            popup_options.selection_on_init = null;    
                        }                                                                                       
                        //init dialog to select related uploaded files
                        window.hWin.HEURIST4.ui.showEntityDialog(this.configMode.entity, popup_options);
                }
                
                if(__show_select_dialog!=null){
                    //no more buttons this._on( $btn_rec_search_dialog, { click: __show_select_dialog } );
                    this._on( $input, { keypress: __show_select_dialog, click: __show_select_dialog } );
                    this._on( $gicon, { click: __show_select_dialog } );
                }
                
                if(this.isFileForRecord && value){
                    //assign value at once
                    this.newvalues[$input.attr('id')] = value;
                }
            }
            else
            if( this.detailType=='folder' ){ //----------------------------------------------------
                
                $input.css({'padding-left':'30px'});
                
                var $gicon = $('<span>').addClass('ui-icon ui-icon-gear')
                    .css({position:'absolute',margin:'2px 0 0 8px',cursor:'hand'})
                    .insertBefore($input);
                var $select_folder_dlg = $('<div/>').hide().appendTo( $inputdiv )
                
                that.newvalues[$input.attr('id')] = value;
                    
                this._on( $gicon, { click: function(){                                 
                        $select_folder_dlg.select_folders({
                       onselect:function(newsel){
                            if(newsel){
                                var newsel = newsel.join(';');
                                that.newvalues[$input.attr('id')] = newsel;
                                $input.val(newsel);
                                that.onChange();
                            }
                        }, 
                       selectedFolders: that.newvalues[$input.attr('id')], 
                       multiselect: that.configMode && that.configMode.multiselect});
                    }} );
            }
            else
            if( this.detailType=='file' ){ //----------------------------------------------------
                
                
                        this.options.showclear_button = (this.configMode.hideclear!=1);
                        
                        if(!this.configMode.version) this.configMode.version = 'thumb';
                
                        //url for thumb
                        var urlThumb = window.hWin.HAPI4.getImageUrl(this.configMode.entity, 
                                                        this.options.recID, this.configMode.version, 1);
                        var dt = new Date();
                        urlThumb = urlThumb+'&ts='+dt.getTime();
                        
                        $input.css({'padding-left':'30px'});
                        $('<span class="ui-icon ui-icon-folder-open"></span>')
                                .css({position: 'absolute', margin: '5px 0px 0px 8px'}).insertBefore( $input ); 
                        
                        var sz = 0;
                        if(that.options.dtID=='rty_Thumb'){
                            sz = 64;
                        }else if(that.options.dtID=='rty_Icon'){
                            sz = 16;
                        }
                        
                        //container for image
                        var $input_img = this.input_img = $('<div tabindex="0" contenteditable class="image_input fileupload ui-widget-content ui-corner-all" style="border:dashed blue 2px;">'
                            + '<img src="'+urlThumb+'" class="image_input" style="'+(sz>0?('width:'+sz+'px;'):'')+'">'
                            + '</div>').appendTo( $inputdiv );                
                        if(this.configMode.entity=='recUploadedFiles'){
                           this.input_img.css({'min-height':'320px','min-width':'320px'});
                           this.input_img.find('img').css({'max-height':'320px','max-width':'320px'});
                        }
                         
                        window.hWin.HAPI4.checkImage(this.configMode.entity, this.options.recID, 
                            this.configMode.version,
                            function(response){
                                  if(response.data=='ok'){
                                      that.entity_image_already_uploaded = true;
                                  }
                        });
                        
                        //change parent div style - to allow special style for image selector
                        if(that.configMode.css){
                            that.element.css(that.configMode.css);
                        }
                        
                        //library browser and explicit file upload buttons
                        if(that.configMode.use_assets){
                            
                            if(value){
                                that.newvalues[$input.attr('id')] = value; 
                            }
                            
                            var ele = $('<div style="display:inline-block;vertical-align:top;padding-left:4px"/>')
                            .appendTo( $inputdiv );                            
                            
                            $('<a href="#"><span class="ui-icon ui-icon-folder-open"/>Upload file</a>')
                                .click(function(){ $input.click() }).appendTo( ele );                            
                            $('<br/><br/>').appendTo( ele );                            
                            
                            $('<a href="#" title="Or select from library"><span class="ui-icon ui-icon-grid"/>Library</a>')
                                .click(function(){that.openIconLibrary()}).appendTo( ele );                     
                                
                        }
                            
                /* 2017-11-08 no more buttons 
                        //browse button    
                        var $btn_fileselect_dialog = $( "<span>", {title: "Click to select file for upload"})
                        .addClass('smallicon fileupload ui-icon ui-icon-folder-open')
                        .css('vertical-align','top')
                        .appendTo( $inputdiv );
                        //.button({icons:{primary: "ui-icon-folder-open"},text:false});
                  */                      
                        //set input as file and hide
                        $input.prop('type','file').hide();
                        
                        //temp file name  it will be renamed on server to recID.png on save
                        var newfilename = '~'+window.hWin.HEURIST4.util.random();

                        //crate progress dialog
                        var $progress_dlg = $('<div title="File Upload"><div class="progress-label">Starting upload...</div>'
                        +'<div class="progressbar" style="margin-top: 20px;"></div></div>').hide().appendTo( $inputdiv );
                        var $progress_bar = $progress_dlg.find('.progressbar');
                        var $progressLabel = $progress_dlg.find('.progress-label');
                        
                        this.select_imagelib_dlg = $('<div/>').hide().appendTo( $inputdiv );//css({'display':'inline-block'}).
         
                        $progress_bar.progressbar({
                              value: false,
                              change: function() {
                                $progressLabel.text( "Current Progress: " + $progress_bar.progressbar( "value" ) + "%" );
                              },
                              complete: function() {
                                    $progressLabel.text( "Complete!" );
                              }
                          });
         
         
         
        var fileupload_opts = {
    url: window.hWin.HAPI4.baseURL +  'hsapi/controller/fileUpload.php',
    formData: [ {name:'db', value: window.hWin.HAPI4.database}, 
                {name:'entity', value:this.configMode.entity},
                {name:'version', value:this.configMode.version},
                {name:'maxsize', value:this.configMode.size},
                {name:'registerAtOnce', value:this.configMode.registerAtOnce},
                {name:'recID', value:that.options.recID}, //need to verify permissions
                {name:'newfilename', value:newfilename }], //unique temp name
    //acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i
    //autoUpload: true,
    sequentialUploads:true,
    dataType: 'json',
    pasteZone: $input_img,
    dropZone: $input_img,
    // add: function (e, data) {  data.submit(); },
    submit: function (e, data) { //start upload
    
        $progress_dlg = $progress_dlg.dialog({
            autoOpen: false,
            modal: true,
            closeOnEscape: false,
            resizable: false,
            buttons: []
          });                        
        $progress_dlg.dialog('open'); 
        $progress_dlg.parent().find('.ui-dialog-titlebar-close').hide();
    },
    done: function (e, response) {
            //hide progress bar
            $progress_dlg.dialog( "close" );
        
            if(response.result){//????
                response = response.result;
            }
            if(response.status==window.hWin.ResponseStatus.OK){
                var data = response.data;

                $.each(data.files, function (index, file) {
                    if(file.error){ //it is not possible we should cought it on server side - just in case
                        $input_img.find('img').prop('src', '');
                        if(that.linkedImgContainer !== null){
                            that.linkedImgContainer.find('img').prop('src', '');
                        }

                        window.hWin.HEURIST4.msg.showMsgErr(file.error);
                    }else{

                        if(file.ulf_ID>0){ //file is registered at once and it returns ulf_ID
                            that.newvalues[$input.attr('id')] = file.ulf_ID;
                            if(that.linkedImgInput !== null){
                                that.newvalues[that.linkedImgInput.attr('id')] = file.ulf_ID;
                            }
                        }else{
                            
                            //var urlThumb = window.hWin.HAPI4.getImageUrl(that.configMode.entity, 
                            //            newfilename+'.png', 'thumb', 1);
                            var urlThumb =
                            (that.configMode.entity=='recUploadedFiles'
                                ?file.url
                                :file[(that.configMode.version=='icon')?'iconUrl':'thumbnailUrl'])
                                +'?'+(new Date()).getTime();
                            
                            // file.thumbnailUrl - is correct but inaccessible for heurist server
                            // we get image via fileGet.php
                            $input_img.find('img').prop('src', '');
                            $input_img.find('img').prop('src', urlThumb);
                            
                            if(that.configMode.entity=='recUploadedFiles'){
                                that.newvalues[$input.attr('id')] = file;
                            }else{
                                that.newvalues[$input.attr('id')] = newfilename;  //keep only tempname
                            }
                        }
                        $input.attr('title', file.name);
                        that.onChange();//need call it manually since onchange event is redifined by fileupload widget
                    }
                });
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);// .message
            }
            var inpt = this;
            $input_img.off('click');
            $input_img.on({click: function(){
                        $(inpt).click();
            }});
            },                            
    progressall: function (e, data) { //@todo to implement
        var progress = parseInt(data.loaded / data.total * 100, 10);
        //$('#progress .bar').css('width',progress + '%');
        $progress_bar.progressbar( "value", progress );        
    }                            
                        };        
        
    var isTiledImage = that.configMode.tiledImageStack ||
                        (that.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']     
                        && that.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL']);
    if(isTiledImage){
        fileupload_opts['formData'].push({name:'tiledImageStack', value:1});
        fileupload_opts['formData'].push({name: 'acceptFileTypes', value:'zip'});
        
        $input.attr('accept','.zip, application/zip');
    }                
       
                        //init upload widget
                        $input.fileupload( fileupload_opts );
                
                        //init click handlers
                        //this._on( $btn_fileselect_dialog, { click: function(){ $input_img.click(); } } );
                        $input_img.on({click: function(e){ //find('a')
                            $input.click(); //open file browse
                            
                            if($(e.target).is('img')){
                            }else{
                            }
                        }});
                        /*focus: function(){
                             $input_img.css({border:'dashed green 2px'});
                        },
                        blur: function(){
                             $input_img.css({border:'none'});
                        }});*
/*                        
                        paste:function(e){
console.log('onpaste');                            

    var items = e.originalEvent.clipboardData.items;
    for (var i = 0 ; i < items.length ; i++) {
        var item = items[i];
        if (item.type.indexOf("image") >=0) {
            console.log("FILE!");
        } else {
            console.log("Ignoring non-image.");
        }
    }
                        }});
*/                        
                        
                        
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
                
                      
                __show_action_dialog = function (event){
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
                                $input.val(JSON.stringify(that.newvalues[$input.attr('id')])).change();
                            }
                        };
                        
                        window.hWin.HEURIST4.ui.showRecordActionDialog( this.configMode.actionName, dlg_options );
                };

                this._on( $input, { keypress: __show_action_dialog, click: __show_action_dialog } );
                this._on( $gicon, { click: __show_action_dialog } );
            }else 
            if(this.detailType=='geo'){   //----------------------------------------------------
                
                $input.css({'width':'62ex','padding-left':'30px',cursor:'hand'});
                
                var $gicon = $('<span>').addClass('ui-icon ui-icon-globe')
                    .css({position:'absolute',margin:'4px 0 0 8px',cursor:'hand'})
                    .insertBefore($input);

                var geovalue = window.hWin.HEURIST4.geo.wktValueToDescription(value);
            
                that.newvalues[$input.attr('id')] = value;

                if(geovalue.summary && geovalue.summary != ''){
                    $input.val(geovalue.type+'  '+geovalue.summary).css('cursor','hand');
                }else if(!window.hWin.HEURIST4.util.isempty(value)){
                    var parsedWkt = window.hWin.HEURIST4.geo.getParsedWkt(value, true);
                    if(parsedWkt == '' || parsedWkt == null){
                        $input.val('');
                        $('<span>').addClass('geo-badvalue').css({'display': 'inline-block', 'margin-left': '5px'}).text('Bad value: ' + value).appendTo($inputdiv);
                    }else{
                        if(parsedWkt.type == 'Point'){

                            var invalid = '';
                            if(Math.abs(parsedWkt.coordinates[0]) > 180){
                                invalid = 'longitude is';
                            }
                            if(Math.abs(parsedWkt.coordinates[1]) > 90){
                                invalid = (invalid != '') ? 'longitude and latitude are' : 'latitude is';
                            }
                            $('<span>').addClass('geo-badvalue').css({'display': 'inline-block', 'margin-left': '5px', color: 'red'}).text(invalid + ' outside of range').appendTo($inputdiv);
                        }
                    }
                }
                      
                __show_mapdigit_dialog = function (event){
                        event.preventDefault();
                        
                        if(that.is_disabled) return;
                    
                        var url = window.hWin.HAPI4.baseURL 
                            +'viewers/map/mapDraw.php?db='+window.hWin.HAPI4.database;
                       
                        var wkt_params = {'wkt': that.newvalues[$input.attr('id')] };
                        if(that.options.is_faceted_search){
                            wkt_params['geofilter'] = true;
                        }

                        if(this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_GEOTIFF_SOURCE']){

                            var ele = that.options.editing.getFieldByName(window.hWin.HAPI4.sysinfo['dbconst']['DT_FILE_RESOURCE']);
                            var vals = ele.editing_input('getValues');
                            if($.isArray(vals) && vals.length>0){
                                vals = vals[0];
                                if(vals['ulf_ExternalFileReference']){
                                    wkt_params['imageurl'] = vals['ulf_ExternalFileReference'];
                                }else{
                                    wkt_params['imageurl'] = window.hWin.HAPI4.baseURL
                                        +'?db='+window.hWin.HAPI4.database
                                        +'&file='+vals['ulf_ObfuscatedFileID'];
                                }
                            }
                        }

                        window.hWin.HEURIST4.msg.showDialog(url, {
                            height:that.options.is_faceted_search?540:'900',
                            width:that.options.is_faceted_search?600:'1000',
                            window: window.hWin,  //opener is top most heurist window
                            dialogid: 'map_digitizer_dialog',
                            default_palette_class: 'ui-heurist-populate',
                            params: wkt_params,
                            title: window.hWin.HR('Heurist map digitizer'),
                            //class:'ui-heurist-bg-light',
                            callback: function(location){
                                if( !window.hWin.HEURIST4.util.isempty(location) ){
                                    //that.newvalues[$input.attr('id')] = location
                                    that.newvalues[$input.attr('id')] = (that.options.is_faceted_search
                                                ?'':(location.type+' '))
                                                +location.wkt;
                                    var geovalue = window.hWin.HEURIST4.geo.wktValueToDescription(location.type+' '+location.wkt);
                                    if(that.options.is_faceted_search){
                                        $input.val(geovalue.summary).change();
                                    }else{
                                        $input.val(geovalue.type+'  '+geovalue.summary);
                                        $input.change();

                                        $inputdiv.find('span.geo-badvalue').remove();
                                    }
                                    
                                    //$input.val(location.type+' '+location.wkt)
                                }
                            }
                        } );
                };

                //this._on( $link_digitizer_dialog, { click: __show_mapdigit_dialog } );
                //this._on( $btn_digitizer_dialog, { click: __show_mapdigit_dialog } );
                this._on( $input, { keypress: __show_mapdigit_dialog, click: __show_mapdigit_dialog } );
                this._on( $gicon, { click: __show_mapdigit_dialog } );
                
            }else if(this.configMode && this.configMode['colorpicker']){ //-----------------------------------------------
                
                $input.colorpicker({
                        hideButton: false, //show button right to input
                        showOn: "both",
                        val:value});
                $input.parent('.evo-cp-wrap').css({display:'inline-block',width:'200px'});

            }else if($Db.getConceptID('dty', this.options.dtID) == '3-1082'){ // Geo Bookmark, five input form, experimental 

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

                    popele.find('input[class="bkm_points"]').click(function(e){
                        var url = window.hWin.HAPI4.baseURL 
                            +'viewers/map/mapDraw.php?db='+window.hWin.HAPI4.database;

                        var wkt_points = $('input[id="bkm_long"]').val() + ',' + $('input[id="bkm_lat"]').val();
                        var points = wkt_points.split(/[\s,]+/);

                        var geo_points = points[0] + ',' + points[2] + ' ' + points[1] + ',' + points[3];

                        var wkt_params = {'wkt': geo_points};
                        wkt_params['start_tool'] = 'rectangle';

                        window.hWin.HEURIST4.msg.showDialog(url, {
                            height:that.options.is_faceted_search?540:'900',
                            width:that.options.is_faceted_search?600:'1000',
                            window: window.hWin,  //opener is top most heurist window
                            dialogid: 'map_digitizer_dialog',
                            default_palette_class: 'ui-heurist-populate',
                            params: wkt_params,
                            title: window.hWin.HR('Heurist map digitizer'),
                            callback: function(location){
                                if( !window.hWin.HEURIST4.util.isempty(location) ){
                                    
                                    var geovalue = window.hWin.HEURIST4.geo.wktValueToDescription(location.type+' '+location.wkt);
                                    var geocode = geovalue.summary;
                                    geocode = geocode.replace('X', '');
                                    geocode = geocode.replace('Y', '');
                                    geocode = geocode.replace(' ', '');

                                    var points = geocode.split(/[\s,]+/);

                                    $('input[id="bkm_long"]').val(points[0] + ',' + points[2]).change();
                                    $('input[id="bkm_lat"]').val(points[1] + ',' + points[3]).change();
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
                        height: 252,
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

                $input.attr('readonly','readonly');
                
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
                            var current_val = window.hWin.HEURIST4.util.isJSON($input.val());
                            if(!current_val) current_val = {};
                            window.hWin.HEURIST4.ui.showEditSymbologyDialog(current_val, false, function(new_value){
                                $input.val(JSON.stringify(new_value));
                                that.onChange();
                            });
                    }});
            
                }
             
        }//end color/symbol editor
        
        else if( this.options.dtID>0 && this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_WORLDFILE']){
            
                    var $btn_edit_switcher = $( '<span>calculate extent</span>', 
                    {title: 'Get image extent based on worldfile parameters and image width and height'})
                        //.addClass('smallicon ui-icon ui-icon-gear btn_add_term')
                        .addClass('smallbutton btn_add_term')
                        .css({'line-height': '20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
                        .appendTo( $inputdiv );
                    
                    this._on( $btn_edit_switcher, { click: function(){
                        calculateImageExtentFromWorldFile( that.options.editing );
                    }});
        }

        // Semantic url links, separated by semi-colons, for RecTypes, Vocab+Terms, DetailTypes
        if(this.options.dtID && (typeof this.options.dtID === 'string' || this.options.dtID instanceof String)
            && this.options.dtID.indexOf('ReferenceURL') !== -1){

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
        
        if( typeof dwidth==='string' && dwidth.indexOf('%')== dwidth.length-1){ //set in percents
            
            $input.css('width', dwidth);
            
        }else if ( this.detailType=='freetext' || this.detailType=='url' || this.detailType=='blocktext'  
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

                    
                this.addSecondInut( $input.attr('id') );
                    
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
                .addClass("smallbutton ui-icon ui-icon-circlesmall-close btn_input_clear")//   ui-icon
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
                        if(that.getValues()[0] == '') return;

                        var input_id = $(e.target).attr('data-input-id');  //parent(). need if button
                        
    					if (this.isFileForRecord) /* Need to hide the player and image containers, and the download link for images */
                        {
                            $parentNode = $(e.target.parentNode);
                            $parentNode.find('.thumb_image').hide();
                            $parentNode.find('.fullSize').hide();
                            $parentNode.find('.download_link').hide();
                            $parentNode.find('#player'+value.ulf_ID).hide();
                            
                            $parentNode.find(".smallText").text("Click image to freeze in place").css({
                                "font-size": "smaller", 
                                "color": "grey", 
                                "position": "", 
                                "bottom": ""
                            });						
    						
                            that._on($input, {mouseout:__hideImagePreview});
                            that._on($input_img, {mouseout:__hideImagePreview});
                            
                            isClicked = 0;
                        }
    					
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
        
        //move term error message to last 
        var trm_err = $inputdiv.find('.term-error-message');
        if(trm_err.length>0){
           trm_err.appendTo($inputdiv);
        }

        return $input.attr('id');

    }, //addInput

    //
    // Link to image fields together, to perform actions (e.g. add, change, remove) on both fields, mostly for icon and thumbnail fields
    //
    linkIconThumbnailFields: function($img_container, $img_input){
        this.linkedImgContainer = $img_container;
        this.linkedImgInput = $img_input;
    },

    //
    //
    //
    openIconLibrary: function(){                                 
        
        if(!(this.detailType=='file' && this.configMode.use_assets)) return;
        
        var that = this;
        
        this.select_imagelib_dlg.select_imagelib({onselect:function(res){
            if(res){
                that.input_img.find('img').prop('src', res.url);
                that.newvalues[$(that.inputs[0]).attr('id')] = res.path;  //$input
                that.onChange(); 
                
                
                //HARDCODED!!!! sync icon or thumb to defRecTypes
                if(res.path.indexOf('setup/iconLibrary/')>0){
                    var tosync = '', repl, toval;
                    if(that.options.dtID=='rty_Thumb'){ tosync = 'rty_Icon'; repl='64'; toval='16';}
                    else if(that.options.dtID=='rty_Icon'){tosync = 'rty_Thumb'; repl='16'; toval='64';}
               
                    if(tosync){
                        var ele = that.options.editing.getFieldByName(tosync);
                        if(ele){
                            var s_path = res.path;
                            var s_url  = res.url;
                            if(s_path.indexOf('icons8-')>0){
                                s_path = s_path.replace('-'+repl+'.png','-'+toval+'.png')
                                s_url = s_url.replace('-'+repl+'.png','-'+toval+'.png')
                            }
                            
                            var s_path2 = s_path.replace(repl,toval)
                            var s_url2 = s_url.replace(repl,toval)
                            
                            if(ele && ele.find('.image_input').length > 0){// elements in correct location
                                ele.find('.image_input').find('img').prop('src', s_url2); 
                                
                            }else if(that.linkedImgContainer !== null && that.linkedImgInput !== null)
                            {
                                if(ele){
                                    ele.editing_input('setValue', s_path2 );
                                    ele.hide();
                                } 
                                
                                that.linkedImgInput.val( s_path2 );
                                that.linkedImgContainer.find('img').prop('src', s_url2 );
                            }
                        }
                    }
                }
                
            }
        }, assets:that.configMode.use_assets, size:that.configMode.size});
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
                            +'Delete parent pointer in the child record</label><br><br>'
                        +'<label><input type="radio" value="2" name="delete_mode" checked="checked" style="outline:none"/>'
                            +'Delete the child record completely</label></div>'
                +'<div style="padding:15px 0">Warning: If you delete the parent pointer in the child record, this will generally render the record useless as it will lack identifying information.</div></div>';
                
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
        
        if(this.isFileForRecord){   //FILE FOR RECORD
            
            if(!value){   //empty value
                window.hWin.HEURIST4.ui.setValueAndWidth(ele, '');
                return;
            }

            if($.isPlainObject(value) && value.ulf_ObfuscatedFileID){

                var rec_Title = value.ulf_ExternalFileReference;
                if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                    rec_Title = value.ulf_OrigFileName;
                }
                window.hWin.HEURIST4.ui.setValueAndWidth(ele, rec_Title, 10);

                //url for thumb
                if(!window.hWin.HEURIST4.util.isempty(value['ulf_ExternalFileReference']) && value.ulf_MimeExt == 'youtube'){ // retrieve youtube thumbnail

                    var youtube_id = window.hWin.HEURIST4.util.get_youtube_id(value.ulf_ExternalFileReference);

                    if(youtube_id){

                        ele.parent().find('.image_input > img').attr('src', 'https://img.youtube.com/vi/'+ youtube_id +'/default.jpg');
                        ele.parent().find('.smallText').text("Click image to freeze in place").css({
                            "font-size": "smaller", 
                            "color": "grey", 
                            "position": "", 
                            "top": ""
                        })
                        .removeClass('invalidImg');

                        that.newvalues[ele.attr('id')] = value;
                    }else{

                        ele.parent().find('.image_input > img').removeAttr('src');
                        ele.parent().find('.smallText').text("Unable to retrieve youtube thumbnail").css({
                            "font-size": "larger", 
                            "color": "black", 
                            "position": "relative", 
                            "top": "60px"
                        })
                        .addClass('invalidImg');

                        ele.parent().find('.hideTumbnail').click();
                    }

                    ele.change();
                }else{ // check if image that can be rendered

                    window.hWin.HAPI4.checkImage("Records", value["ulf_ObfuscatedFileID"], null, function(response) {

                        if(response.data && response.status == window.hWin.ResponseStatus.OK) {

                            ele.parent().find('.image_input > img').attr('src',
								window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&thumb='+
									value.ulf_ObfuscatedFileID);

                            if($.isPlainObject(response.data) && response.data.width > 0 && response.data.height > 0) {

                                ele.parent().find(".smallText").text("Click image to freeze in place").css({
                                    "font-size": "smaller", 
                                    "color": "grey", 
                                    "position": "", 
                                    "top": ""
                                })
                                .removeClass('invalidImg');

                                that.newvalues[ele.attr('id')] = value;
                            }else{

                                ele.parent().find('.image_input > img').removeAttr('src');
                                ele.parent().find(".smallText").text("This file cannot be rendered").css({
                                    "font-size": "larger", 
                                    "color": "black", 
                                    "position": "relative", 
                                    "top": "60px"
                                })
                                .addClass('invalidImg');

                                ele.parent().find('.hideTumbnail').click();
                            }
                            ele.change();
                        }
                    });
                }
            }else{
                 //call server for file details
                 var recid = ($.isPlainObject(value))?value.ulf_ID :value;
                 if(recid>0){
                     
                     var request = {};
                        request['recID']  = recid;
                        request['a']          = 'search'; //action
                        request['details']    = 'list';
                        request['entity']     = 'recUploadedFiles';
                        request['request_id'] = window.hWin.HEURIST4.util.random();
                        
                        window.hWin.HAPI4.EntityMgr.doRequest(request,
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                        var recordset = new hRecordSet(response.data);
                                        var record = recordset.getFirstRecord();
                                        if(record){
                                            var newvalue = {ulf_ID: recordset.fld(record,'ulf_ID'),
                                                        ulf_ExternalFileReference: recordset.fld(record,'ulf_ExternalFileReference'),
                                                        ulf_OrigFileName: recordset.fld(record,'ulf_OrigFileName'),
                                                        ulf_ObfuscatedFileID: recordset.fld(record,'ulf_ObfuscatedFileID')};
                                            that._findAndAssignTitle(ele, newvalue, selector_function);
                                        }
                                }
                            });
                 }
            }
                    
        }else if(this.detailType=='file'){  // FILE FOR OTHER ENTITIES - @todo test
            
            window.hWin.HEURIST4.ui.setValueAndWidth(ele, value, 10);
            
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
                    
                
        }else{    
            //related entity                 
            if(window.hWin.HEURIST4.util.isempty(value)) value = [];
            value = $.isArray(value)?value
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
                       hasValues = false;
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
         
//console.log('_onTermChange');
                
                if(! $input.attr('radiogroup')){
                
                    if($input.hSelect("instance")!=undefined){
                        
                        var opt = $input.find('option[value="'+$input.val()+'"]');
                        var parentTerms = opt.attr('parents');
                        if(parentTerms){
                            $input.hSelect("widget").find('.ui-selectmenu-text').text( parentTerms+'.'+opt.text() );    
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

        if(!window.hWin.HEURIST4.util.isempty(allTerms)){//this is not vocabulary ID, this is something more complex

            if($.isPlainObject(this.configMode))    { 

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

                if (!$.isArray(allTerms) && !window.hWin.HEURIST4.util.isempty(allTerms)) {
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

            var headerTerms = '';
            allTerms = this.f('rst_FilteredJsonTermIDTree');        
            //headerTerms - disabled terms
            headerTerms = this.f('rst_TermIDTreeNonSelectableIDs') || this.f('dty_TermIDTreeNonSelectableIDs');
            
            if(window.hWin.HEURIST4.util.isempty(allTerms) &&
               this.options.dtID==window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'])
            { //specific behaviour - show all
                allTerms = 'relation'; //show all possible relations
            }else if(typeof allTerms == 'string' && allTerms.indexOf('-')>0){ //vocabulary concept code
                allTerms = $Db.getLocalID('trm', allTerms);
            }
            
            
            var dt_type = this.detailType;
            var topOptions = true;
            if(!window.hWin.HEURIST4.util.isempty(this.f('dty_Type'))){
                dt_type = this.f('dty_Type');
                //topOptions = false;
            }
            
            //if($input==null) $input = $('<select>').uniqueId();
            
            //this.options.useHtmlSelect native select produce double space for option  Chrome touch screen
            
            //vocabulary
            $input = window.hWin.HEURIST4.ui.createTermSelect($input.get(0),
                {vocab_id:allTerms, //headerTermIDsList:headerTerms,
                    defaultTermID:value, topOptions:topOptions, supressTermCode:true, 
                    useHtmlSelect:this.options.useHtmlSelect});

            if(typeof openSearchMenu!=='undefined' && $.isFunction(openSearchMenu) && this.options.recordset && this.options.recordset.entityName == 'Records'){

                var search_icon = window.hWin.HAPI4.baseURL+'hclient/assets/filter_icon_black18.png';

                var opt = document.createElement('option');

                opt.text = '<div style="width:175px;padding:10px 0px">'
                +'<span style="padding-right:10px;vertical-align:sub">'
                +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                + '" class="rt-icon rt-icon2" style="background-image: url(&quot;'+search_icon+ '&quot;);"/></span>'
                +'<input class="input_menu_filter" size="15" style="outline: none;background:none;border: 1px solid lightgray;"/>'
                +'<span class="smallbutton ui-icon ui-icon-circlesmall-close" tabindex="-1" title="Clear entered value" '
                +'style="position:relative; cursor: pointer; outline: none; box-shadow: none; border-color: transparent;"></span>'
                + '<div class="not-found" style="padding:10px;color:darkgreen;display:none;">No terms match the filter</div></div>';

                opt.value = 'select';

                $input.get(0).prepend(opt);

                $input.hSelect('destroy');

                var performedOnce = 0;

                window.hWin.HEURIST4.ui.initHSelect($input, this.options.useHtmlSelect, null, function(){ 

                    if(performedOnce == 0){

                        // Extra indenting + re-label blank option
                        $input.hSelect('menuWidget').find('div[role="option"]').each(function(idx, opt){

                            var l_pad = 0;
                            var in_styles = $(opt).attr('style');
                            in_styles = in_styles.slice(0, in_styles.length - 1).split(';').findIndex(function(style){
                                if(style.indexOf('padding-left:') >= 0){
                                    l_pad = parseFloat(style.split(':')[1]) + 1;
                                }
                            });
                            $(opt).css('padding-left', l_pad + 'em');

                            if(idx == 1){
                                $(opt).text('<blank>');
                            }
                        });

                        performedOnce = 1;
                    }

                    openSearchMenu(that, $input, true); // perform special function, found under editing_exts.js
                });
            }

            var opts = $input.find('option');      
            if(opts.length==0 || (opts.length==1 && $(opts[0]).text()=='')){
               $input.hSelect('widget').html('<span style="padding: 0.1em 2.1em 0.2em 0.2em">no terms defined, please add terms</span><span class="ui-selectmenu-icon ui-icon ui-icon-triangle-1-e"></span>'); 
            }
            
            var err_ele = $input.parent().find('.term-error-message');
            if(err_ele.length>0){
                err_ele.remove();
            }
            
            //show error message on init                    
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
+'<hr/>'
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
                                                $input.change();
                                                
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
                                    that._recreateSelector($input, newvalue);
                                }
                            );
                            
                        }});
                        
                    }//is_admin
                }
                
                
            }    
            
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
                
                if(that.detailType=='file'){
                    that.input_cell.find('img.image_input').prop('src','');

                    if(that.linkedImgInput !== null){
                        that.linkedImgInput.val('');
                        that.newvalues[that.linkedImgInput.attr('id')] = '';
                    }
                    if(that.linkedImgContainer !== null){
                        that.linkedImgContainer.find('img').prop('src', '');
                    }
                }else if(that.detailType=='resource'){
                    
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
                if(that.detailType=='date' || that.detailType=='file'){
                    $input.change();
                }else{
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
        
        if(!$.isArray(values)) values = [values];

        var isReadOnly = (this.options.readonly || this.f('rst_Display')=='readonly');
        
        var i;
        for (i=0; i<values.length; i++){
            if(isReadOnly){
                this._addReadOnlyContent(values[i]);
            }else{
                var inpt_id = this._addInput(values[i]);
            }
        }
        if (isReadOnly || (make_as_nochanged==true)) {
            this.options.values = values;
        }

        var repeatable = (Number(this.f('rst_MaxValues')) != 1);
        if(values.length>1 && !repeatable){
            this.showErrorMsg('Repeated value for a single value field - please correct');
        }else{
            //this.showErrorMsg(null);
        }
        
        this._setAutoWidth();            
        
        if($.isFunction(this.options.onrecreate)){
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

        if(!(this.detailType=="resource" || this.detailType=='file' 
            || this.detailType=='date' || this.detailType=='geo'))
        {
            if($input.attr('radiogroup')>0){
                res = $input.find('input:checked').val();
            }else if(this.detailType=='boolean'){
                if($.isArray(this.configMode) && this.configMode.length==2) {
                    res = this.configMode[ $input.is(':checked')?0:1 ];
                }else{
                    res = $input.is(':checked') ?$input.val() :0;        
                }       
                
            }else{
                res = $input.val();
            }
            
            if(!window.hWin.HEURIST4.util.isnull(res) && res!=''){
                res = res.trim();
            }
        }else {
            res = this.newvalues[$input.attr('id')];
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
        
        if(this.options.readonly || this.f('rst_Display')=='readonly') return;
        var idx, ele_after = this.firstdiv; //this.error_message;
        for (idx in this.inputs) {
            var ele = this.inputs[idx].parents('.input-div');
            ele.insertAfter(ele_after);
            ele_after = ele;
        }    },
    
    //
    // get all values (order is respected)
    //
    getValues: function(){

        if(this.options.readonly || this.f('rst_Display')=='readonly'){
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
                            res = '';
                        }else{
                            res  = res+'<>'+res2;
                        }
                    }
                                    
                    var ele = $input.parents('.input-div');
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

    
    //
    //
    //
    setDisabled: function(is_disabled){
        //return;
        if(!(this.options.readonly || this.f('rst_Display')=='readonly')){
            var idx;
            for (idx in this.inputs) {
                if(!this.isFileForRecord) {  //this.detailType=='file'
                    var input_id = this.inputs[idx];
                    var $input = $(input_id);
                    window.hWin.HEURIST4.util.setDisabled($input, is_disabled);
                }
            }
            this.is_disabled = is_disabled;
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

            if(this.options.readonly || this.f('rst_Display')=='readonly'){
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
    //
    //    
    setUnchanged: function(){
        
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
    //
    //
    validate: function(){

        if (this.f('rst_Display')=='hidden' || this.f('rst_Display')=='readonly') return true;
        
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
                    ($.type(ress[0])=='string' && ress[0].trim()=='')) {
                    
                    
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
        if(!this.options.readonly && this.inputs && this.inputs.length>0 
            && $(this.inputs[0]).is(':visible') 
            && !$(this.inputs[0]).hasClass('ui-state-disabled') )
        {
            $(this.inputs[0]).focus();   
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
                
        if($.isArray(value)){

            disp_value = value[1]; //record title, relation description, filename, human readable date and geo

        }else if(this.detailType=="enum" || this.detailType=="relationtype"){

            disp_value = $Db.getTermValue(value);

            if(window.hWin.HEURIST4.util.isempty(value)) {
                disp_value = 'term missing. id '+value;
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

            disp_value = "@todo relation "+value;

            //@todo NEW datatypes
        } else if(this.detailType=="geo"){

            /*if(detailType=="query")
            if(detailType=="color")
            if(detailType=="bool")
            if(detailType=="password")*/

            disp_value = "@todo geo "+value;


        } else if(this.detailType=="url"){

            var def_value = this.f('rst_DefaultValue');
            if(window.hWin.HEURIST4.util.isempty(value)) value = def_value;
            
            if(!window.hWin.HEURIST4.util.isempty(value) &&
               !(value.indexOf('http://')==0 || value.indexOf('https://')==0)){
                value = 'http://'+ value;
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

        /*
        if(this.detailType=="url"){

            $btn_extlink = $( '<span>', {title: 'Open URL in new window'})
                .addClass('smallicon ui-icon ui-icon-extlink')
                .appendTo( $inputdiv.find('div') );
        
            this._on( $btn_extlink, { click: function(){ window.open(value, '_blank') }} );
        
        }
        */   
    },
    
    //
    // browse for heurist uploaded/registered files/resources and add player link
    //         
    _addHeuristMedia: function(){

        var popup_options = {
            isdialog: true,
            select_mode: 'select_single',
            edit_addrecordfirst: false, //show editor atonce
            selectOnSave: true,
            select_return_mode:'recordset', //ids or recordset(for files)
            filter_group_selected:null,
            //filter_groups: this.configMode.filter_group,
            onselect:function(event, data){

                if(data){

                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                        var recordset = data.selection;
                        var record = recordset.getFirstRecord();

                        var thumbURL = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                        +"&thumb="+recordset.fld(record,'ulf_ObfuscatedFileID');

                        var playerTag = recordset.fld(record,'ulf_PlayerTag');

                        tinymce.activeEditor.insertContent( playerTag );
                    }

                }//data

            }
        };//popup_options        

        window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles', popup_options);
    },


    //
    //
    //
    _prepareIds: function(ptrset)
    {
        if(!$.isArray(ptrset)){
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
    _createDateInput: function($input, $inputdiv){
      
        $input.css('width', this.options.is_faceted_search?'13ex':'20ex');
        
        var that = this;

        function __onDateChange(){
            var value = $input.val();
            
            that.newvalues[$input.attr('id')] = value; 
            
            if(that.options.dtID>0){
                var isTemporalValue = value && value.search(/\|VER/) != -1; 
                if(isTemporalValue) {
                    window.hWin.HEURIST4.ui.setValueAndWidth($input, temporalToHumanReadableString(value));    
                    
                    $input.addClass('Temporal').removeClass('text').attr('readonly','readonly');
                }else{
                    $input.removeClass('Temporal').addClass('text').removeAttr("readonly").css('width','20ex');
                }
            }
            
            that.onChange();
        }

        function translateDate(date, from_calendar, to_calendar){

            if(!$.isFunction($('body').calendarsPicker)){
                return date;
            }

            if(typeof date == 'string'){
                var date_parts = date.split('-');
                date = {};
                date['year'] = date_parts[0];

                if(date_parts.length >= 2){
                    date['month'] = date_parts[1];
                }
                if(date_parts.length == 3){
                    date['day'] = date_parts[2];
                }
            }

            var new_cal = from_calendar.newDate(date['year'], date['month'], date['day']);
            if(!new_cal){
                return date;
            }

            var julian_date = new_cal._calendar.toJD(Number(new_cal.year()), Number(new_cal.month()), Number(new_cal.day()));
            return to_calendar.fromJD(julian_date);
        }

        var defDate = $input.val();
        var $tinpt = $('<input type="hidden" data-picker="'+$input.attr('id')+'">')
                        .val(defDate).insertAfter( $input );

        if($.isFunction($('body').calendarsPicker)){ // third party extension for jQuery date picker, used for Record editing

            var calendar = $.calendars.instance('gregorian');
            var g_calendar = $.calendars.instance('gregorian');
            var temporal = null;

            try {
                temporal = Temporal.parse($input.val());
            } catch(e) {
                temporal = null;
            }
            var cal_name = temporal ? temporal.getField('CLD') : null;
            var tDate = temporal ? temporal.getTDate("DAT") : null;

            if(!window.hWin.HEURIST4.util.isempty($input.val()) && tDate && cal_name && cal_name.toLowerCase() !== 'gregorian'){

                // change calendar to current type
                calendar = $.calendars.instance(cal_name);                

                if(tDate && tDate.getYear()){
                    var hasMonth = tDate.getMonth();
                    var hasDay = tDate.getDay();

                    var month = hasMonth ? tDate.getMonth() : 1;
                    var day = hasDay ? tDate.getDay() : 1;

                    defDate = translateDate({'year': tDate.getYear(), 'month': month, 'day': day}, g_calendar, calendar);
                }
            }else if(tDate){
                // remove padding zeroes from year
                var year = Number(tDate.getYear());
                defDate = tDate.toString('yyyy-MM-dd');
                defDate = defDate.replace(tDate.getYear(), year);
            }

            $tinpt.val(defDate);

            $tinpt.calendarsPicker({
                calendar: calendar,
                defaultDate: defDate,
                //selectDefaultDate: false,
                showOnFocus: false,
                dateFormat: 'yyyy-mm-dd',
                pickerClass: 'calendars-jumps',
                onShow: function($calendar, calendar_locale, config){
                    config.div.css('z-index', 60000);
                },
                onSelect: function(date){

                    var cur_cal = $tinpt.calendarsPicker('option', 'calendar');
                    var value = $tinpt.val();
                    var val_parts = value != '' ? value.split('-') : '';
                    var new_temporal = new Temporal();

                    if(val_parts.length == 4 && val_parts[0] == ''){ // for BC years
                        val_parts.shift();
                        val_parts[0] = '-'+val_parts[0];
                    }

                    if(window.hWin.HEURIST4.util.isArrayNotEmpty(val_parts) && val_parts.length == 3 && cur_cal.local.name.toLowerCase() != 'gregorian'){

                        var g_value = translateDate({'year': val_parts[0], 'month': val_parts[1], 'day': val_parts[2]}, cur_cal, g_calendar);
                        g_value = g_calendar.formatDate('yyyy-mm-dd', g_value);

                        if(g_value != ''){
                            try {

                                var new_tdate = TDate.parse(g_value);

                                new_temporal.setType('s');
                                new_temporal.setTDate('DAT', new_tdate);
                                new_temporal.addObjForString('CLD', cur_cal.local.name);
                                new_temporal.addObjForString('CL2', value);

                                value = new_temporal.toString();
                            } catch(e) {}
                        }
                    }

                    $input.val(value);
                    window.hWin.HAPI4.save_pref('edit_record_last_entered_date', $input.val());
                    __onDateChange();
                },
                renderer: $.extend({}, $.calendars.picker.defaultRenderer,
                        {picker: $.calendars.picker.defaultRenderer.picker.
                            replace(/\{link:prev\}/, '{link:prevJump}{link:prev}').
                            replace(/\{link:next\}/, '{link:nextJump}{link:next}')}),
                showTrigger: '<span class="smallicon ui-icon ui-icon-calendar" style="display:inline-block" data-picker="'+$input.attr('id')+'" title="Show calendar" />'}
            );

            this._on($input, {
                'blur': function(event){ //update to changed value
                    $tinpt.val($input.val());
                }
            });
        }else{ // we use jquery datepicker for general use

                /*var $tinpt = $('<input type="hidden" data-picker="'+$input.attr('id')+'">')
                        .val($input.val()).insertAfter( $input );*/

                var $btn_datepicker = $( '<span>', {title: 'Show calendar'})
                    .attr('data-picker',$input.attr('id'))
                    .addClass('smallicon ui-icon ui-icon-calendar')
                    .insertAfter( $tinpt );
                    
                
                var $datepicker = $tinpt.datepicker({
                    /*showOn: "button",
                    buttonImage: "ui-icon-calendar",
                    buttonImageOnly: true,*/
                    showButtonPanel: true,
                    changeMonth: true,
                    changeYear: true,
                    dateFormat: 'yy-mm-dd',
                    beforeShow: function(){
                        
                        if(that.is_disabled) return false;
                        var cv = $input.val();
                        
                        var prev_dp_value = window.hWin.HAPI4.get_prefs('edit_record_last_entered_date'); 
                        if(cv=='' && !window.hWin.HEURIST4.util.isempty(prev_dp_value)){
                            //$datepicker.datepicker( "setDate", prev_dp_value );    
                            $datepicker.datepicker( "option", "defaultDate", prev_dp_value); 
                        }else if(cv!='' && cv.indexOf('-')<0){
                            $datepicker.datepicker( "option", "defaultDate", cv+'-01-01'); 
                        }else if(cv!='') {
                            $tinpt.val($input.val());
                            //$datepicker.datepicker( "option", "setDate", cv); 
                        }
                    },
                    onClose: function(dateText, inst){
                        
                        if($tinpt.val()!=''){
                            $input.val($tinpt.val());
                            window.hWin.HAPI4.save_pref('edit_record_last_entered_date', $input.val());
                            __onDateChange();
                        }else{
                            $tinpt.val($input.val());
                        }
                    }
                });
                
                this._on( $input, {
                    keyup: function(event){
                        if(!isNaN(String.fromCharCode(event.which))){
                            var cv = $input.val();
                            if(cv!='' && cv.indexOf('-')<0){
                                $datepicker.datepicker( "setDate", cv+'-01-01');   
                                $input.val(cv);
                            }
                        }
                    },
                    keypress: function (e) {
                        var code = e.charCode || e.keyCode;
                        var charValue = String.fromCharCode(code);
                        var valid = false;

                        if(charValue=='-'){
                            valid = true;
                        }else{
                            valid = /^[0-9]+$/.test(charValue);
                        }

                        if(!valid){
                            window.hWin.HEURIST4.util.stopEvent(e);
                            e.preventDefault();
                        }

                    },
                    dblclick: function(){
                        $btn_datepicker.click();
                    }
                });

                //.button({icons:{primary: 'ui-icon-calendar'},text:false});
               
                
                this._on( $btn_datepicker, { click: function(){
                    
                        if(that.is_disabled) return;
                        
                        $datepicker.datepicker( 'show' ); 
                        $("#ui-datepicker-div").css("z-index", "999999 !important"); 
                        //$(".ui-datepicker").css("z-index", "999999 !important");   
                }} );
        } 

        if(this.options.is_faceted_search){
            
                $input.css({'max-width':'13ex','min-width':'13ex'});
            
        }else if(this.options.dtID>0){ //this is details of records
        
            
                var $btn_temporal = $( '<span>', 
                    {title: 'Pop up widget to enter compound date information (uncertain, fuzzy, radiometric etc.)'})
                .addClass('smallicon ui-icon ui-icon-clock')
                .appendTo( $inputdiv );
                //.button({icons:{primary: 'ui-icon-clock'}, text:false});
                this._on( $btn_temporal, { click: function(){
                    
                    if(that.is_disabled) return;

                    var url = window.hWin.HAPI4.baseURL 
                        + 'hclient/widgets/editing/editTemporalObject.html?'
                        + encodeURIComponent(that.newvalues[$input.attr('id')]
                                    ?that.newvalues[$input.attr('id')]:$input.val());
                    
                    window.hWin.HEURIST4.msg.showDialog(url, {height:570, width:750,
                        title: 'Temporal Object',
                        class:'ui-heurist-populate-fade',
                        //is_h6style: true,
                        default_palette_class: 'ui-heurist-populate',
                        callback: function(str){
                            if(!window.hWin.HEURIST4.util.isempty(str) && that.newvalues[$input.attr('id')] != str){
                                $input.val(str);    
                                $input.change();
                            }

                            if($.isFunction($('body').calendarsPicker) && $tinpt.hasClass('hasCalendarsPicker')){

                                var new_temporal = null;
                                var new_cal = null;
                                var new_date = null;
                                try {
                                    new_temporal = Temporal.parse(str);
                                    new_cal = new_temporal.getField('CLD');
                                    new_cal = $.calendars.instance(new_cal);
                                    new_date = new_temporal.getTDate("DAT");
                                } catch(e) {
                                    new_cal = null;
                                    new_date = null;
                                }
    
                                // Update calendar for calendarPicker
                                if(new_cal && new_date && typeof $tinpt !== 'undefined'){
    
                                    if(new_date.getYear()){
                                        var hasMonth = new_date.getMonth();
                                        var hasDay = new_date.getDay();
    
                                        var month = hasMonth ? new_date.getMonth() : 1;
                                        var day = hasDay ? new_date.getDay() : 1;
    
                                        new_date = translateDate({'year': new_date.getYear(), 'month': month, 'day': day}, g_calendar, new_cal);
                                        new_date = new_date.formatDate('yyyy-mm-dd', new_cal);
                                    }
    
                                    var cur_cal = $tinpt.calendarsPicker('option', 'calendar');
                                    if(cur_cal.local.name.toLowerCase() != new_cal.local.name.toLowerCase()){
                                        $tinpt.calendarsPicker('option', 'calendar', new_cal);
                                    }
    
                                    if(typeof new_date == 'string'){
                                        $tinpt.val(new_date);
                                    }
                                }
                            }
                        }
                    } );
                }} );
            
        }//temporal allowed
        
        $input.change(__onDateChange);
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
                this.addSecondInut();           
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
    addSecondInut: function(input_id){

        var that = this;
        $.each(this.inputs, function(idx, item){

            var $input = $(item);
            if(input_id==null || $input.attr('id')==input_id){
                
                var $inputdiv = $input.parents('.input-div');
                
                var edash = $('<span class="span-dash">&nbsp;-&nbsp;</span>')
                //duplicate input for between mode
                if(that.detailType=='date') {
                    
                    
                    //var dpicker = that.element.find('input[data-picker="'+$input.attr('id')+'"]');
                    var dpicker_btn = that.element.find('span[data-picker="'+$input.attr('id')+'"]');
                    
                    edash.insertAfter(dpicker_btn);
                    
                    var inpt2 = $('<input>').attr('id',$input.attr('id')+'-2')
                            .addClass('text ui-widget-content ui-corner-all')
                            .change(function(){
                                that.onChange();
                            })
                            .insertAfter(edash);
                            
                    window.hWin.HEURIST4.ui.disableAutoFill( $inpt2 );
                            
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

        var child_terms = $Db.trm_TreeData(vocab_id, 'set');
        var asButtons = this.options.recordset && this.options.recordset.entityName=='Records' && this.f('rst_TermsAsButtons');

        if(asButtons == 1 && child_terms.length <= 20){ // recreate buttons/checkboxes

            this.enum_buttons = (Number(this.f('rst_MaxValues')) != 1) ? 'checkbox' : 'radio';
            var dtb_res = this._createEnumButtons(true, child_terms);

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
                $input = that._recreateSelector($input, value);
                $input.hSelect('widget').css('width','auto');
                that._on( $input, {change:that._onTermChange} );
            });
        }

        // Show/Hide select by picture
        var trm_img_req = {
            'a': 'search',
            'entity': 'defTerms',
            'details': 'list',
            'trm_ID': child_terms,
            'withimages': 1,
            'request_id': window.hWin.HEURIST4.util.random()
        };

        window.hWin.HAPI4.EntityMgr.doRequest(trm_img_req, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                var recset = new hRecordSet(response.data);
                if(recset.length() > 0){
                    that.input_cell.find('.ui-icon-image').show();
                }else{
                    that.input_cell.find('.ui-icon-image').hide();
                }
            }
        });
    },

    //
    // Set up checkboxes/radio buttons for enum field w/ rst_TermsAsButtons set to 1
    // Params:
    //	isRefresh (bool): whether to clear $inputdiv first
    //	terms_list (array): array of term ids
    //	$inputdiv (jQuery Obj): element where inputs will be placed
    //	values (array): array of existing values to check by default
    //
    _createEnumButtons: function(isRefresh, terms_list, $inputdiv, values){

        var that = this;

        var vocab_id = this.f('rst_FilteredJsonTermIDTree');

        if(terms_list == null){
            terms_list = $Db.trm_TreeData(vocab_id, 'set');
        }
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
                .change(function(event){ 

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

                                            $last_ele.parents('.input-div').remove();
                                            that.inputs.splice(last_idx, 1);

                                            return false;
                                        }
                                    }
                                });
                            }
                        }else{

                            that.new_value = changed_val;

                            that.btn_add.click();
                        }
                    }

                    that.onChange();
                });

            var $label = $('<label>', {'title': trm_label, append: [$btn, trm_label]})
                            .addClass('truncate enum_input')
                            .css({
                                'max-width': '120px',
                                'min-width': '120px',
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
    }
});
