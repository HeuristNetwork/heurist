/**
* searchBuilderItem.js - element in filter builder - to define query element
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @designer    Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.searchBuilderItem", {

    //{ conjunction: [ {predicate} , {predicate}, .... ] }
    //
    // predicate    token:value
    //
/*    
     ids, id: record ID 
     title: record title 
     url, u: record url 
     notes, n: record notes (`rec_ScratchPad`) 
     added: creation date 
     date, modified:  edition date 
     after, since, before: aliases for added:&gtdate and added&lt;date 

                                  
     addedby: added by specified user (`rec_AddedByUGrpID`) 
     owner,workgroup,wg: owner of record (`rec_OwnerUGrpID`) 
      
     tag, keyword, kwd: tag name (`usrtags`.`tag_Text`) 
     user, usr: bookmarked by user (`usrbookmarks`.`bkm_UGrpID`) 

      
     t,type: record type 
     f,field: field type id 
     linked_to,linkedfrom,related_to,relatedfrom,links: various link predicates 
*/    
    
    // default options
    options: {
        //token:null,  //t, f, linkedXXX
        
        code: null, //hierarchy

        top_rty_ID: 0,
        rty_ID: 0,
        
        dty_ID: 0, //field id or token       
        
        hasFieldSelector: false,
        
        // callback
        onremove: null,
        onchange: null,
        
        onselect_field: null  //callback to select field from treeview
    },

    _current_field_type:null, // type of input field
    _predicate_input_ele:null,     // reference to editing_input
    _predicate_reltype_ele:null,     // reference to relation type selector

    // the widget's constructor
    _create: function() {

        var that = this;
        
        //create elements for predicate
        // 2. field selector for field or links tokens
        // 3. comparison selector or relationtype selector
        // 4. value input
        // 5. OR button
        
        
        // 0. Label (header)
        this.label_token = $( "<div>" )
            .css({"font-size":"smaller",'padding-left':'83px',width:'95%','margin-top':'4px'})
            .appendTo( this.element ); //10px 0 10px 20px,'border-top':'1px solid lightgray' 

        // selector container - for fields and comparison
        this.sel_container = $('<div>')
            .css({'display':'inline-block','vertical-align':'top','padding-top':'3px'})
            .appendTo(this.element);

        // values container - consists of set of inputs (editing_input) and add/remove buttons
        this.values_container = $( '<fieldset>' )
            .css({'display':'inline-block','padding':0}) //,'margin-bottom': '2px'
            .appendTo( this.element );

            
        $('<div class="header_narrow field_header" '
        +'style="min-width:83px;display:inline-block;text-align:right;padding-right: 5px;">'
        +'<label for="opt_rectypes">Criteria</label></div>')
            .appendTo( this.sel_container );
        
        // 2. field selector for field or links tokens
        this.select_fields = $( '<select>' )
            .attr('title', 'Select field' )
            .addClass('text ui-corner-all')
            .css({'margin-left':'2em','min-width':'210px','max-width':'210px'})
            .hide()
            .appendTo( this.sel_container );

        this.select_fields_btn = $('<span role="combobox" class="ui-selectmenu-button ui-button '
                    +'ui-widget ui-selectmenu-button-closed ui-corner-all" '
                    +'style="padding: 0px; font-size: 1.1em; width: 210px; min-width: 210px;">'
                    +'<span class="ui-selectmenu-icon ui-icon ui-icon-triangle-1-s"></span><span class="ui-selectmenu-text">Any field</span></span>')
                .insertAfter(this.select_fields);
        this._on( this.select_fields_btn, { click: function(event){
            if($.isFunction(this.options.onselect_field)){
                window.hWin.HEURIST4.util.stopEvent(event);
                this.options.onselect_field.call(this);
                //this._onSelectField();
            }
        }});


        // 1. Remove icon
        this.remove_token = $( "<span>" )
        .attr('title', 'Remove this search token' )
        .addClass('ui-icon ui-icon-circle-b-close')
        .css({'cursor':'pointer','font-size':'0.8em',visibility:'hidden'})
        .appendTo( this.sel_container );        
        
        this._on( this.remove_token, { click: function(){
            if($.isFunction(this.options.onremove)){
                this.options.onremove.call(this);
            }    
        } });
            
        // 3a  negate  
        this.cb_negate = $( '<label><input type="checkbox">not</label>' )
            .css('font-size','0.8em')
            .hide()
            .appendTo( this.sel_container );

        
        // 3b. comparison selector or relationtype selector
        this.select_comparison = $( '<select>' )
            .attr('title', 'Select compare operator' )
            .addClass('text ui-corner-all')
            .css({'margin-left':'1em','min-width':'99px','max-width':'99px',border:'none'})
            //.hide()
            .appendTo( this.sel_container );

            
        // 4. conjunction selector for multivalues
        //var ele = $('<div>').css('padding-top','2px').hide().appendTo( this.sel_container );
        this.select_conjunction = $( '<select><option value="any">or</option><option value="all">and</option></select>' )
            .attr('title', 'Should field satisfy all criteria or any of them' )
            .addClass('text ui-corner-all')
            .css({'margin':'5px 0px 2px',border:'none'}) //,'margin-right':'-21px'
            .appendTo( this.sel_container )
            .hide();
            
        
        this.select_relationtype = $( '<select>' )
            .attr('title', 'Select relation type' )
            .addClass('text ui-corner-all')
            .css('margin-left','2em')
            .hide()
            .appendTo( this.sel_container );
        

        var that = this;
        this.sel_container.hover(function(){
                   that.remove_token.css({visibility:'visible'});  },
        function(){
                   that.remove_token.css({visibility:'hidden'});
        });
        
        
        
/*        var div_btn =  $('<div>').css({'width':(this.options.level<3)?'12em':'6em'}).appendTo(this.value_container); 

        var btn_delete = $( "<button>", {text:'Delete'})
        .attr('title', 'Delete this token' )
        .css('font-size','0.8em')
        .button({icons: { primary: "ui-icon-closethick" }, text:false}).appendTo(this.div_btn);
*/

        this._refresh();
        
        
        if(!this.options.hasFieldSelector){
            this._defineInputElement();            
        }
        
        
        
    }, //end _create
    
    //
    //
    //
    changeOptions: function(ext_options){

        this.options = $.extend(this.options, ext_options);
        
        this._refresh();
    },
    
    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

        if(!this.options.hasFieldSelector){
            this.remove_token.css({'margin-top':'-55px'});
            //this.label_token.show();    
        }else{
            //this.label_token.hide();    

            var topOptions2 = [
                {key:'anyfield',title:window.hWin.HR('Any field')},
                {key:0,title:'Record header fields', group:1, disabled:true},
                {key:'title',title:'Title (constructed)', depth:1},
                {key:'added',title:'Date added', depth:1},
                {key:'modified',title:'Date modified', depth:1},
                {key:'addedby',title:'Creator (user)', depth:1},
                {key:'url',title:'URL', depth:1},
                {key:'notes',title:'Notes', depth:1},
                {key:'owner',title:'Owner (user or group)', depth:1},
                {key:'access',title:'Visibility', depth:1},
                {key:'tag',title:'Tags', depth:1}
            ];

            var bottomOptions = null;
            //[{key:'latitude',title:window.hWin.HR('geo: Latitude')},
            //                     {key:'longitude',title:window.hWin.HR('geo: Longitude')}]; 

            if(this.options.top_rty_ID>0){
                
                this.select_fields_btn.show();
                this.select_fields.hide();

            }else{

                var allowed_fieldtypes = ['enum','freetext','blocktext',
                    'geo','year','date','integer','float','resource','relmarker'];

                this.select_fields_btn.hide();
                
                //show field selector
                window.hWin.HEURIST4.ui.createRectypeDetailSelect(this.select_fields.get(0), this.options.top_rty_ID, 
                    allowed_fieldtypes, topOptions2, 
                    {show_parent_rt:true, show_latlong:true, bottom_options:bottomOptions, 
                        selectedValue:this.options.dty_ID, //initally selected
                        useIds: true, useHtmlSelect:false});                

                this._on( this.select_fields, { change: this._onSelectField });
                
            }
            this._onSelectField();

        }

        /*        
        if(this.options.token=='f'){

        if(this.options.hasFieldSelector)
        {
        var allowed = Object.keys($Db.baseFieldType);
        allowed.splice(allowed.indexOf("separator"),1);
        allowed.splice(allowed.indexOf("relmarker"),1);
        allowed.splice(allowed.indexOf("resource"),1);
        //allowed.splice(allowed.indexOf("geo"),1);
        allowed.splice(allowed.indexOf("file"),1);

        //list of fields for rtyIDs            
        window.hWin.HEURIST4.ui.createRectypeDetailSelect(this.select_fields.get(0), this.option.rtyIDs, 
        allowed, window.hWin.HR('Any field type'), {selectedValue:this.options.dty_ID});

        this.select_fields.show();
        }else{
        this.select_fields.hide();    
        }


        }else if(this.options.token=='links'){    

        this.select_fields.show();
        }else
        {
        this.select_fields.hide();
        this.select_relationtype.hide();

        this._defineInputElement();

        }
        if(this.options.token=='links'){
        this.select_relationtype.show();    
        this.select_comparison.hide();
        }else{
        this.select_relationtype.hide();    
        this.select_comparison.show();
        }
        */
    },
    //
    // custom, widget-specific, cleanup.
    _destroy: function() {

    },

    _defineInputElement: function( field_type ){

        if(this.options.code){
            var res = $Db.parseHierarchyCode(this.options.code, this.options.top_rty_ID);
            if(res!==false){
                if(this.options.top_rty_ID>0){
                    this.element
                        .find('span.ui-selectmenu-button>span.ui-selectmenu-text')
                        .text(res.harchy[res.harchy.length-1]);
                }

                if(res.harchy.length>2){
                    res.harchy.pop();
                    this.label_token.html(res.harchy.join(''));
                    this.label_token.show();
                }else{
                    this.label_token.hide();
                }
                
            }else{
                this.label_token.text('broken!');
            }
        }else if(this.options.dty_ID>0){
            this.label_token.text($Db.dty(this.options.dty_ID,'dty_Name'));    
        }
        
        var that = this;


        var ed_options = {
            recID: -1,
            //dtID: dtID,
            values: [''],
            readonly: false,
            showclear_button: true,
            show_header: false,
            showedit_button: false,
            suppress_prompts: true,  //supress help, error and required features
            suppress_repeat: 'force_repeat',
            dtFields: null,
            is_faceted_search: true,
            
            change: function(){
                that._manageConjunction();
                
                if($.isFunction(that.options.onchange))
                {
                    that.options.onchange.call(this);
                }
        
            }
            
        };

        if(this.options.dty_ID>0){ //numeric - base field

            //console.log(this.options.rty_ID+'  '+this.options.dty_ID);            
            field_type = $Db.dty(this.options.dty_ID,'dty_Type');
            if(field_type=='blocktext') field_type = 'freetext';

            if(this.options.rty_ID>0){
                ed_options['rectypeID'] = this.options.rty_ID;
            }else{
                
                    var dtFields = {dty_Type:field_type, 
                                    rst_DisplayName: $Db.dty(this.options.dty_ID,'dty_Name'),
                                    rst_FilteredJsonTermIDTree: $Db.dty(this.options.dty_ID,'dty_JsonTermIDTree'),
                                    rst_PtrFilteredIDs: $Db.dty(this.options.dty_ID,'dty_PtrTargetRectypeIDs'),
                                    rst_MaxValues:100};
                    
                    ed_options['dtFields'] = dtFields;
            }
            ed_options['detailtype'] = field_type;
            ed_options['dtID'] = this.options.dty_ID;

        }
        else{        
            //non base fields inputs

            if(!field_type){

                field_type = 'freetext';

                //create input element 
                if(this.options.dty_ID=='added' ||
                    this.options.dty_ID=='modified'){

                    field_type = 'date';

                }else if (this.options.dty_ID=='addedby' ||
                    this.options.dty_ID=='owner' ||
                    this.options.dty_ID=='user'){
                        //user selector
                        field_type = 'user';

                }else  if (this.options.dty_ID=='access' || 
                           this.options.dty_ID=='ids' || 
                           this.options.dty_ID=='tag'){
                        
                        field_type = this.options.dty_ID;
                }
            }
            
            var dtFields = {dty_Type:field_type, rst_DisplayName:'', rst_MaxValues:100};

            if(field_type=="rectype"){
                dtFields['cst_EmptyValue'] = window.hWin.HR('Any record type');
            }

            ed_options['dtFields'] = dtFields;
        }//========

        
        var eqopts = [];
        var already2 = false;

        if(field_type=='geo'){

            eqopts = [{key:'',title:'within'}];

        }else if(field_type=='enum' || field_type=='resource' || field_type=='relmarker' 
                 || field_type=='user' || field_type=='access' || field_type=='ids'){

            eqopts = [{key:'',title:'equals'},
                      {key:'-',title:'not equals'}];   //- negate

        } else if(field_type=='float' || field_type=='integer'){

            //???less than or equals, greater than or equals
            
            eqopts = [
                {key:'=',title:'equals'},
                {key:'-',title:'not equals'},
                {key:'>',title:'greater than'},
                {key:'<',title:'less than'},
                {key:'<>',title:'between'},
                {key:'-<>',title:'not betweeen'}
            ];

        }else if(field_type=='date'){
            //
            eqopts = [
                {key:'',title:'like'},
                {key:'=',title:'equals'},
                {key:'-',title:'not equals'},
                {key:'>',title:'greater than'},
                {key:'<',title:'less than'},
                {key:'<>',title:'between'}
            ];
            
        }else if(field_type=='tag'){

            eqopts = eqopts.concat([
                {key:'=',title:'equals'},    //cs
                {key:'',title:'string match'},
                {key:'starts',title:'starts with'},
                {key:'ends',title:'ends with'}
                ]);
            
        }else{

/*        
Text:         String match, All words, Any word, No word, 
         <separator> Whole value, Starts with, Ends with 
        (I do not know what "between" does)
String match = LIKE
All words  = MATCH (field) AGAINST ('+MySQL +YourSQL' IN BOOLEAN MODE);
Any word =  OR   AGAINST ('MySQL YourSQL');
No word = None of the words is present AGAINST ('-MySQL -YourSQL' IN BOOLEAN MODE);
Whole value = EQUAL
    Any value = any value matches (current default for blank value)
    No data = no data recorded (record missing the field)
*/        
            if(this.options.dty_ID>0 || this.options.dty_ID=='title'){
                eqopts = [
                    {key:'',title:'string match'}, //case sensetive ==
                    {key:'@++',title:'all words'}, //full text
                    {key:'@',title:'any words'},  //full text
                    {key:'@--',title:'no word'},   //full text
                    {key:'', title:'──────────', disabled:true}];

                if(this.options.dty_ID>0){
                    already2 = true;
                    eqopts.push({key:'any', title:'any value'});
                    eqopts.push({key:'NULL', title:'not defined'});
                }
                
            }else{
                eqopts = [
                    {key:'',title:'string match'}];
            }
            
            
            eqopts = eqopts.concat([
                {key:'=',title:'whole value'},    //cs
                {key:'starts',title:'starts with'},
                {key:'ends',title:'ends with'},
                {key:'<>',title:'between'}
                ]);
        }

        if(this.options.dty_ID>0 && field_type!='relmarker' && !already2){            
            eqopts.push({key:'any', title:'any value'});
            eqopts.push({key:'NULL', title:'not defined'});
        }


        window.hWin.HEURIST4.ui.createSelector(this.select_comparison.get(0), eqopts);

        this._on( this.select_conjunction, { change: function(){
            this._manageConjunction();
            if($.isFunction(this.options.onchange)){
                    this.options.onchange.call(this);
            }
        }});
        
        this._on( this.select_comparison, { change: function(){

            var cval = this.select_comparison.val();
            if(cval=='NULL' || cval=='any' ){
                this._predicate_input_ele.hide();
                this.select_conjunction.hide();
                this.cb_negate.hide();
            }else{
                this._predicate_input_ele.show();
                this._manageConjunction();
                //this.cb_negate.show();
            }
            if(cval=='@' 
                || field_type=='geo' || field_type=='float' || field_type=='integer'){
                this.cb_negate.hide();
            }
            
            if(cval=='<>' || cval=='-<>'){
                this._predicate_input_ele.editing_input('setBetweenMode', true);        
            }else{
                this._predicate_input_ele.editing_input('setBetweenMode', false);        
            }
            
            if($.isFunction(this.options.onchange)){
                    this.options.onchange.call(this);
            }
            
        } });
            
        this._current_field_type = field_type;
        //clear input values
        if(this._predicate_input_ele){
            this.select_conjunction.appendTo(this.sel_container); //back to selcontainer
            this._predicate_input_ele.remove(); this._predicate_input_ele = null;    
        }
        if(this._predicate_reltype_ele){
            this._predicate_reltype_ele.remove(); this._predicate_reltype_ele = null;    
        }
        this.values_container.empty();
        
        if(field_type=='relmarker'){
            // for this type we create two elements 
            // relation type selector and resource record selector
            ed_options['detailtype'] = 'relationtype';
            ed_options['dtID'] = 'r';
            this._predicate_reltype_ele = $("<div>").editing_input(ed_options).appendTo(this.values_container);

            ed_options['detailtype'] = 'resource';
            ed_options['dtID'] = this.options.dty_ID;
            
        } 
        
        //init input elements
        this._predicate_input_ele = $("<div>")
            .editing_input(ed_options).appendTo(this.values_container);
            
        //transfer conjunction to input element
        var ele = this._predicate_input_ele.find('.editint-inout-repeat-button')
                    .css({'margin-left':'22px','min-width':'16px'});
        var ele = ele.parent();
        ele.css('min-width','40px');
        this.select_conjunction.appendTo(ele);
        this.select_conjunction.hide();
            
        this.select_comparison.trigger('change');
    },

    //
    //
    //
    getCodes: function(){
        var codes = this.options.code.split(':');
        codes[codes.length-1] = this.options.dty_ID
        return codes.join(':');
    },
    
    //
    //
    //
    getValues: function(){
        if(this._predicate_input_ele){
            
            var relatype_vals = null;
            var has_relatype_value = false, has_value = false;
            var vals = $(this._predicate_input_ele).editing_input('getValues');
            var isnegate =  this.cb_negate.is(':visible') && 
                            this.cb_negate.find('input').is(':checked');
            var op = this.select_comparison.val();

            if(this._current_field_type=='relmarker'){
                relatype_vals = $(this._predicate_reltype_ele).editing_input('getValues');
                has_relatype_value = (relatype_vals.length>1 ||!window.hWin.HEURIST4.util.isempty(relatype_vals[0]));
            }
            has_value =  (vals.length>1 || !window.hWin.HEURIST4.util.isempty(vals[0]));

            
            if (!(has_relatype_value || has_value) && !(op=='any' || op=='NULL')){
                return null;
            }            

            if(op=='any'){
                    op = '';
                    vals = [''];
            }else if(op=='NULL'){
                    op = '';
                    vals = ['NULL'];
            }else if( (this._current_field_type=='enum' 
                        || this._current_field_type=='ids'
                        || this._current_field_type=='user'
                        || this._current_field_type=='resource') 
                    && vals.length>1 && this.select_conjunction.val()=='any')
            {
                vals = [(isnegate?'-':'')+vals.join(',')];

            }else if (this._current_field_type=='relmarker') {
                
                
                if(has_relatype_value){
                    if(has_value){
                        vals = [{ids:(isnegate?'-':'')+vals.join(',')}];
                    } else{
                        vals = [];
                    }
                    vals.push({r:(isnegate?'-':'')+relatype_vals.join(',')});
                }else{
                    vals = (isnegate?'-':'')+vals.join(',');    
                }
                
                return {related_to:vals};
                
            }else {
                
                if(op=='starts'){
                    op = '';
                    $.each(vals,function(i,val){vals[i]=vals[i]+'%'});
                }else if(op=='ends'){
                    op = '';
                    $.each(vals,function(i,val){vals[i]='%'+vals[i]});
                }else if (op=='<>'){
                    op = '';
                }else if (op=='-<>'){
                    op = '-';
                }
                
                //if(isnegate){
                //    op = '-'+op;
                //}
                
                if(op!=''){
                    $.each(vals,function(i,val){vals[i]=op+vals[i]});        
                }
            }

            var key;
            
            if (this._current_field_type=='geo') {
                key = 'geo';    
            }else 
            if(this.options.dty_ID>0){
                key = 'f:'+this.options.dty_ID; 
            }else 
            if(this.options.dty_ID=='anyfield' || this.options.dty_ID==''){
                key = 'f';
            }else {
                key = this.options.dty_ID;
            }
            
            var res = {};
            
            if(vals.length==1){
                res = {};
                res[key] = vals[0];     
            }else{
                var conj = this.select_conjunction.val();
                if(key=='tag'){
                    var p = {}; 
                    p[conj] = vals;
                    res[key] = p;
                }else{
                    res[conj] = [];
                    $.each(vals,function(i,val){ 
                        var p = {}; 
                        p[key] = val;
                        res[conj].push(p); 
                    });        
                }
                
            }
          
            return res;  
        }else{
            return null;
        }
    },
    
    //
    //
    //    
    _onSelectField:function(){

        if(!(this.options.top_rty_ID>0)){        
            this.options.dty_ID = this.select_fields.val();
        }
            
        this._defineInputElement();
        
    },
    

    _manageConjunction: function()
    {                
        this.select_conjunction.parent().find('.conj').remove(); //previous
        var ft = this._current_field_type;

        var vals = !this._predicate_input_ele?0:this._predicate_input_ele.editing_input('getValues');
        
        if(ft=='user' ||  ft=='ids' || vals.length<2){
            if(ft=='user' ||  ft=='ids'){
                this.select_conjunction.val('any');    
            }
            this.select_conjunction.hide();
        }else{
            this.select_conjunction.show();    

            //add or/and
            if(vals.length>2){
                var eles = this._predicate_input_ele.find('.input-cell > .input-div');

                var mh = $(eles[0]).height();

//console.log('changes '+vals.length+'  '+cnt+'  h='+mh);
                var cnt = eles.length-2;
                eles = [];
                while(cnt--) eles.push('<div class="conj" style="line-height:'+(mh+1)+'px;padding:0px 4px 2px">'
                    +(this.select_conjunction.val()=='any'?'or':'and')
                    +'</div>');

                $(eles.join('')).appendTo(this.select_conjunction.parent());
            }

        }
    }                
        

});
