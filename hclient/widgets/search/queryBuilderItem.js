/**
* IN PROGRESS 
* 
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

$.widget( "heurist.queryBuilderItem", {

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
        token:null,
        
        rtyIDs:null, //array of record type IDs - to reduce fields for "F" and "LINKS"
        
        // callback
        onremove: null
    },

    _current_field_type:null, //type of input field
    _predicate_values:[], //list of possible predicate values (OR)
    

    // the widget's constructor
    _create: function() {

        var that = this;
        
        //create elements for predicate
        // 1. token selector (can be hidden)
        // 2. field selector for field or links tokens
        // 3. comparison selector or relationtype selector
        // 4. value input
        // 5. OR button
        
        // selecor container
        this.sel_container = $('<div>').css({'display':'inline-block','vertical-align':'top'}).appendTo(this.element);
        // values container - consists of set of inputs (editing_input) and add/remove buttons
        this.values_container = $( "<fieldset>" ).css({'display':'inline-block','padding':0}).appendTo( this.element );
        
        // 1. token selector (can be hidden)
        
        this.select_token = $( "<select>" )
        .attr('title', 'Select what kind of object are you going to search' )
        .addClass('text ui-corner-all')
        .appendTo( this.sel_container );
        
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

        // 2. field selector for field or links tokens
        this.select_fields = $( "<select>" )
        .attr('title', 'Select field' )
        .addClass('text ui-corner-all')
        .css('margin-left','2em')
        .hide()
        .appendTo( this.sel_container );

        this._on( this.select_fields, { change: this._onSelectField });
        
        // 3. comparison selector or relationtype selector
        this.select_comparison = $( "<select>" )
        .attr('title', 'Select compare operator' )
        .addClass('text ui-corner-all')
        .css('margin-left','2em')
        .hide()
        .appendTo( this.sel_container );
        
        this.select_relationtype = $( "<select>" )
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
    }, //end _create
    
    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

        if(this.options.token=='f'){
            
            var allowed = Object.keys($Db.baseFieldType);
            allowed.splice(allowed.indexOf("separator"),1);
            allowed.splice(allowed.indexOf("relmarker"),1);
            allowed.splice(allowed.indexOf("resource"),1);
            allowed.splice(allowed.indexOf("geo"),1);
            allowed.splice(allowed.indexOf("file"),1);
        
            window.hWin.HEURIST4.ui.createRectypeDetailSelect(this.select_fields.get(0), this.option.rtyIDs, 
                        allowed, window.hWin.HR('Any field type'));
            
            this.select_fields.show();
            
        }else if(this.options.token=='links'){    
            
            this.select_fields.show();
        }else{
            this.select_fields.hide();
            this.select_relationtype.hide();
           
            var dt_type = 'freetext';
            //create input element 
            if(this.options.token=='added' ||
               this.options.token=='date'){

               dt_type = 'date';
                
            }else if (this.options.token=='addedby' ||
               this.options.token=='owner' ||
               this.options.token=='user'){
               //user selector
               dt_type = 'user';

            }else  if (this.options.token=='tag'){
               //tag selector 
               dt_type = 'keyword';
            }else  if (this.options.token=='t'){
               //record type selector 
               dt_type = 'rectype';
            }
            
            this._defineInputElement( dt_type );
            
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

           if(this._current_field_type!=field_type){
                //clear input values
                this.values_container.empty();
                this._predicate_values = [];
           }
            
            
           this._current_field_type = field_type;
        
           dtFields = {dty_Type:field_type, rst_DisplayName:'', rst_MaxValues:100};
           
           if(field_type=="rectype"){
                dtFields['cst_EmptyValue'] = window.hWin.HR('Any record type');
           }

           var ed_options = {
                        recID: -1,
                        //dtID: dtID,
                        //rectypeID: rectypeID,
                        //rectypes: window.hWin.HEURIST4.rectypes,
                        values: '',
                        readonly: false,
                        showclear_button: true,
                        show_header: false,
                        dtFields: dtFields
                        
                };
                
           this._predicate_values.push(
                $("<div>").editing_input(ed_options).appendTo(this.values_container)
           );
        
    },
    
    //
    //
    //
    _isSomethingDefined:function(){
        var i;
        for (i in _predicate_values){
            var val = $(_predicate_values[i]).editing_input('getValues');
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
