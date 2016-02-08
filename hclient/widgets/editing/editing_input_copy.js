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
        recID: null,  //it is only needed for relation maker @todo remove

        //field desription is either taken from top.HEURIST4.rectypes - H3 compatible structure
        rectypes: null,  // reference to top.HEURIST4.rectypes - defRecStructure
        rectypeID: null, //field description is taken either from rectypes[rectypeID] or from dtFields
        dtID: null,      // field type id (for recDetails) or field name (for other Entities)

        //  it is either from top.HEURIST4.rectype.typedefs.dtFields - retrieved with help rectypeID and dtID
        // object with some mandatory field names
        dtFields: null, 
        
        values: null,
        readonly: false,
        title: '',  //label (overwrite Display label from record structure)
        showclear_button: true,
        show_header: true, //show/hide label  
        detailtype: null,  //overwrite detail type from db (for example freetext instead of memo)
    },

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

        var that = this;

        var detailType = this.options.detailtype ?this.options.detailtype :this.f('dty_Type');
        /*
        if(detailType=="separator"){
        this.element.css('display','block');
        $( "<h3>")
        .addClass('separator')
        .html(this.f('rst_DisplayName'))
        .appendTo( this.element );
        }
        */

        var required = "";
        if(this.options.readonly) {
            required = "readonly";
        }else{
            this.f('rst_RequirementType');
            if (required == 'optional') required = "optional";
            else if (required == 'required') required = "required";
                else if (required == 'recommended') required = "recommended";
                    else required = "";

            var repeatable = (this.f('rst_MaxValues') != 1)? true : false; //saw TODO this really needs to check many exist
            
            //multiplier button
            if(repeatable){

                this.btn_add = $( "<button>")
                .addClass("smallbutton editint-inout-repeat-button")
                //.css('display','table-cell')
                //.css({width:'16px', height:'16px', display:'table-cell'})
                .appendTo( this.element )
                .button({icons:{primary: "ui-icon-circlesmall-plus"}, text:false})
                .css({display:'table-cell'});

                // bind click events
                this._on( this.btn_add, {
                    click: function(){
                        if(this.f('rst_MaxValues')==null || this.options.values.length < this.f('rst_MaxValues')){
                            this.options.values.push('');
                            this._addInput('', this.options.values.length-1);
                        }
                    }
                });

            }else{
                $( "<span>")
                .addClass('editint-inout-repeat-button')
                .css({width:'16px', display:'table-cell'})
                .appendTo( this.element );

            }
        }

        //header
        if(this.options.show_header){
            this.header = $( "<div>")
            .addClass('header '+required)
            .css('width','150px')
            .css('vertical-align', (detailType=="blocktext")?'top':'')
            .html('<label for="input'+(this.options.varid?this.options.varid:'0_'+this.options.dtID)+'">'
                + (top.HEURIST4.util.isempty(this.options.title)?this.f('rst_DisplayName'):this.options.title) +'</label>')
            .appendTo( this.element );
        }


        //input
        this.input_cell = $( "<div>")
        .addClass('input-cell')
        .appendTo( this.element );


        //values are not defined - assign default value    
        if( !top.HEURIST4.util.isArray(this.options.values) ){
            //this.options.values.length==0
            var def_value = this.f('rst_DefaultValue');
            if(top.HEURIST4.util.isempty(def_value)){
                this.options.values = [''];        
            }else if(top.HEURIST4.util.isArray()){
                this.options.values = def_value;
            }else{
                this.options.values = [def_value];
            }
        }
        //recreate input elements and assign given values
        var i;
        for (i=0; i<this.options.values.length; i++){
            if(this.options.readonly){
                this._addLabel(this.options.values[i], i);
            }else{
                this._addInput(this.options.values[i], i);
            }
        }

        this._refresh();
    }, //end _create

    /* private function */
    _refresh: function(){
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
        rst_FilteredJsonTermIDTree      @todo rename to rst_JsonConfig (over dty_JsonConfig)
        rst_TermIDTreeNonSelectableIDs  
        dty_TermIDTreeNonSelectableIDs    
    *  
    *
    * @param fieldname
    */
    f: function(fieldname){
        
        var val = this.options['dtFields'][fieldname]; //try get by name
        if(top.HEURIST4.util.isnull(val) && this.options.rectypes){ //try get by index
            var fi = this.options.rectypes.typedefs.dtFieldNamesToIndex;
            val = this.options['dtFields'][fi[fieldname]];
        }
        if(!val){ //some default values
            if(fieldname=='rst_RequirementType') val = 'optional'
            else if(fieldname=='rst_MaxValues') val = 1
            else if(fieldname=='dty_Type') val = 'freetext'
            else if(fieldname=='rst_DisplayWidth' 
                && (this.f('dty_Type')=='freetext' || this.f('dty_Type')=='blocktext' || this.f('dty_Type')=='resource')) {
                val = 60;
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
    _removeInput: function(idx){
        if(this.options.values.length>1){
            //remove from values array
            this.options.values.splice(idx,1);
            //remove element
            this.inputs[idx].parents('.input-div').remove();
            this.inputs.splice(idx,1);
        }
    },
        
    /**
    * add input according field type
    * 
    * @param value
    * @param idx - index for repetative values
    */
    _addInput: function(value, idx) {

        if(!this.inputs){
            this.inputs = [];
        }

        var that = this;
        var detailType = this.options.detailtype ?this.options.detailtype :this.f('dty_Type');
        var $input = null;
        var inputid = 'input'+(this.options.varid?this.options.varid :idx+'_'+this.options.dtID);
        value = value ?value:'';

        //@todo      var defaultValue = (top.HEURIST4.edit.isAdditionOfNewRecord()?recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_DefaultValue']]:"");


        var $inputdiv = $( "<div>" ).addClass('input-div').appendTo( this.input_cell );

        if(detailType=="blocktext"){

            $input = $( "<textarea>",{id:inputid , rows:3})
            .addClass('text ui-widget-content ui-corner-all')
            .val(value)
            .appendTo( $inputdiv );

        }else if(detailType=="enum"){

            $input = $( "<select>",{id:inputid} )
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .appendTo( $inputdiv );

            this._recreateSelector($input, value);

        }else if(detailType=="boolean"){

            $input = $( "<input>",{id:inputid, type:'checkbox', value:'1'} )
            .addClass('text ui-widget-content ui-corner-all')
            .css('vertical-align','-3px')
            .appendTo( $inputdiv );

            if(!(value==false || value=='0' || value=='n')){
                $input.prop('checked','checked');    
            }

        }else if(detailType=="rectype"){

            $input = $( "<select>",{id:inputid} )
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .appendTo( $inputdiv );

            top.HEURIST4.ui.createRectypeSelect($input.get(0),null, this.f('cst_EmptyValue')); 
            if(value){
                $input.val(value);
            }
            
        }else if(detailType=="user"){ //special case - only groups of current user

            $input = $( "<select>",{id:inputid} )
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .appendTo( $inputdiv );

            top.HEURIST4.ui.createUserGroupsSelect($input.get(0),null,
                    [{key:'',title:top.HR('select user/group...')},
                     {key:top.HAPI4.currentUser['ugr_ID'], title:top.HAPI4.currentUser['ugr_FullName'] }] ); 
            if(value){
                $input.val(value);
            }
            
        }else{
            $input = $( "<input>",{id:inputid})
            .addClass('text ui-widget-content ui-corner-all')
            .val(value)
            .appendTo( $inputdiv );

            if(detailType=="integer" || detailType=="year"){
                
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
                        top.HEURIST4.util.stopEvent(e);
                        e.preventDefault();
                    }    
                    
                });
                /*$input.keyup(function () { 
                    if (this.value != this.value.replace(/[^0-9-]/g, '')) {
                        this.value = this.value.replace(/[^0-9-]/g, '');  //[-+]?\d
                    }
                });*/
            }else
            if(detailType=="float"){
                
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
                        top.HEURIST4.util.stopEvent(e);
                        e.preventDefault();
                    }    
                    
                });
                
            }else
            if(detailType=="date"){
                var $datepicker = $input.datepicker({
                    /*showOn: "button",
                    buttonImage: "ui-icon-calendar",
                    buttonImageOnly: true,*/
                    showButtonPanel: true,
                    changeMonth: true,
                    changeYear: true,
                    dateFormat: "yy-mm-dd"
                });

                var $btn_datepicker = $( "<button>", {title: "Show calendar"})
                .addClass("smallbutton")
                .appendTo( $inputdiv )
                .button({icons:{primary: "ui-icon-calendar"},text:false});

                this._on( $btn_datepicker, { click: function(){$datepicker.datepicker( "show" ); }} );

            }else if(detailType=="resource"){

                /*if(!this.rec_search_dialog){
                    this.rec_search_dialog = this.element.rec_search({
                        isdialog: true,
                        search_domain: 'a'
                    });
                }*/
                var ptrset = that.f('rst_PtrFilteredIDs');
                var ptrcfg = $.parseJSON(ptrset);
                var __show_select_dialog = null;
                var $btn_rec_search_dialog = $( "<button>", {title: "Click to search and select"})
                    .addClass("smallbutton")
                    .appendTo( $inputdiv )
                    .button({icons:{primary: "ui-icon-link"},text:false});
                
                if(ptrcfg==null){  //this is record (link) selector

                    __show_select_dialog = function __show_select_dialog(event){
                        event.preventDefault();
                        
                        var url = top.HAPI4.basePathV4 + 
                                'hclient/framecontent/recordSelect.php?db='+top.HAPI4.database+
                                '&rectype_set='+that.f('rst_PtrFilteredIDs');
                        top.HEURIST4.msg.showDialog(url, {height:600, width:600, 
                                title: top.HR('Select linked record'), 
                                class:'ui-heurist-bg-light',
                                callback: function(recordset){
                                    if( top.HEURIST4.util.isRecordSet(recordset) ){
                                        var record = recordset.getFirstRecord();
                                        var name = recordset.fld(record,'rec_Title');
                                        $input.val(name);
                                        that.options.values[idx] = recordset.fld(record,'rec_ID');
                                    }
                                }           
                        } );
                        /*        
                        that.rec_search_dialog.rec_search("option",{
                            //retuns selected recrod
                            rectype_set: that.f('rst_PtrFilteredIDs'),
                            onselect: function(event, recordset){

                                if(recordset && recordset.length()>0){
                                    var record = recordset.getFirstRecord();
                                    $input.val(recordset.fld(record,'rec_Title'));
                                    that.options.values[idx] = recordset.fld(record,'rec_ID');
                                }

                            }
                        });

                        that.rec_search_dialog.rec_search( "show" );
                        */
                    };


                //$input.css('width', this.options['input_width']?this.options['input_width']:'300px');
                }else{
                    //this is entity selector
                    //detect entity
                    var entityName = ptrcfg.entity;
                    
                    if(!$.isFunction($('body')['showManage'+entityName])){

                            var popup_options = {
                                isdialog: true,
                                select_mode: (ptrcfg.multi==true?'select_multi':'select_single'),
                                //selectbutton_label: '',
                                //page_size: $('#page_size').val(),
                                action_select: false,
                                action_buttons: true,
                                filter_group_selected:null,
                                filter_groups: ptrcfg.filter,
                                onselect:function(event, data){
                                    
                                    if(data && data.selection && top.HEURIST4.util.isRecordSet(data.selection))
                                    {
                                        var recordset = data.selection;
                                        var record = recordset.getFirstRecord();
                                        var name = recordset.fld(record,'rec_Title');
                                        $input.val(name);
                                        that.options.values[idx] = recordset.fld(record,'rec_ID');
                                    }

                                }
                            }
                            
                            
                            var manage_dlg = $('<div>')
                                .uniqueId()
                                .appendTo( $('body') )
                                ['manage'+entityName]( popup_options );
                                
                            __show_select_dialog = function(event){
                                manage_dlg.manageSysUsers( 'popupDialog' );    
                            }
                            
                    }
                }
                
                if(__show_select_dialog!=null){
                    this._on( $btn_rec_search_dialog, { click: __show_select_dialog } );
                    this._on( $input, { keypress: __show_select_dialog, click: __show_select_dialog } );
                }
                
                
            }else if(false && detailType=="relmarker"){

                $input.css('width','auto');

                if(!this.rec_relation_dialog){
                    this.rec_relation_dialog = this.element.rec_relation({

                        rectype_set: this.f('rst_PtrFilteredIDs'), //constraints for target record types
                        reltype_set: this.f('rst_FilteredJsonTermIDTree'), //contraints for relation types
                        reltype_headers: this.f('rst_TermIDTreeNonSelectableIDs') || this.f('dty_TermIDTreeNonSelectableIDs'), //subset of headers for relation type pulldown

                        source_ids: [this.options.recID],
                        isdialog: true
                    });
                }

                var $btn_rec_relation_dialog = $( "<button>", {title: "Click to define relationship"})
                .addClass("smallbutton")
                .appendTo( $inputdiv )
                .button({icons:{primary: "ui-icon-link"},text:false});

                function __show_relation_dialog(event){
                    event.preventDefault();

                    that.rec_relation_dialog.rec_relation("option",{
                        //retuns selected recrod
                        onselect: function(event, lbl){ //@todo - work with new relation record
                            $input.val(lbl);
                            /*
                            if(recordset && recordset.length()>0){
                            var record = recordset.getFirstRecord();
                            $input.val(recordset.fld(record,'rec_Title'));
                            that.options.values[idx] = recordset.fld(record,'rec_ID');
                            }*/

                        }
                    });

                    that.rec_relation_dialog.rec_relation( "show" );
                };

                this._on( $btn_rec_relation_dialog, { click: __show_relation_dialog } );
                this._on( $input, { keypress: __show_relation_dialog, click: __show_relation_dialog } );

            }
            /*else if(detailType=="freetext" && this.options['input_width']){
                $input.css('width', this.options['input_width']);
            }*/

        }

        if (parseFloat( this.f('rst_DisplayWidth') ) > 0 && detailType!='boolean') {    //if the size is greater than zero
            $input.css('width', Math.round(2 + Number(this.f('rst_DisplayWidth'))) + "ex"); //was *4/3
        }


        this.inputs.push($input);

        //name="type:1[bd:138]"

        //clear button
        //var $btn_clear = $( "<div>")
        if(this.options.showclear_button)
        {
        
        var $btn_clear = $('<button>',{
            title: 'Clear entered value'
        })
        .addClass("smallbutton btn_input_clear")
        .css({'vertical-align': (detailType=="blocktext")?'top':'' })
        .appendTo( $inputdiv )
        .button({icons:{primary: "ui-icon-circlesmall-close"},text:false});

        // bind click events
        this._on( $btn_clear, {
            click: function(e){
                if(top,HEURIST4.util.isempty(that.options.values[idx])){
                    that._removeInput(idx);                    
                }else{
                    that.options.values[idx] = '';
                    $input.val('');
                }
            }
        });
        
        }

    },


    //
    // recreate SELECT for enum type
    //
    _recreateSelector: function($input, value){

        $input.empty();

        var detailType = this.options.detailtype ?this.options.detailtype :this.f('dty_Type');

        var allTerms = this.f('rst_JsonConfig');
        if(allTerms) {
            
            if(isNaN(allTerms)){
                
                if (!$.isArray(allTerms) && !top.HEURIST4.util.isempty(allTerms)) {
                    allTerms = allTerms.split(',');   
                }

                
                if(top.HEURIST4.util.isArrayNotEmpty(allTerms)){
                    if(top.HEURIST4.util.isnull(allTerms[0]['key'])){
                        //plain array 
                        var idx, options = [];
                        for (idx=0; idx<allTerms.length; idx++){
                            options.push({key:allTerms[idx], title:allTerms[idx]});
                        }
                        allTerms = options;
                    }
                    //array of key:title objects
                    top.HEURIST4.ui.createSelector($input.get(0), allTerms);
                }
            }else{
                //vocabulary
                top.HEURIST4.ui.createTermSelectExt2($input.get(0), 
                    {datatype:detailType, termIDTree:allTerms, headerTermIDsList:null, 
                    defaultTermID:value, topOptions:true, supressTermCode:true});
            }
        }else{
        
            allTerms = this.f('rst_FilteredJsonTermIDTree');
            
            if(isNaN(allTerms)){
                
                var options = [];
                var idx, opts = allTerms.split(',');
                for (idx=0; idx<opts.length; idx++){
                    options.push({key:opts[idx], title:opts[idx]});
                }
            
                top.HEURIST4.ui.createSelector($input.get(0), options);
            
            }else{
                var headerTerms = this.f('rst_TermIDTreeNonSelectableIDs') || this.f('dty_TermIDTreeNonSelectableIDs');

                top.HEURIST4.ui.createTermSelectExt2($input.get(0), 
                    {datatype:detailType, termIDTree:allTerms, headerTermIDsList:headerTerms, 
                    defaultTermID:value, topOptions:true, supressTermCode:true});
            }
        }
    },

    setValue: function(value, idx, display_value){
        // alert("selected "+value+"  "+display_value);
        if(!idx) idx = 0;
        this.inputs[idx].val( value );
    },

    getValues: function(){

        var detailType = this.options.detailtype ?this.options.detailtype :this.f('dty_Type');
        var idx;
        var ress = [];
        for (idx in this.inputs) {
            if(!(detailType=="resource" || detailType=="relmarker")){
                this.options.values[idx] = this.inputs[idx].val();
            }
            if(this.options.values[idx]){
                ress.push( this.options.values[idx] );
            }
        }
        return ress;
    },

    //
    // returns array of input elements
    //
    getInputs: function(){
        return this.inputs;
    },

    _addLabel: function(value, idx) {

        var detailType = this.options.detailtype ?this.options.detailtype :this.f('dty_Type');
        var disp_value ='';

        if($.isArray(value)){

            disp_value = value[1]; //record title, relation description, filename, human readable date and geo

        }else if(detailType=="enum" || detailType=="relationtype"){

            disp_value = top.HEURIST4.ui.getTermValue(detailType, value, true);

            if(top.HEURIST4.util.isempty(value)) {
                disp_value = 'term missed. id '+termID
            }
        } else if(detailType=="file"){

            disp_value = "@todo file "+value;   

        } else if(detailType=="resource"){

            disp_value = "@todo resource "+value;   

        } else if(detailType=="relmarker"){  //combination of enum and resource

            disp_value = "@todo relation "+value;   

        //@todo NEW datatypes                
        } else if(detailType=="geo"){
            
            /*if(detailType=="query")
            if(detailType=="color")
            if(detailType=="bool")
            if(detailType=="password")*/
            
            disp_value = "@todo geo "+value;   
            

        }else{
            disp_value = value;
        }

        if(detailType=="blocktext"){
            this.input_cell.css({'padding-top':'0.4em'});
        }

        this.input_cell.html(disp_value);

    }  


});
