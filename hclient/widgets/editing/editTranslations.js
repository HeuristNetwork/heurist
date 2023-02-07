/**
* Widget to define translations 
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

$.widget( "heurist.editTranslations", {

    // default options
    options: {
        is_dialog: true, //show in dialog or embedded
        
        fieldtype: 'freetext', //or blocktext

        values: [''],  //array of values 
        
        onclose: null
        
    },
    
    _as_dialog:null, //reference to itself as dialog (see options.is_dialog)
    
    _was_changed: false,

    // the constructor
    _init: function() {

        var that = this;

        
        this._container = $('<div class="ent_content_full" style="top:0;padding:10px"/>')
                    .appendTo( $('<div class="ent_wrapper">').appendTo(this.element) );

        if(this.options.is_dialog){
            
            var $dlg;
            
            var arrButtons = {};
            arrButtons[window.hWin.HR('Apply')] = function() {
                that._onCloseDialog();
                if(that._as_dialog && that._as_dialog.dialog('instance')) that._as_dialog.dialog( "close" );
            };
            arrButtons[window.hWin.HR('Cancel')] = function() {
                if(that._as_dialog && that._as_dialog.dialog('instance')) that._as_dialog.dialog( "close" );
            };

            $dlg = this.element.dialog({
                autoOpen: true,
                height: 240,
                width: 840,
                modal: true,
                title: 'Define Translations',
                buttons: arrButtons,
                resizeStop: function( event, ui ) {
                    var pele = that.element.parents('div[role="dialog"]');
                    that.element.css({overflow: 'none !important', width:pele.width()-24 });
                },
                close:function(){
                    that._as_dialog.remove();    
                }
            });
            
            $dlg.parent().addClass('ui-heurist-populate');
            
            this._as_dialog = $dlg; 
        }
        
        //create edit form with list of inputs
        this._createForm();        
        
    }, //end _create
    
    //
    //
    //
    _createForm: function(){
        
        //create - define new language button 
        this._btn_add = $('<div class="btn_lang_add" style="margin:10px 85px;color:#6A7C99;cursor:pointer;font-size:0.9em">'
        +'<span class="ui-icon ui-icon-plus" style="font-size:1em;margin-left: 95px;"></span> Add language'
        +'</div>')
        .appendTo( this._container );
        
        
        for (var i=0; i<this.options.values.length; i++){
            
            this._createEntry( this.options.values[i] , true);

        }//for
        
        this._adjustDimension();
        
        this._on(this._btn_add, {click:function(){
            //take defaul value
            var ele = this.element.find('[data-def=1]');
            
            this._createEntry(ele.val(), false);
            this._adjustDimension();
        }});
    },        
    
    //
    ///
    //
    _adjustDimension: function(){
        
        
        var ch = this.element.find('div.ent_content_full')[0].scrollHeight;
 
        if(ch<150) ch = 150;

        var topPos = 0;
        if(this.options.is_dialog){        
            var pos = this._as_dialog.dialog('option', 'position');
            if(pos && pos.of && !(pos.of instanceof Window)){
                var offset = $(pos.of).offset();
                topPos = (offset?offset.top:0)+40;
            }
            
            ch = ch + 80;

            //var dh =  this._dialog.dialog('option', 'height');

            var ht = Math.min(ch, window.innerHeight-topPos);

            //console.log(ch+'  new '+ht);                   
            
            this._as_dialog.dialog('option', 'height', ht);    
        }else{
            topPos = this.element.parent().offset().top + 10;
            
            if(ch > window.innerHeight-topPos){
                ch = window.innerHeight-topPos;
            }
            
            this.element.parent().height(ch);
        }
                
    },
    

    //
    //
    //
    _createEntry: function(value, check_default){

            var sel_container, values_container, input_ele, that = this;
            
            var cont = $('<div>').css({margin:'5px'}).insertBefore(this._btn_add);
            
            // selector container - to select language
            this.sel_container = $('<div>')
                .css({'display':'inline-block','vertical-align':'top','padding-top':'3px','min-width':'100px'})
                .appendTo(cont);
            // values container
            values_container = $( '<div>' )
                .css({'display':'inline-block','padding':'3px'}) //,'margin-bottom': '2px'
                .appendTo( cont );
                
            
            var _is_default = false;    
            var lang = '';;
            
            if(check_default){
                
                if(!window.hWin.HEURIST4.util.isempty(value) && value.substr(2,1)==':'){
                    lang = value.substr(0,2);
                    value = value.substr(3).trim();
                }
                
                var _is_default = window.hWin.HEURIST4.util.isempty(lang);
            }
//console.log('>>>'+value);            
            
            
            if(this.options.fieldtype=='blocktext')
            {
                input_ele = $( "<textarea>",{rows:4}) //min number of lines
                        .css({'overflow-x':'hidden'})
                        .keydown(function(e){
                            if (e.keyCode == 65 && e.ctrlKey) {
                                e.target.select()
                            }    
                        });
 
            }else{
                input_ele = $( "<input>");
                
            }
            
            input_ele.uniqueId()
                        .addClass('text ui-widget-content ui-corner-all')
                        .css({width:'680px'})
                        .val(value)
                        .keyup(function(){that._was_changed=true;})
                        .change(function(){that._was_changed=true;})
                        .appendTo( values_container );
                        
            if(_is_default){
                input_ele.attr('data-def',1);
            }
            
            var inpt_id = input_ele.attr('id');

            if(_is_default){
                //label for first value
                $('<div class="header_narrow field_header" '
                +'style="min-width:90px;display:inline-block;text-align:right;padding-right: 9px;">'
                +'<label>Default language</label></div>')
                    .appendTo( this.sel_container );
            }else{
                var ind = -1;

                //find last seleted
                if(!check_default){
                    var ele = this.element.find('select:last');
                    if(ele.length>0){
                        ind = ele[0].selectedIndex;
                        if(ind<0) ind = 0;
                    }
                }
                
                // 2. field selector for field or links tokens
                var sel = $( '<select>' )
                    .attr('title', 'Select language' )
                    .attr('data-input-id', inpt_id)
                    .addClass('text ui-corner-all')
                    .css({'margin-left':'2em','min-width':'40px','max-width':'40px'})
                    .hide()
                    .appendTo( this.sel_container );
                    
                    
                window.hWin.HEURIST4.ui.createLanguageSelect(sel, null, lang, false);
                
                if(ind>=0){
                    sel[0].selectedIndex = ind + 1;
                    sel.hSelect('refresh');
                }
                
            }


            
    },
    
    //
    //
    //
    _onCloseDialog: function(){

        if(this._was_changed){
            
            var that = this;
            
            var eles = this.element.find('textarea,input');
            
            var res = [];
            
            eles.each(function(i, item){
               item = $(item); 
               var val = item.val().trim();
               if(!window.hWin.HEURIST4.util.isempty(val)){
                   //find language
                   var sel = that.element.find('select[data-input-id="'+item.attr('id')+'"]');
                   if(sel.length>0 && sel.val() && !item.attr('data-def')){
                       res.push(sel.val()+':'+val);
                   }else{
                       res.push(val);
                   }
               }
                
            });
        
            if($.isFunction(this.options.onclose)){
                this.options.onclose.call(this, res);        
            }
                
        }
    },


    /* private function */
    _refresh: function(){
    },
    
    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
    },


});
