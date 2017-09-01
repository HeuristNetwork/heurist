/**
* Widget for input controls on edit form
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

        //field desription is taken from window.hWin.HEURIST4.rectypes
        rectypes: null,  // reference to window.hWin.HEURIST4.rectypes - defRecStructure
        rectypeID: null, //field description is taken either from rectypes[rectypeID] or from dtFields
        dtID: null,      // field type id (for recDetails) or field name (for other Entities)

        //  it is either from window.hWin.HEURIST4.rectype.typedefs.dtFields - retrieved with help rectypeID and dtID
        // object with some mandatory field names
        dtFields: null,

        values: null,
        readonly: false,
        title: '',  //label (overwrite Display label from record structure)
        showclear_button: true,
        show_header: true, //show/hide label
        suppress_prompts:false, //supress help, error and required features
        detailtype: null,  //overwrite detail type from db (for example freetext instead of memo)
        
        change: null  //onchange callback
    },

    //newvalues:{},  //keep actual value for resource (recid) and file (ulfID)
    detailType:null,
    configMode:null, //configuration settings, mostly for enum and resource types (from field rst_FieldConfig)
    
    isFileForRecord:false,

    // the constructor
    _create: function() {

        //field description is taken either from rectypes[rectypeID] or from dtFields
        if(this.options.dtFields==null && this.options.dtID>0 && this.options.rectypeID>0 &&
            this.options.rectypes && this.options.rectypes.typedefs && this.options.rectypes.typedefs[this.options.rectypeID])
        {

            this.options.dtFields = this.options.rectypes.typedefs[this.options.rectypeID].dtFields[this.options.dtID];
        }

        if(this.options.dtFields==null){ //field description is not defined
            return;
        }
        this.detailType = this.options.detailtype ?this.options.detailtype :this.f('dty_Type');
        

        this.configMode = this.f('rst_FieldConfig');
        if(!window.hWin.HEURIST4.util.isempty(this.configMode)){
            if($.type(this.configMode) === "string"){
                try{
                    this.configMode = $.parseJSON(this.configMode);
                }catch(e){
                    this.configMode = null;
                }
            }
            if(!$.isPlainObject(this.configMode)){
                this.configMode = null;
            }
        }
        //by default
        if((this.detailType=="resource" || this.detailType=='file') 
            && window.hWin.HEURIST4.util.isempty(this.configMode))
        {
            this.configMode= {entity:'records'};
        }

//console.log('create '+this.options.dtID);        
//console.log('vals='+this.options.values);

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
            .css('vertical-align', (this.detailType=="blocktext" || this.detailType=='file')?'top':'')
            .html('<label>' + lblTitle + '</label>')
            .appendTo( this.element );
        }
        
        //repeat button        
        if(this.options.readonly || this.f('rst_Display')=='readonly') {

            //spacer
            $( "<span>")
            .addClass('editint-inout-repeat-button')
            .css({'min-width':'16px', display:'table-cell'})
            .appendTo( this.element );

        }else{

            var repeatable = (Number(this.f('rst_MaxValues')) != 1)? true : false; //saw TODO this really needs to check many exist

            
            if(!repeatable || this.options.suppress_repeat){  
                //spacer
                $( "<span>")
                .addClass('editint-inout-repeat-button')
                .css({'min-width':'16px', display:'table-cell'})
                .appendTo( this.element );
                
            }else{ //multiplier button
                this.btn_add = $( "<button>")
                .addClass("smallbutton editint-inout-repeat-button")
                //.css({'font-size':'2em'})
                //.css('display','table-cell')
                //.css({width:'16px', height:'16px', display:'table-cell'})
                .appendTo( this.element )
                .button({icons:{primary: "ui-icon-circlesmall-plus"}, text:false, label:'Add another ' + lblTitle +' value'})
                .css({display:'table-cell'});
                
                this.btn_add.find('span.ui-icon').css({'font-size':'2em'});
                
                // bind click events
                this._on( this.btn_add, {
                    click: function(){
                        if( !(Number(this.f('rst_MaxValues'))>0)  || this.inputs.length < this.f('rst_MaxValues')){
                            this._addInput('');
                        }
                    }
                });
            }
        }        
        


        //input cell
        this.input_cell = $( "<div>")
        .addClass('input-cell')
        .appendTo( this.element );

        //add hidden error message div

        this.error_message = $( "<div>")
        .hide()
        .addClass('heurist-prompt ui-state-error')
        .css({'height': 'auto',
            'padding': '0.2em',
            'margin-bottom': '0.2em'})
        .appendTo( this.input_cell );

        //add prompt
        var help_text = this.f('rst_DisplayHelpText');
        this.input_prompt = $( "<div>")
        .text( help_text && !this.options.suppress_prompts ?help_text:'' )
        .addClass('heurist-helper1').css('padding-bottom','1em');
        if(window.hWin.HAPI4.get_prefs('help_on')!=1){
            this.input_prompt.hide();
        }
        this.input_prompt.appendTo( this.input_cell );


        //values are not defined - assign default value
        var values_to_set;

        if( !window.hWin.HEURIST4.util.isArray(this.options.values) ){
            var def_value = this.f('rst_DefaultValue');
            if(window.hWin.HEURIST4.util.isempty(def_value)){
                values_to_set = [''];        
            }else if(window.hWin.HEURIST4.util.isArray(def_value)){
                values_to_set = def_value;
            }else{
                values_to_set = [def_value];
            }
        }else{
            values_to_set = this.options.values.slice();
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
    },
    
    _setOptions: function( ) {
        this._superApply( arguments );
        this._refresh();
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        if(this.btn_add){
            this.btn_add.remove();
        }
        // remove generated elements
        if(this.rec_search_dialog){
            this.rec_search_dialog.remove();
        }
        if(this.rec_relation_dialog){
            this.rec_relation_dialog.remove();
        }
        if(this.header){
            this.header.remove();
        }
        if(this.inputs){
            $.each(this.inputs, function(index, value){ value.remove(); } );
            this.input_cell.remove();
        }
    },

    /**
    * get value for given record type structure field
    *
    * dtFields - either from rectypes.typedefs and index is taken from dtFieldNamesToIndex
    *          - or it is object with following list of properties
    dty_Type,
    rst_DisplayName,  //label
    rst_DisplayHelpText  (over dty_HelpText)           //hint
    rst_DisplayExtendedDescription  (over dty_ExtendedDescription) //rollover

    rst_RequirementType,  //requirement
    rst_MaxValues     //repeatability

    rst_DisplayWidth - width in characters

    rst_PtrFilteredIDs
    rst_FilteredJsonTermIDTree      @todo rename to rst_FieldConfig (over dty_JsonConfig)
    rst_TermIDTreeNonSelectableIDs
    dty_TermIDTreeNonSelectableIDs
    *
    *
    * @param fieldname
    */
    f: function(fieldname){

        var val = this.options['dtFields'][fieldname]; //try get by name
        if(window.hWin.HEURIST4.util.isnull(val) && this.options.rectypes){ //try get by index
            var fi = this.options.rectypes.typedefs.dtFieldNamesToIndex;
            val = this.options['dtFields'][fi[fieldname]];
        }
        if(window.hWin.HEURIST4.util.isempty(val)){ //some default values
            if(fieldname=='rst_RequirementType') val = 'optional'
            else if(fieldname=='rst_MaxValues') val = 1
                else if(fieldname=='dty_Type') val = 'freetext'
                    else if(fieldname=='rst_DisplayWidth'
                        && (this.f('dty_Type')=='freetext' || this.f('dty_Type')=='blocktext' || this.f('dty_Type')=='resource')) {
                            val = 55;
                        }
        }
        return val;

        /*}else{
        var rfrs = this.options.rectypes.typedefs[this.options.rectypeID].dtFields[this.options.dtID];
        var fi = this.options.rectypes.typedefs.dtFieldNamesToIndex;
        return rfrs[fi[fieldname]];
        }*/
    },


    //
    //
    //
    _removeInput: function(input_id){
        if(this.inputs.length>1){
            //find in array
            var that = this;
            $.each(this.inputs, function(idx, item){

                var $input = $(item);
                if($input.attr('id')==input_id){
                    if(that.newvalues[input_id]){
                        delete that.newvalues[input_id];
                    }
                    
                    if(that.detailType=='file'){
                        $input.fileupload('destroy');
                        $input.remove();
                        that.input_cell.find('.input-div').remove();
                    }else{
                        //remove element
                        $input.parents('.input-div').remove();
                    }
                    //remove from array
                    that.inputs.splice(idx,1);
                    return;
                }

            });

        }else{  //and clear last one
            this._clearValue(input_id, '');
        }
        
    },
    
    
    _onChange: function(event){
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


        var $inputdiv = $( "<div>" ).addClass('input-div').insertBefore(this.input_prompt);  //.appendTo( this.input_cell );

        if(this.detailType=='blocktext'){

            $input = $( "<textarea>",{rows:3})
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .val(value)
            .keyup(function(){that._onChange();})
            .change(function(){that._onChange();})
            .appendTo( $inputdiv );

        }else if(this.detailType=='enum' || this.detailType=='relationtype'){

            $input = $( '<select>' )
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .change(function(){
                
                $input.find('option[term-view]').each(function(idx,opt){
                    $(opt).text($(opt).attr('term-view'));
                });
                
                var opt = $input.find( "option:selected" );
                var parentTerms = opt.attr('parents');
                if(parentTerms){
                     opt.text(parentTerms+'.'+opt.attr('term-orig'));
                }
                that._onChange();
            })
            .appendTo( $inputdiv );

            this._recreateSelector($input, value);
            
            if($input.val()!=value){ //value is not allowed
                
            }
            
            var allTerms = this.f('rst_FieldConfig');    
            //allow edit terms only for true defTerms enum
            if(window.hWin.HEURIST4.util.isempty(allTerms)){
                
                allTerms = this.f('rst_FilteredJsonTermIDTree');        

                var isVocabulary = !isNaN(Number(allTerms)); 

                var $btn_termedit = $( '<span>', {title: 'Add new term to this list'})
                .addClass('smallicon ui-icon ui-icon-gear')
                .appendTo( $inputdiv );
                //.button({icons:{primary: 'ui-icon-gear'},text:false});
                
                this._on( $btn_termedit, { click: function(){
                    
                if(isVocabulary){

                    var type = (this.detailType!='enum')?'relation':'enum';
                    
                    var url = window.hWin.HAPI4.baseURL 
                        + 'admin/structure/terms/editTermForm.php?db='+window.hWin.HAPI4.database
                        + '&treetype='+type+'&parent='+Number(allTerms);
                    
                    window.hWin.HEURIST4.msg.showDialog(url, {height:320, width:650,
                        title: 'Add Term',
                        //class:'ui-heurist-bg-light',
                        callback: function(context){
                            if(context=="ok") {
                                window.hWin.HEURIST4.terms = window.hWin.HEURIST.terms;
                                that._recreateSelector($input, true);
                            }
                        }
                    } );
                    

                }else{
                    
                    var url = window.hWin.HAPI4.baseURL 
                        + 'admin/structure/terms/selectTerms.html?mode=editrecord&db='
                        + window.hWin.HAPI4.database
                        + '&detailTypeID='+this.options.dtID;

                    window.hWin.HEURIST4.msg.showDialog(url, {height:540, width:750,
                        title: 'Select Term',
                        //class:'ui-heurist-bg-light',
                        callback: function(editedTermTree, editedDisabledTerms){
                            if(editedTermTree || editedDisabledTerms) {
                                window.hWin.HEURIST4.terms = window.hWin.HEURIST.terms;
                                
                                that.options['dtFields']['rst_FilteredJsonTermIDTree'] = editedTermTree;
                                that.options['dtFields']['rst_TermIDTreeNonSelectableIDs'] = editedDisabledTerms
                                                                
                                that._recreateSelector($input, true);
                            }
                        }
                    });


                }                
                
                
                
                }} ); //end btn onclick
            
            }//allow edit terms only for true defTerms enum
            

        }else if(this.detailType=='boolean'){

            $input = $( '<input>',{type:'checkbox', value:'1'} )
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('vertical-align','-3px')
            .change(function(){that._onChange();})
            .appendTo( $inputdiv );

            if(!(value==false || value=='0' || value=='n')){
                $input.prop('checked','checked');
            }

        }else if(this.detailType=="rectype"){

            $input = $( "<select>" )
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .change(function(){that._onChange();})
            .appendTo( $inputdiv );

            window.hWin.HEURIST4.ui.createRectypeSelect($input.get(0),null, this.f('cst_EmptyValue'));
            if(value){
                $input.val(value);
            }

        }else if(this.detailType=="user"){ //special case - only groups of current user

            $input = $( "<select>")
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .change(function(){that._onChange();})
            .appendTo( $inputdiv );

            window.hWin.HEURIST4.ui.createUserGroupsSelect($input.get(0),null,
                [{key:'',title:window.hWin.HR('select user/group...')},
                    {key:window.hWin.HAPI4.currentUser['ugr_ID'], title:window.hWin.HAPI4.currentUser['ugr_FullName'] }] );
            if(value){
                $input.val(value);
            }

        }
        else if(this.detailType=="relmarker"){ 
            
                this.options.showclear_button = false;
                $inputdiv.css({'display':'inline-block','vertical-align':'middle'});
            
                if(this.inputs.length==0){ //show current relations
                
                    function __onRelRemove(){
                        //console.log('>>>>'+that.element.find('.link-div').length);
                        if( that.element.find('.link-div').length==0){ //hide this button if there are links
                            that.element.find('.rel_link').show()
                        }
                    }
                
                    var __show_addlink_dialog = function(){
                            var url = window.hWin.HAPI4.baseURL 
                                +'hclient/framecontent/recordAddLink.php?db='+window.hWin.HAPI4.database
                                +'&source_ID=' + that.options.recID
                                +'&dty_ID=' + that.options.dtID;
                            
                            window.hWin.HEURIST4.msg.showDialog(url, {height:380, width:600,
                                title: window.hWin.HR('Add relationship'),
                                class:'ui-heurist-bg-light',
                                callback: function(context){
                                    
                                    //add new element
                //context = {rec_ID: targetIDs[0], rec_Title:sTargetName, rec_RecTypeID:target_RecTypeID,                     relation_recID:0, trm_ID:termID };
                                    if(context && context.count>0){
                                        var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($inputdiv,
                                            context, true);
                                        ele.insertBefore(that.element.find('.rel_link'));
                                        that.element.find('.rel_link').hide();//hide this button if there are links
                                        ele.on('remove', __onRelRemove);
                                    }
                                    
                                }
                            } );
                    };
                
                
                    var sRels = '';
                    if(that.options.recordset){
                    
                    var relations = that.options.recordset.getRelations();
                    if(relations && relations.direct){
                        
                        var ptrset = that.f('rst_PtrFilteredIDs');
                        var allTerms = this.f('rst_FilteredJsonTermIDTree');        
                        var headerTerms = this.f('rst_TermIDTreeNonSelectableIDs') || this.f('dty_TermIDTreeNonSelectableIDs');
                        //var terms = window.hWin.HEURIST4.ui.getPlainTermsList(this.detailType, allTerms, headerTerms, null);
                        
                        var headers = relations.headers;
                        var direct = relations.direct;
                        
                        var ph_gif = window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif';
                        
                        for(var k in direct){
                            //direct[k]['dtID']==this.options.dtID && 
                            if(direct[k]['trmID']>0){ //relation   
                            
                                //verify that target rectype is satisfy to constraints and trmID allowed
                                var targetID = direct[k].targetID;
                                var targetRectypeID = headers[targetID][2];
                                
                                if(window.hWin.HEURIST4.ui.isTermInList(this.detailType, allTerms, headerTerms, direct[k]['trmID']))                               {
                                    var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($inputdiv, 
                                        {rec_ID: targetID, 
                                         rec_Title: headers[targetID][0], 
                                         rec_RecTypeID: headers[targetID][1], 
                                         relation_recID: direct[k]['relationID'], 
                                         trm_ID: direct[k]['trmID']}, true);
                                    ele.on('remove', __onRelRemove);
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

                   
                   //define explicit add relationship button
                   var $btn_add_rel_dialog = $( "<button>", {title: "Click to add new relationship"})
                        .addClass("rel_link") //.css({display:'block'})
                        .button({icons:{primary: "ui-icon-circle-plus"},label:'Add Relationship'})
                        .appendTo( $inputdiv );
                
                   $btn_add_rel_dialog.click(function(){__show_addlink_dialog()});
                   
                   if( this.element.find('.link-div').length>0){ //hide this button if there are links
                        $btn_add_rel_dialog.hide();
                   }

                
                }else{
                    //this is second call - some links are already defined
                    //show popup dialog at once
                    //IJ ASKS to disbale it __show_addlink_dialog();
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
        else if(this.detailType=="resource" && this.configMode.entity=='records'){

            //replace input with div
            $input = $( "<div>").css({'display':'inline-block','vertical-align':'middle','min-wdith':'20ex'})
                            .uniqueId().appendTo( $inputdiv );
            
            //define explicit add relationship button
            $( "<button>", {title: "Select record to be linked"})
                        .button({icons:{primary: "ui-icon-triangle-1-e"},label:'&nbsp;&nbsp;&nbsp;Select Record'})
                        .addClass('sel_link2')
                        .appendTo( $inputdiv );
            
            //var $btn_rec_search_dialog = 
            var isparententity = (that.f('rst_CreateChildIfRecPtr')==1);
            var popup_options = {
                            isdialog: true,
                            select_mode: (this.configMode.csv==true?'select_multi':'select_single'),
                            
                            select_return_mode: 'recordset',
                            edit_mode: 'popup',
                            selectOnSave: true,
                            title: window.hWin.HR('Record pointer: Select or create a linked record'),
                            rectype_set: that.f('rst_PtrFilteredIDs'),
                            parententity: (isparententity)?that.options.recID:0,
                            
                            onselect:function(event, data){
                                     if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                        var recordset = data.selection;
                                        var record = recordset.getFirstRecord();
                                        var targetID = recordset.fld(record,'rec_ID');
                                        var rec_Title = recordset.fld(record,'rec_Title');
                                        var rec_RecType = recordset.fld(record,'rec_RecTypeID');
                                        that.newvalues[$input.attr('id')] = targetID;
                                        //window.hWin.HEURIST4.ui.setValueAndWidth($input, rec_Title);
                                        
                                        $input.empty();
                                        var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($input, 
                                            {rec_ID: targetID, 
                                             rec_Title: rec_Title, 
                                             rec_RecTypeID: rec_RecType}, true);
                                        //ele.appendTo($inputdiv);
                                        that._onChange();
                                        
                                        if($inputdiv.find('.sel_link').length==0){
                                            var $btn_rec_search_dialog = $( "<span>", {title: "Click to search and select"})
                                                .addClass('smallicon sel_link ui-icon ui-icon-pencil')
                                                        .insertAfter( $input );
                                                        
                                            that._on( $btn_rec_search_dialog, { click: __show_select_dialog } );
                                        }else{
                                            $inputdiv.find('.sel_link').css({display:'inline-block'});
                                        }
                                        
                                        if( $inputdiv.find('.link-div').length>0 ){ //hide this button if there are links
                                            $inputdiv.find('.sel_link2').hide(); //was that.element
                                        }
                                        
                                     }
                            }
            };


            // event is false for confirmation of select mode for parententity
            // 
            var __show_select_dialog = function(event){
                
                    if(event!==false){
                
                        if(event) event.preventDefault();
                        
                        if(popup_options.parententity>0){
                            
                            if(that.newvalues[$input.attr('id')]>0){
                                
                                window.hWin.HEURIST4.msg.showMsgFlash('Points to a child record; value cannot be changed (delete it or edit the child record itself)', 2500);
                                return;
                            }
                            
                            
                            //show preliminary dialog that offer to create new record instead 
                            var popele = that.element.find('.child_info_dlg');
                            if(popele.length==0){
                                var sdiv = '<div class="child_info_dlg"><p style="padding:15px 0">You are creating a parent-child (whole-part,containership) connection. We normally recommend creating a new record whcih becomes the child of the current record</p><p>If child records have already been uploaded to the database, you may select one</p><p style="padding:15px 0"><label>Record type: </label><select id="sel_rectypes"></select></p></div>';
                                popele = $(sdiv).appendTo(that.element);
                            }
                            
                            var new_rec_param = null;
                            var selector_rectype = popele.find('select');
                            
                            if(popup_options.rectype_set.indexOf(',')>0){ //multiconstraint need to show selector
                                selector_rectype.empty().parent().show();
                                window.hWin.HEURIST4.ui.createRectypeSelect(selector_rectype.get(0), 
                                                popup_options.rectype_set,null);
                                btn_add_title = window.hWin.HR('Create new record');
                            }else{
                                new_rec_param = {rt:popup_options.rectype_set};
                                selector_rectype.empty().parent().hide();
                                btn_add_title = window.hWin.HR('Create new')
                                    +' '+window.hWin.HEURIST4.rectypes.names[popup_options.rectype_set];
                            }
                            
                            var $dlg_pce = null;
                            
                            var btns = [
                                    {text: btn_add_title,
                                          click: function() { 
                                              
                                              if(new_rec_param==null){
                                                  new_rec_param = {rt:selector_rectype.val()};
                                              }
                                              
                                              window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                                    {new_record_params:new_rec_param, onselect:popup_options.onselect, selectOnSave:true});
                                              $dlg_pce.dialog('close'); 
                                          }},
                                    {text:window.hWin.HR('Select'),
                                          click: function() { __show_select_dialog(false); $dlg_pce.dialog('close'); }},
                                    {text:window.hWin.HR('Cancel'),
                                          click: function() { $dlg_pce.dialog('close'); }}
                            ];
                            
                            
                            $dlg_pce = window.hWin.HEURIST4.msg.showElementAsDialog({
                                window:  window.hWin, //opener is top most heurist window
                                title: window.hWin.HR('Create or select child record'),
                                width: 400,
                                height: 250,
                                element:  popele[0],
                                resizable: false,
                                buttons: btns
                            })
                            
                            return;
                        }
                    }
                    
                    var usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_'+that.configMode.entity, 
                        {width: null,  //null triggers default width within particular widget
                        height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });
        
                    popup_options.width = usrPreferences.width;
                    popup_options.height = usrPreferences.height;
                    
                    //init selection dialog
                    window.hWin.HEURIST4.ui.showEntityDialog(that.configMode.entity, popup_options);
            }

            that._findAndAssignTitle($input, value);
            
            if(value>0){
                        var $btn_rec_search_dialog = $( "<span>", {title: "Click to search and select"})
                            .addClass('smallicon sel_link ui-icon ui-icon-pencil')
                                    .insertAfter( $input );
                            //.button({icons:{primary: 'ui-icon-pencil'},text:false}); //wasui-icon-link
                        this._on( $btn_rec_search_dialog, { click: __show_select_dialog } );
            }
            
            this._on( $inputdiv.find('.sel_link2'), { click: __show_select_dialog } );

            if($inputdiv.find('.link-div').length>0){
                $inputdiv.find('.sel_link2').hide();
            }
           
            /* IJ asks to disable this feature 
            if( this.inputs.length>0 || this.element.find('.link-div').length>0){ //hide this button if there are links
                $inputdiv.find('.sel_link2').hide();
            }else{
                $inputdiv.find('.sel_link2').show();
            }
            
            //open dialog for second and further            
            if(this.inputs.length>0 && !(value>0))  __show_select_dialog();
            */
            
            this.newvalues[$input.attr('id')] = value;    


            
        } 
        else{
            $input = $( "<input>")
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .val(value)
            .keyup(function(){that._onChange();})
            .change(function(){
                    that._onChange();
            })
            .appendTo( $inputdiv );
            
            
            if(this.options.dtID=='rec_URL'){
                
                    var $btn_extlink = null, $btn_editlink = null;
                
                    function __url_input_state(force_edit){
                    
                        if($input.val()=='' || force_edit===true){
                            $input.removeClass('rec_URL').addClass('text').removeAttr("readonly");
                            that._off( $input, 'click');
                            if(!window.hWin.HEURIST4.util.isnull( $btn_extlink)){
                                $btn_extlink.remove();
                                $btn_editlink.remove();
                                $btn_extlink = null;
                                $btn_editlink = null;
                            }
                            if(force_edit===true){
                                $input.focus();   
                            }
                        }else if(window.hWin.HEURIST4.util.isnull($btn_extlink)){
                            
                            if(!($input.val().indexOf('http://')==0 || $input.val().indexOf('https://')==0)){
                                $input.val( 'http://'+$input.val());
                            }
                            $input.addClass('rec_URL').removeClass('text').attr('readonly','readonly');
                            
                            $btn_extlink = $( '<span>', {title: 'Open URL in new window'})
                                .addClass('smallicon ui-icon ui-icon-extlink')
                                .appendTo( $inputdiv );
                                //.button({icons:{primary: 'ui-icon-extlink'},text:false});
                        
                            that._on( $btn_extlink, { click: function(){ window.open($input.val(), '_blank') }} );
                            that._on( $input, { click: function(){ window.open($input.val(), '_blank') }} );

                            $btn_editlink = $( '<span>', {title: 'Edit URL'})
                                .addClass('smallicon ui-icon ui-icon-pencil')
                                .appendTo( $inputdiv );
                                //.button({icons:{primary: 'ui-icon-pencil'},text:false});
                        
                            that._on( $btn_editlink, { click: function(){ __url_input_state(true) }} );
                        }
                
                    }
                
                    $input.focusout( __url_input_state ); 
                    
                    __url_input_state();               
                
            }
            else if(this.detailType=="integer" || this.detailType=="year"){

                $input.keypress(function (e) {
                    var code = (e.keyCode ? e.keyCode : e.which);
                    var charValue = String.fromCharCode(e.keyCode);
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
                /*$input.keyup(function () {
                if (this.value != this.value.replace(/[^0-9-]/g, '')) {
                this.value = this.value.replace(/[^0-9-]/g, '');  //[-+]?\d
                }
                });*/
            }else
            if(this.detailType=="float"){

                    $input.keypress(function (e) {
                        var code = (e.keyCode ? e.keyCode : e.which);
                        var charValue = String.fromCharCode(e.keyCode);
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

            }else
            if(this.detailType=='date'){
                
                $input.css('width','20ex');
                

                function __onDateChange(){
                            var value = $input.val();
                            
                            that.newvalues[$input.attr('id')] = value; 
                            
                            var isTemporalValue = value && value.search(/\|VER/) != -1; 
                            if(isTemporalValue) {
                                window.hWin.HEURIST4.ui.setValueAndWidth($input, temporalToHumanReadableString(value));    
                                
                                $input.addClass('Temporal').removeClass('text').attr('readonly','readonly');
                            }else{
                                $input.removeClass('Temporal').addClass('text').removeAttr("readonly").css('width','20ex');
                            }
                            
                            that._onChange();
                }
                
                
                if($.isFunction($('body').calendarsPicker)){
                        
                    var defDate = window.hWin.HAPI4.get_prefs("record-edit-date");
                    $input.calendarsPicker({
                        calendar: $.calendars.instance('gregorian'),
                        showOnFocus: false,
                        defaultDate: defDate?defDate:'',
                        selectDefaultDate: true,
                        dateFormat: 'yyyy-mm-dd',
                        pickerClass: 'calendars-jumps',
                        //popupContainer: $input.parents('body'),
                        onSelect: function(dates){
                        },
                        renderer: $.extend({}, $.calendars.picker.defaultRenderer,
                                {picker: $.calendars.picker.defaultRenderer.picker.
                                    replace(/\{link:prev\}/, '{link:prevJump}{link:prev}').
                                    replace(/\{link:next\}/, '{link:nextJump}{link:next}')}),
                        showTrigger: '<span class="ui-icon ui-icon-calendar trigger" style="display:inline-block" alt="Popup"></span>'}
                    );     
                           
                }else{
                        var $datepicker = $input.datepicker({
                            /*showOn: "button",
                            buttonImage: "ui-icon-calendar",
                            buttonImageOnly: true,*/
                            showButtonPanel: true,
                            changeMonth: true,
                            changeYear: true,
                            dateFormat: 'yy-mm-dd',
                            onClose: function(dateText, inst){
                                __onDateChange();
                                //$input.change();
                            }
                            /*,beforeShow : function(dateText, inst){
                                $(inst.dpDiv[0]).find('.ui-datepicker-current').click(function(){
                                    console.log('today '+$datepicker.datepicker( 'getDate' ));  
                                });
                            }*/
                        });

                        var $btn_datepicker = $( '<span>', {title: 'Show calendar'})
                            .addClass('smallicon ui-icon ui-icon-calendar')
                            .appendTo( $inputdiv );
                        //.button({icons:{primary: 'ui-icon-calendar'},text:false});

                       
                       
                        
                        this._on( $btn_datepicker, { click: function(){
                                $datepicker.datepicker( 'show' ); 
                                $("#ui-datepicker-div").css("z-index", "999999 !important"); 
                                //$(".ui-datepicker").css("z-index", "999999 !important");   
                        }} );
                } 

                if(this.options.dtID>0){ //this is details of records
                
                    var $btn_temporal = $( '<span>', 
                        {title: 'Pop up widget to enter compound date information (uncertain, fuzzy, radiometric etc.)'})
                    .addClass('smallicon ui-icon ui-icon-clock')
                    .appendTo( $inputdiv );
                    //.button({icons:{primary: 'ui-icon-clock'}, text:false});
                    
                    this._on( $btn_temporal, { click: function(){
                        
                                var url = window.hWin.HAPI4.baseURL 
                                    + 'common/html/editTemporalObject.html?'
                                    + encodeURIComponent(that.newvalues[$input.attr('id')]
                                                ?that.newvalues[$input.attr('id')]:$input.val());
                                
                                window.hWin.HEURIST4.msg.showDialog(url, {height:550, width:750,
                                    title: 'Temporal Object',
                                    //class:'ui-heurist-bg-light',
                                    callback: function(str){
                                        if(!window.hWin.HEURIST4.util.isempty(str) && that.newvalues[$input.attr('id')] != str){
                                            $input.val(str);    
                                            $input.change();
                                        }
                                    }
                                } );
                    
                    }} );
                    
                    $input.change(__onDateChange);
                   
                }//temporal allowed
                else{
                    that.newvalues[$input.attr('id')] = value;            
                }
                
                $input.val(value);    
                $input.change();   

            }else 
            if(this.detailType=="resource" || this.isFileForRecord)
            {
                        var $input_img;
                        var select_return_mode = 'ids';
                        if(this.isFileForRecord){
                            
                            icon_for_button = 'ui-icon-folder-open';
                            select_return_mode = 'recordset';
                            
                            //container for image
                            $input_img = $('<div class="image_input ui-widget-content ui-corner-all">'
                            + '<img class="image_input"></div>').css({'position':'absolute','display':'none','z-index':9999})
                            .appendTo( $inputdiv ); 
                            
                            $input.change(function(){
                                if(!(that.newvalues[$input.attr('id')]>0)){
                                    $input.val('');
                                }
                                if(window.hWin.HEURIST4.util.isempty($input.val())){
                                     $input_img.find('img').attr('src','');    
                                }
                                that._onChange(); 
                            });
                            
                            var hideTimer = 0;
                            $input.mouseover(function(){
                                if(!window.hWin.HEURIST4.util.isempty($input_img.find('img').attr('src'))){
                                    if (hideTimer) {
                                        window.clearTimeout(hideTimer);
                                        hideTimer = 0;
                                    }
                                    $input_img.show();
                                }
                            });
                            $input.mouseout(function(){
                                if($input_img.is(':visible')){
                                    hideTimer = window.setTimeout(function(){$input_img.hide(1000);}, 500);
                                }});
                                
                        }else{
                            icon_for_button = 'ui-icon-link';
                            if(this.configMode.select_return_mode &&
                               this.configMode.select_return_mode!='ids'){
                                 select_return_mode = 'recordset'
                            }
                        }

                        $input.css({'min-wdith':'20ex'});

                        var ptrset = that.f('rst_PtrFilteredIDs');

                        var __show_select_dialog = null;
                        var $btn_rec_search_dialog = $( "<span>", {title: "Click to search and select"})
                        .addClass('smallicon ui-icon '+icon_for_button)
                        .appendTo( $inputdiv );
                        //.button({icons:{primary: icon_for_button},text:false});


/*          //SELECTOR in POPUP URL
    
                                var url = window.hWin.HAPI4.baseURL +
                                'hclient/framecontent/recordSelect.php?db='+window.hWin.HAPI4.database+
                                '&rectype_set='+that.f('rst_PtrFilteredIDs');
                                
                                if(that.f('rst_PtrFilteredIDs')==1){
                                    url =  url + '&parententity=' + that.options.recID;
                                }
                                
                                
                                window.hWin.HEURIST4.msg.showDialog(url, {height:600, width:600,
                                    title: window.hWin.HR('Select linked record'),
                                    window:  window.hWin, //opener is top most heurist window
                                    class:'ui-heurist-bg-light',
                                    callback: function(recordset){
                                        if( window.hWin.HEURIST4.util.isRecordSet(recordset) ){
                                            var record = recordset.getFirstRecord();
                                            var rec_Title = recordset.fld(record,'rec_Title');
                                            that.newvalues[$input.attr('id')] = recordset.fld(record,'rec_ID');
                                            window.hWin.HEURIST4.ui.setValueAndWidth($input, rec_Title);
                                            $input.change();
                                        }
                                    }
                                } );
*/                                 
                         
                        var popup_options = {
                            isdialog: true,
                            select_mode: (this.configMode.csv==true?'select_multi':'select_single'),
                            
                            select_return_mode:select_return_mode, //ids or recordset(for files)
                            filter_group_selected:null,
                            filter_groups: this.configMode.filter_group,
                            onselect:function(event, data){

                             if(data){
                                
                                if(that.isFileForRecord){

                                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                        var recordset = data.selection;
                                        var record = recordset.getFirstRecord();
                                        var rec_Title = recordset.fld(record,'ulf_ExternalFileReference');
                                        if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                                            rec_Title = recordset.fld(record,'ulf_OrigFileName');
                                        }
                                        that.newvalues[$input.attr('id')] = recordset.fld(record,'ulf_ID');
                                        window.hWin.HEURIST4.ui.setValueAndWidth($input, rec_Title);

                                        //url for thumb
                                        $inputdiv.find('.image_input > img').attr('src',
                                            window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&thumb='+
                                            recordset.fld(record,'ulf_ObfuscatedFileID'));

                                        $input.change();
                                    }

                                }
                                else if( data ){

                                    if(select_return_mode=='ids'
                                        && window.hWin.HEURIST4.util.isArrayNotEmpty(data.selection) ){
                                        //config and data are loaded already, since dialog was opened
                                        window.hWin.HAPI4.EntityMgr.getTitlesByIds(that.configMode.entity, data.selection,
                                            function( display_value ){
                                                var rec_Title = display_value.join(',');
                                                window.hWin.HEURIST4.ui.setValueAndWidth($input, rec_Title);
                                        });
                                        that.newvalues[$input.attr('id')] = data.selection.join(',');
                                        $input.change();
                                    }else if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                        //todo

                                    }

                                }
                                
                             }//data

                            }
                        };//popup_options
                        

                        that._findAndAssignTitle($input, value);

                        __show_select_dialog = function(event){
                            
                                event.preventDefault();
                                
                                var usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_'+this.configMode.entity, 
                                    {width: null,  //null triggers default width within particular widget
                                    height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });
                    
                                popup_options.width = usrPreferences.width;
                                popup_options.height = usrPreferences.height;
                                
                                //init dialog
                                window.hWin.HEURIST4.ui.showEntityDialog(this.configMode.entity, popup_options);
                        }
                        
                        if(__show_select_dialog!=null){
                            this._on( $btn_rec_search_dialog, { click: __show_select_dialog } );
                            this._on( $input, { keypress: __show_select_dialog, click: __show_select_dialog } );
                        }
                        
                        if(this.isFileForRecord && value){
                            this.newvalues[$input.attr('id')] = value.ulf_ID;
                        }else{
                            this.newvalues[$input.attr('id')] = value;    
                        }
                        

            }else 
            if( this.detailType=='file' ){
                
                        //url for thumb
                        var urlThumb = window.hWin.HAPI4.getImageUrl(this.configMode.entity, this.options.recID, 'thumbnail');
                        
                        //container for image
                        var $input_img = $('<div class="image_input ui-widget-content ui-corner-all">'
                            + '<img src="'+urlThumb+'" class="image_input"></div>').appendTo( $inputdiv );                
                            
                        //browse button    
                        var $btn_fileselect_dialog = $( "<span>", {title: "Click to select file for upload"})
                        .addClass('smallicon fileupload ui-icon ui-icon-folder-open')
                        .css('vertical-align','top')
                        .appendTo( $inputdiv );
                        //.button({icons:{primary: "ui-icon-folder-open"},text:false});
                        
                        //set input as file and hide
                        $input.prop('type','file').hide();
                        
                        //temp file name 
                        var newfilename = '~'+window.hWin.HEURIST4.util.random();

                        //init upload widget
                        $input.fileupload({
    url: window.hWin.HAPI4.baseURL +  'hserver/utilities/fileUpload.php',  //'ext/jquery-file-upload/server/php/',
    //url: 'templateOperations.php',
    formData: [ {name:'db', value: window.hWin.HAPI4.database}, 
                {name:'entity', value:this.configMode.entity},
                //{name:'DBGSESSID', value:'425944380594800002;d=1,p=0,c=07'},
                {name:'newfilename', value:newfilename }], //unique temp name
    //acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i
    //autoUpload: true,
    sequentialUploads:true,
    dataType: 'json',
    dropZone: $input_img,
    // add: function (e, data) {  data.submit(); },
    done: function (e, response) {
            response = response.result;
            if(response.status==window.hWin.HAPI4.ResponseStatus.OK){
                var data = response.data;

                $.each(data.files, function (index, file) {
                    if(file.error){ //it is not possible we should cought it on server side - just in case
                        $input_img.find('img').prop('src', '');
                        window.hWin.HEURIST4.msg.showMsgErr(file.error);
                    }else{

                        if(file.ulf_ID>0){
                            that.newvalues[$input.attr('id')] = file.ulf_ID;
                        }else{
                            $input_img.find('img').prop('src', file.thumbnailUrl);
                            that.newvalues[$input.attr('id')] = newfilename;
                        }
                        $input.attr('title', file.name);
                        that._onChange();//need call it manually since onchange event is redifined by fileupload widget
                    }
                });
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response.message);
            }
            var inpt = this;
            $input_img.off('click');
            $input_img.on({click: function(){
                        $(inpt).click();
            }});
            },                            
    progressall: function (e, data) { // to implement
        var progress = parseInt(data.loaded / data.total * 100, 10);
//console.log(progress + '%  '+data.loaded + ' of ' + data.total);
        //$('#progress .bar').css('width',progress + '%');
    }                            
                        });
                
                        //init click handlers
                        this._on( $btn_fileselect_dialog, { click: function(){ $input_img.click(); } } );
                        $input_img.on({click: function(){ //find('a')
                                $input.click();}
                        }); 
            }
            else //------------------------------------------------------------------------------------
            if(this.detailType=="geo"){
                
                $input.css({'width':'20ex','padding-left':'30px'});
                //browse button    
                $('<span>').addClass('ui-icon ui-icon-globe')
                    .css({position:'absolute',margin:'5px 0 0 6px'})
                    .insertBefore($input);
                
                var $btn_digitizer_dialog = $( "<span>", {title: "Click to draw map location"})
                        .addClass('smallicon ui-icon ui-icon-pencil')
                        .appendTo( $inputdiv );
                        //.button({icons:{primary: "ui-icon-pencil"},text:false});
                var $link_digitizer_dialog = $( '<a>', {title: "Click to draw map location"})
                        .text( window.hWin.HR('Edit'))
                        .css('cursor','pointer')
                        .appendTo( $inputdiv );
            
                var geovalue = window.hWin.HEURIST4.util.wktValueToDescription(value);
            
                that.newvalues[$input.attr('id')] = value;
                $input.val(geovalue.type+'  '+geovalue.summary);
                      
                __show_mapdigit_dialog = function __show_select_dialog(event){
                    event.preventDefault();

                    var url = window.hWin.HAPI4.baseURL +
                    'hclient/framecontent/mapDraw.php?db='+window.hWin.HAPI4.database+
                    '&wkt='+that.newvalues[$input.attr('id')]; //$input.val();
                    
                    window.hWin.HEURIST4.msg.showDialog(url, {height:'800', width:'1000',
                        title: window.hWin.HR('Heurist map digitizer'),
                        window:  window.hWin, //opener is top most heurist window
                        class:'ui-heurist-bg-light',
                        callback: function(location){
                            if( !window.hWin.HEURIST4.util.isempty(location) ){
                                //that.newvalues[$input.attr('id')] = location
                                that.newvalues[$input.attr('id')] = location.type+' '+location.wkt;
                                var geovalue = window.hWin.HEURIST4.util.wktValueToDescription(location.type+' '+location.wkt);
                                
                                $input.val(geovalue.type+'  '+geovalue.summary).change();
                                //$input.val(location.type+' '+location.wkt)
                            }
                        }
                    } );
                };

                this._on( $link_digitizer_dialog, { click: __show_mapdigit_dialog } );
                this._on( $btn_digitizer_dialog, { click: __show_mapdigit_dialog } );
                this._on( $input, { keypress: __show_mapdigit_dialog, click: __show_mapdigit_dialog } );
            }
            /*else if(this.detailType=="freetext" && this.options['input_width']){
            $input.css('width', this.options['input_width']);
            }*/

        }

        if (parseFloat( this.f('rst_DisplayWidth') ) > 0 
            &&  this.detailType!='boolean' && this.detailType!='date') {    //if the size is greater than zero
                $input.css('width', Math.round(2 + Math.min(120, Number(this.f('rst_DisplayWidth')))) + "ex"); //was *4/3
        }


        this.inputs.push($input);

        //name="type:1[bd:138]"

        //clear button
        //var $btn_clear = $( "<div>")
        if(this.options.showclear_button && this.options.dtID!='rec_URL')
        {

            var $btn_clear = $('<button>',{
                title: 'Clear entered value',
                'data-input-id': $input.attr('id')
            })
            .addClass("smallbutton btn_input_clear")
            .css({'vertical-align': (this.detailType=='blocktext' || this.detailType=='file')?'top':'',
'margin-left': (this.detailType=='relmarker' || this.detailType=='geo' || 
                this.detailType=='file' || this.detailType=='resource' || this.detailType=='enum'  || this.detailType=='date')
                        ?'0px':'-17px'
            })
            .appendTo( $inputdiv )
            .button({icons:{primary: "ui-icon-circlesmall-close"},text:false});
            //.position( { my: "right top", at: "right top", of: $($input) } );

            // bind click events
            this._on( $btn_clear, {
                click: function(e){

                    var input_id = $(e.target).parent().attr('data-input-id');
                    
                    if(that.detailType=="resource" && that.configMode.entity=='records' 
                            && that.f('rst_CreateChildIfRecPtr')==1){
                        that._clearChildRecordPointer( input_id );
                    }else{
                        that._removeInput( input_id );
                    }
                }
            });

        }

        return $input.attr('id');

    }, //addInput
    
    //
    //
    //
    _clearChildRecordPointer: function( input_id ){
        
            var that = this;
        
            var popele = that.element.find('.child_delete_dlg');
            if(popele.length==0){
                var sdiv = '<div class="child_delete_dlg"><p style="padding:15px 0">You are deleting a pointer to a child record, that is a record which is owned by/an integral part of the current record, as identified by a pointer back from the child to the current record.</p><p>Actions:<br><label><input type="radio" value="1" name="delete_mode"/>Delete parent pointer in the child record</label><br><label><input type="radio" value="2" name="delete_mode" checked="checked"/>Delete the child record completely</label></p><p style="padding:15px 0">Warning: If you delete the parent pointer in the child record, this will generally render the record useless as it will lack identifying information.</p></div>';
                
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
                                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                        
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
    //
    //
    _findAndAssignTitle: function(ele, value){
        
        if(!value){
            window.hWin.HEURIST4.ui.setValueAndWidth(ele, '');
            return;
        }
        
        var that = this;
        
        if(this.isFileForRecord){
            
            var rec_Title = value.ulf_ExternalFileReference;
            if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                rec_Title = value.ulf_OrigFileName;
            }
            window.hWin.HEURIST4.ui.setValueAndWidth(ele, rec_Title);
            
            //url for thumb
            ele.parent().find('.image_input > img').attr('src',
                window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&thumb='+
                    value.ulf_ObfuscatedFileID);
                    
        }else if(this.detailType=='file'){
            
            window.hWin.HEURIST4.ui.setValueAndWidth(ele, value);
            
        }else if(this.configMode.entity==='records'){
                //assign initial display value
                if(Number(value)>0){
                    var sTitle = null;
                    if(that.options.recordset){
                        var relations = that.options.recordset.getRelations();
                        if(relations && relations.headers && relations.headers[value]){
                            
                            var rec_Title = window.hWin.HEURIST4.util.htmlEscape(relations.headers[value][0]);
                            ele.empty();
                            window.hWin.HEURIST4.ui.createRecordLinkInfo(ele, 
                                            {rec_ID: value, 
                                             rec_Title: rec_Title, 
                                             rec_RecTypeID: relations.headers[value][1]}, true);

                            //window.hWin.HEURIST4.ui.setValueAndWidth(ele, rec_Title);
                        }
                    }
                    if(!sTitle){
                        window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+value, w: "all", f:"header"}, 
                            function(response){
                                if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                    var recordset = new hRecordSet(response.data);
                                    var record = recordset.getFirstRecord();
                                    var rec_Title = recordset.fld(record,'rec_Title');
                                    var rec_RecType = recordset.fld(record,'rec_RecTypeID');
                                    ele.empty();
                                    window.hWin.HEURIST4.ui.createRecordLinkInfo(ele, 
                                            {rec_ID: value, 
                                             rec_Title: rec_Title, 
                                             rec_RecTypeID: rec_RecType}, true);

                                    //window.hWin.HEURIST4.ui.setValueAndWidth(ele, rec_Title);
                                }
                            }
                        );
                    }
                }else{
                    window.hWin.HEURIST4.ui.setValueAndWidth(ele, '');
                }
        }else{                     
            window.hWin.HAPI4.EntityMgr.getTitlesByIds(this.configMode.entity, value.split(','),
                   function( display_value ){
                        var rec_Title  = display_value.join(',');           
                        window.hWin.HEURIST4.ui.setValueAndWidth(ele, rec_Title);
                   });
        }
        
    },

    //
    // recreate SELECT for enum/relation type
    //
    _recreateSelector: function($input, value){
        
        if(value===true){
            //keep current
            value = $input.val();
        }

        $input.empty();

        var allTerms = this.f('rst_FieldConfig');

        if(!window.hWin.HEURIST4.util.isempty(allTerms)){//this is not vocabulary ID, this is something more complex

            if($.isPlainObject(this.configMode)){ //this lookup for entity

                //create and fill SELECT
                //this.configMode.entity
                //this.configMode.filter_group

                //add add/browse buttons
                window.hWin.HEURIST4.ui.createEntitySelector($input.get(0), this.configMode, true, null);

                if(this.configMode.button_browse){

                }

            }else{

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
                    //array of key:title objects
                    window.hWin.HEURIST4.ui.createSelector($input.get(0), allTerms);
                }
            }
            if(!window.hWin.HEURIST4.util.isnull(value)){
                $input.val(value); 
            }  

        }else{ //this is usual enumeration from defTerms

            allTerms = this.f('rst_FilteredJsonTermIDTree');        
            //headerTerms - not used anymore
            var headerTerms = this.f('rst_TermIDTreeNonSelectableIDs') || this.f('dty_TermIDTreeNonSelectableIDs');

            //vocabulary
            window.hWin.HEURIST4.ui.createTermSelectExt2($input.get(0),
                {datatype:this.detailType, termIDTree:allTerms, headerTermIDsList:headerTerms,
                    defaultTermID:value, topOptions:true, supressTermCode:true});
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
                }else if(that.detailType=='resource' && that.configMode.entity == 'records'){
                    $input.parent().find('.sel_link').hide();
                    $input.parent().find('.sel_link2').show();
                    $input.empty();
                }else if(that.detailType=='relmarker'){    
                    this.element.find('.rel_link').show();
                }else{
                    $input.val( display_value?display_value :value);    
                }
                if(that.detailType=='date' || that.detailType=='file'){
                    $input.change();
                }else{
                    that._onChange();
                }
                return;
            }

        });

    },

    //
    // recreate input elements and assign values
    //
    setValue: function(values){

        //clear previous inputs
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
        if (isReadOnly) {
            this.options.values = values;
        }

    },

    //
    // get value for particular input element
    //  input_id - id or element itself
    //
    _getValue: function(input_id){

        if(this.detailType=="relmarker") return null;
        
        var res = null;
        var $input = $(input_id);

        if(!(this.detailType=="resource" || this.detailType=='file' || this.detailType=='date' || this.detailType=='geo')){
            res = $input.val();
        }else {
            res = this.newvalues[$input.attr('id')];
        }

        return res;
    },

    //
    //
    //
    getValues: function(){

        if(this.options.readonly || this.f('rst_Display')=='readonly'){
            return this.options.values;
        }else{
            var idx;
            var ress = [];
            for (idx in this.inputs) {
                var res = this._getValue(this.inputs[idx]);
                if(!window.hWin.HEURIST4.util.isempty( res ) || ress.length==0){ //provide the only empty value  || res.trim()!=''
                    if(window.hWin.HEURIST4.util.isnull( res )) res = '';
                    ress.push(res)
                }
            }
            return ress;
        }

    },

    
    //
    //
    //
    setDisabled: function(is_disabled){

        if(!(this.options.readonly || this.f('rst_Display')=='readonly')){
            var idx;
            for (idx in this.inputs) {
                var input_id = this.inputs[idx];
                var $input = $(input_id);
                window.hWin.HEURIST4.util.setDisabled($input, is_disabled);
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
    // returns array of input elements
    //
    getInputs: function(){
        return this.inputs;
    },

    validate: function(){

        if (this.options.dtFields.rst_Display=='hidden' ||
        this.options.dtFields.rst_Display=='readonly') return true;
        
        var req_type = this.f('rst_RequirementType');
        var max_length = this.f('dty_Size');
        var data_type = this.f('dty_Type');
        var errorMessage = '';

        if(req_type=='required'){

            var ress = this.getValues();

            if(ress.length==0 || window.hWin.HEURIST4.util.isempty(ress[0]) || ress[0].trim()=='' ){
                //error highlight
                $(this.inputs[0]).addClass( "ui-state-error" );
                //add error message
                errorMessage = 'Field is requried. Please specify value';

            }else if((data_type=='freetext' || data_type=='blocktext') && ress[0].length<4){
                //errorMessage = 'Field is requried. Please specify value';
            }
        }
        //verify max alowed size
        if(max_length>0 &&
            (data_type=='freetext' || data_type=='blocktext')){

            var idx;
            for (idx in this.inputs) {
                var res = this._getValue(this.inputs[idx]);
                if(!window.hWin.HEURIST4.util.isempty( res ) && res.length>max_length){
                    //error highlight
                    $(this.inputs[idx]).addClass( "ui-state-error" );
                    //add error message
                    errorMessage = 'Field is requried. Please specify value';
                }
            }
        }
        if(data_type=='integer' || this.detailType=='year'){
            //@todo validate 
            
        }else if(data_type=='float'){
            
            
        }
        

        if(errorMessage!=''){
            this.error_message.text(errorMessage);
            this.error_message.show();
        }else{
            this.error_message.hide();
            $(this.inputs).removeClass('ui-state-error');
        }

        return (errorMessage=='');
    },

    
    focus: function(){
        if(this.inputs && this.inputs.length>0){
            $(this.inputs[0]).focus();   
            return true;
        }else{
            return false;
        }
    },

    //
    //
    //
    _addReadOnlyContent: function(value, idx) {

        var disp_value ='';

        var $inputdiv = $( "<div>" ).addClass('input-div truncate')
                .css({'font-weight':'bold', 'max-width':'400px'})
                .insertBefore(this.input_prompt);

        if($.isArray(value)){

            disp_value = value[1]; //record title, relation description, filename, human readable date and geo

        }else if(this.detailType=="enum" || this.detailType=="relationtype"){

            disp_value = window.hWin.HEURIST4.ui.getTermValue(value, true);

            if(window.hWin.HEURIST4.util.isempty(value)) {
                disp_value = 'term missed. id '+termID
            }
        } else if(this.detailType=='file'){

            this._findAndAssignTitle($inputdiv, value);

        } else if(this.detailType=="resource"){

            disp_value = "....resource "+value;

            this._findAndAssignTitle($inputdiv, value);

        } else if(this.detailType=="relmarker"){  //combination of enum and resource

            disp_value = "@todo relation "+value;

            //@todo NEW datatypes
        } else if(this.detailType=="geo"){

            /*if(detailType=="query")
            if(detailType=="color")
            if(detailType=="bool")
            if(detailType=="password")*/

            disp_value = "@todo geo "+value;


        }else{
            disp_value = value;
        }

        if(this.detailType=="blocktext"){
            this.input_cell.css({'padding-top':'0.4em'});
        }

        $inputdiv.html(disp_value);
        
    }


});
