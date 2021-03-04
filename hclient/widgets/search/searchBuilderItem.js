/**
* queryBuilderItem.js - element in query builder - to define query element
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
        token:null,  //t, f, linkedXXX
        
        code: null, //hierarchy

        top_rty_ID: 0,
        rty_ID: 0,
        dty_ID: 0,        
        
        rtyIDs:null, //array of record type IDs - to reduce fields for "F" and "LINKS"
        
        hasTokenSelector: false,
        hasFieldSelector: false,
        
        // callback
        onremove: null,
        onchange: null
    },

    _current_field_type:null, // type of input field
    _predicate_input_ele:null,     // reference to editing_input

    // the widget's constructor
    _create: function() {

        var that = this;
        
        //create elements for predicate
        // 1. token selector (can be hidden)
        // 2. field selector for field or links tokens
        // 3. comparison selector or relationtype selector
        // 4. value input
        // 5. OR button
        
        
        // 0. Label (header)
        this.label_token = $( "<div>" )
            .css({"font-size":"smaller",padding:'10px 0 10px 20px',width:'95%','margin-top':'4px','border-top':'1px solid lightgray'})
            .appendTo( this.element );;

        // selector container - for fields and comparison
        this.sel_container = $('<div>')
            .css({'display':'inline-block','vertical-align':'top','padding-top':'3px'})
            .appendTo(this.element);
            
        // 0a. Remove icon
        this.remove_token = $( "<span>" )
        .attr('title', 'Remove this search token' )
        .addClass('ui-icon ui-icon-circle-b-close')
        .css({'cursor':'pointer','margin-top':'-55px'})
        .appendTo( this.sel_container );        

        this._on( this.remove_token, { click: function(){
            if($.isFunction(this.options.onremove)){
                this.options.onremove.call(this, this.options.code);
            }    
        } });
            
            
        // values container - consists of set of inputs (editing_input) and add/remove buttons
        this.values_container = $( '<fieldset>' )
            .css({'display':'inline-block','padding':0}) //,'margin-bottom': '2px'
            .appendTo( this.element );
        
        // 1. token selector (can be hidden)
        
        this.select_token = $( "<select>" )
            .attr('title', 'Select what kind of object are you going to search' )
            .addClass('text ui-corner-all')
            .appendTo( this.sel_container );

        if(this.options.hasTokenSelector){
            window.hWin.HEURIST4.ui.createSelector(this.select_token.get(0), 
                [{key:'t',title:'Entity(Record) Type'},  //t,type
                    {key:'f',title:'Field'},                //f,field   
                    {key:'links',title:'Link/relation'},    //linked_to,linkedfrom,related_to,relatedfrom,links

                    {key:'',title:'-'},
                    {key:'title',title:'Title'},
                    {key:'ids',title:'ID'},
                    {key:'url',title:'URL'},
                    {key:'notes',title:'Notes'},

                    {key:'added',title:'Added'},
                    {key:'date',title:'Modified'},  //after, since, before
                    {key:'addedby',title:'Added by user'},  
                    {key:'owner',title:'Owner'},  //owner,workgroup,wg

                    {key:'',title:'-'},
                    {key:'tag',title:'Tag(Keyword)'},         //tag, keyword, kwd
                    {key:'user',title:'Bookmarked by user'}  //user, usr
            ]);

            this._on( this.select_token, { change: this._onSelectToken });
        }

        // 2. field selector for field or links tokens
        this.select_fields = $( '<select>' )
            .attr('title', 'Select field' )
            .addClass('text ui-corner-all')
            .css('margin-left','2em')
            .hide()
            .appendTo( this.sel_container );

        this._on( this.select_fields, { change: this._onSelectField });

        // 3a  negate  
        this.cb_negate = $( '<label><input type="checkbox">not</label>' )
            //.css('margin-left','2em')
            .hide()
            .appendTo( this.sel_container );

        
        // 3b. comparison selector or relationtype selector
        this.select_comparison = $( '<select>' )
            .attr('title', 'Select compare operator' )
            .addClass('text ui-corner-all')
            .css('margin-left','0.5em')
            .hide()
            .appendTo( this.sel_container );

            
        // 4. conjunction selector for multivalues
        var ele = $('<div>').css('padding-top','2px').hide().appendTo( this.sel_container );
        this.select_conjunction = $( "<select><option>any</option><option>all</option></select>" )
            .attr('title', 'Should field satisfy all criteria or any of them' )
            .addClass('text ui-corner-all')
            .css('float','right')
            //.hide()
            .appendTo( ele );


        
        this.select_relationtype = $( '<select>' )
            .attr('title', 'Select relation type' )
            .addClass('text ui-corner-all')
            .css('margin-left','2em')
            .hide()
            .appendTo( this.sel_container );
        

/*        var div_btn =  $('<div>').css({'width':(this.options.level<3)?'12em':'6em'}).appendTo(this.value_container); 

        var btn_delete = $( "<button>", {text:'Delete'})
        .attr('title', 'Delete this token' )
        .css('font-size','0.8em')
        .button({icons: { primary: "ui-icon-closethick" }, text:false}).appendTo(this.div_btn);
*/

        this._refresh();
        
        if(this.options.dty_ID>0){
            this._defineInputElement();            
        }
        
        
    }, //end _create
    
    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){
        
        if(!this.options.hasTokenSelector){
            this.select_token.hide();    
            this.label_token.show();    
        }else{
            this.select_token.show();    
            this.label_token.hide();    
        }

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

    },
    //
    // custom, widget-specific, cleanup.
    _destroy: function() {

    },

    _defineInputElement: function( field_type ){

        if(this.options.code){
            var res = $Db.parseHierarchyCode(this.options.code, this.options.top_rty_ID);
            if(res!==false){
                this.label_token.html(res.harchy.join(''));
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
                var vals = this.getValues();
                if(vals.length<2){
                    that.select_conjunction.parent().hide();    
                }else{
                    that.select_conjunction.parent().show();    
                }
                
            }
            
        };

        if(this.options.dty_ID>0){

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
            ed_options['detailtype'] = field_type
            ed_options['dtID'] = this.options.dty_ID;


        }else{        
            //non base fields inputs

            if(!field_type){

                field_type = 'freetext';

                //create input element 
                if(this.options.token=='added' ||
                    this.options.token=='modified'){

                    field_type = 'date';

                }else if (this.options.token=='addedby' ||
                    this.options.token=='owner' ||
                    this.options.token=='user'){
                        //user selector
                        field_type = 'user';

                }else  if (this.options.token=='tag'){
                        // tag selector 
                        // field_type = 'keyword';
                }else  if (this.options.token=='t'){
                        //record type selector 
                        field_type = 'rectype';
                }
            }
            
            var dtFields = {dty_Type:field_type, rst_DisplayName:'', rst_MaxValues:100};

            if(field_type=="rectype"){
                dtFields['cst_EmptyValue'] = window.hWin.HR('Any record type');
            }

            ed_options['dtFields'] = dtFields;
        }//========

        var eqopts = [];

        if(field_type=='geo'){

            eqopts = [{key:'',title:'within'}];

        }else if(field_type=='enum' || field_type=='resource' || field_type=='relmarker' 
                || field_type=='keyword' || field_type=='user'){

            eqopts = [{key:'',title:'is'}];   //- negate

        } else if(field_type=='float' || field_type=='integer'){

            eqopts = [
                {key:'=',title:'equal'},
                {key:'-',title:'not equal'},
                {key:'>',title:'greater than'},
                {key:'<',title:'less than'},
                {key:'<>',title:'between'},
                {key:'-<>',title:'not betweeen'}
            ];

        }else if(field_type=='date'){
            //
            eqopts = [
                {key:'',title:'like'},
                {key:'=',title:'equal'},
                {key:'>',title:'greater than'},
                {key:'<',title:'less than'},
                {key:'<>',title:'between'}
            ];

        }else{

            eqopts = [
                {key:'',title:'contains'},         //case sensetive ==
                {key:'starts',title:'starts with'},
                {key:'ends',title:'ends with'},
                {key:'=',title:'is exact'},    //cs
                {key:'<>',title:'between'},
                {key:'@',title:'full text'}
            ]; // - negate  (except fulltext)

        }

        if(this.options.dty_ID>0){            
            eqopts.push({key:'any', title:'any value'});
            eqopts.push({key:'NULL', title:'not defined'});
        }

        window.hWin.HEURIST4.ui.createSelector(this.select_comparison.get(0), eqopts);
        
        this._on( this.select_comparison, { change: function(){

            if(this.select_comparison.val()=='NULL' || this.select_comparison.val()=='any'){
                this._predicate_input_ele.hide();
                this.select_conjunction.hide();
                this.cb_negate.hide();
            }else{
                this._predicate_input_ele.show();
                this.select_conjunction.show();
                
                this.cb_negate.show();
            }
            if(this.select_comparison.val()=='@' 
                || field_type=='geo' || field_type=='float' || field_type=='integer'){
                this.cb_negate.hide();
            }
            
            
        } });
            
        this._current_field_type = field_type;
        //clear input values
        this.values_container.empty();
        this._predicate_input_ele = null;
        
        this._predicate_input_ele = $("<div>")
        .editing_input(ed_options).appendTo(this.values_container);

        this.select_comparison.trigger('change');
    },

    getValues: function(){
        if(this._predicate_input_ele){
            
            var vals = $(this._predicate_input_ele).editing_input('getValues');
            var isnegate =  this.cb_negate.is(':visible') && 
                            this.cb_negate.find('input').is(':checked');
            var op = this.select_comparison.val();
            
            if(vals.length==1 && window.hWin.HEURIST4.util.isempty(vals[0]) && 
                    !(op=='any' || op=='NULL')){
                
                return null;
            }            

            if(op=='any'){
                    op = '';
                    vals = [''];
            }else if(op=='NULL'){
                    op = '';
                    vals = ['NULL'];
            }else if( (this._current_field_type=='enum' 
                        || this._current_field_type=='user'
                        || this._current_field_type=='resource') 
                    && vals.length>1 && this.select_conjunction.val()=='any')
            {
                vals = [(isnegate?'-':'')+vals.join(',')];
            }else {
                
                if(op=='starts'){
                    op = '';
                    $.each(vals,function(i,val){vals[i]=vals[i]+'%'});
                }else if(op=='ends'){
                    op = '';
                    $.each(vals,function(i,val){vals[i]='%'+vals[i]});
                }
                
                if(isnegate){
                    op = '-'+op;
                }
                
                if(op!=''){
                    $.each(vals,function(i,val){vals[i]=op+vals[i]});        
                }
            }

            var key;
            
            if(this.options.dty_ID>0){
                key = 'f:'+this.options.dty_ID; 
            }else if (this._current_field_type=='geo') {
                key = 'geo';    
            }else if(this.options.token=='anyfield' || this.options.token==''){
                key = 'f';
            }else {
                key = this.options.token;
            }
            
            var res = {};
            
            if(vals.length==1){
                res = {};
                res[key] = vals[0];     
            }else{
                var conj = this.select_conjunction.val();
                res[conj] = [];
                $.each(vals,function(i,val){ 
                    var p = {}; 
                    p[key] = val;
                    res[conj].push(p); 
                });        
            }
          
            return res;  
        }else{
            return null;
        }
    },
    
    //
    //
    //
    _isSomethingDefined:function(){
        var i;
        if (this._predicate_input_ele){
            var val = $(this._predicate_input_ele).editing_input('getValues');
            if(!window.hWin.HEURIST4.util.isempty(val)){
                return true;
            }
        }
        return false;
    },
    
    //
    // show, hide elements depends on token type
    //    
    _onSelectToken:function(){
        
        var newtoken = this.select_token.val();
        
        if(this.option.token && newtoken!=_token && this._isSomethingDefined()){
            alert('Something defined! If you change predicate type all previously defined data will be lost.');
        }
        
        this.options.token = newtoken;
        this._refresh();
    },
    
    //
    //
    //    
    _onSelectField:function(){
        
    },


    // Any time the widget is called with no arguments or with only an option hash,
    // the widget is initialized; this includes when the widget is created.
    _init: function() {

    },
    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    /*_setOptions: function( ) {
    this._superApply( arguments );
    },*/

    /*
    _setOption: function( key, value ) {
    this._super( key, value );
    if ( key === "recordtypes" ) {

    window.hWin.HEURIST4.ui.createRectypeSelect(this.select_source_rectype.get(0), value, false, true);
    this._onSelectRectype();
    this._refresh();
    }
    },*/


});
