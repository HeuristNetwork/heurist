/**
/**
* recordAdd.js - two modes - list of all rectypes to add 
*                and based on recordAccess dialog  (isExpanded - true)  
*                to define default new record params and add new record 
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

$.widget( "heurist.recordAdd", $.heurist.recordAccess, {

    // default options
    options: {
        is_h6style: false,
        width: 520,
        height: 800,
        title:  'Add Record', //'Record addition settings'
        
        currentRecordset: {},  //stub
        currentRecType: 0,
        currentRecTags: null,
        scope_types: 'none',
        
        
        isExpanded: false,  //false - show list, true - show preferences dialog
        
        allowExpanded: true,
        get_params_only: false
    },
    
    rectype_list:null,
    _toolbar: null,

    _initControls:function(){

        if(this.options.RecTypeID>0){

            this.options.currentRecType =  this.options.RecTypeID;           
            this.options.currentOwner = this.options.OwnerUGrpID;           
            this.options.currentAccess = this.options.NonOwnerVisibility;           
            this.options.currentRecTags = this.options.RecTags;           
            this.options.currentAccessGroups = this.options.NonOwnerVisibilityGroups;           

        }else
            if(this.options.currentRecType==0){
                //take from current user preferences
                var add_rec_prefs = window.hWin.HAPI4.get_prefs('record-add-defaults');
                if(!$.isArray(add_rec_prefs) || add_rec_prefs.length<4){
                    add_rec_prefs = [0, 0, 'viewable', '']; //rt, owner, access, tags  (default to Everyone)
                }
                if(add_rec_prefs.length<5){ //visibility groups
                    add_rec_prefs.push('');
                }
                if(!this.options.get_params_only){
                    this.options.currentRecType =  add_rec_prefs[0];           
                }
                this.options.currentOwner = add_rec_prefs[1];           
                this.options.currentAccess = add_rec_prefs[2];           
                this.options.currentRecTags = add_rec_prefs[3];           
                this.options.currentAccessGroups = add_rec_prefs[4];           
            }

        var $dlg = this.element.children('fieldset');

        if(this.options.is_h6style){
            //add title 
            $dlg.css({top:'36px',bottom:'2px','overflow-y':'auto',position:'absolute',width:'auto', margin: '0px','font-size':'0.9em'}).hide();

            //titlebar            
            this._innerTitle = $('<div class="ui-heurist-header" style="top:0px;">'+this.options.title+'</div>')
            .insertBefore($dlg); // menu-text
            //'<span class="ui-icon ui-icon-gear" style="cursor:pointer;float:right;margin:0px 6px">Define parameters</span>                

            this.closeBtn = $('<button>').button({icon:'ui-icon-closethick',showLabel:false, label:'Close'}) 
            .css({'position':'absolute', 'right':'4px', 'top':'6px', height:24, width:24})
            .addClass('ui-fade-color')
            .insertBefore($dlg);
            this._on(this.closeBtn, {click:function(){
                this.closeDialog();
            }});


            //toolbar for control buttons                    
            this._toolbar = $('<div><div class="ui-dialog-buttonset" style="text-align:right">'
                +'<button id="btnAddRecord"/>'
                +'<button id="btnAddRecordInNewWin"/>'
                +'<button id="btnSavePreferences"/>'
                +'</div></div>')
            .addClass('ent_footer ui-heurist-header')
            .hide()    
            .css({'height':'36px','padding':'4px 20px 0px'}).insertAfter($dlg);


            this._on(this.element.find('#btnAddRecord').button({label: window.hWin.HR('Add Record').toUpperCase() })
                .addClass('ui-button-action')
                .show(), {click:this.doAction});
            this._on(this.element.find('#btnAddRecordInNewWin').button({icon:'ui-icon-extlink', 
                label:window.hWin.HR('Add Record in New Window'), showLabel:false })
                .css({margin:'0 24px 0 4px'}) //background:'revert', 
                .show(), {click:this.doAction});
            this._on(this.element.find('#btnSavePreferences').button({label: window.hWin.HR('Save Settings').toUpperCase() })
                .show(), {click:this.doAction});


            this.expandBtn = $('<button>').button({icon:'ui-icon-gear',label:'Permission settings'})
            .addClass('ui-heurist-btn-header1')
            .css({position:'absolute',top:'43px',left:6}) 
            .insertBefore($dlg);

            this._on(this.expandBtn, {click: function(e){ 

                this.doExpand( this.rectype_list.is(':visible') );

            }});

            //list of rectypes
            this.rectype_list = $('<ul class="heurist-selectmenu" '
                +' style="position:absolute;top:56px;left:0;right:0;bottom:1px;padding:0px;'
                +'font-size:smaller;overflow-y:auto;list-style-type:none"></ul>')
            .insertBefore($dlg);



            this.doExpand( this.options.isExpanded );

        }

        //add and init record type selector
        $('<div id="div_sel_rectype" style="padding: 0.2em;" class="input">'
            +'<div class="header_narrow" style="padding: 0px 16px 0px 0px;"><label for="sel_recordtype">Type of record to add:</label></div>'
            +'<select id="sel_recordtype" style="width:40ex;max-width:30em"></select>'

            //+'<div id="btnAddRecord" style="font-size:0.9em;display:none;margin:0 30px"></div>'
            //+'<div id="btnAddRecordInNewWin" style="font-size:0.9em;display:none;"></div>'
            +'</div>').prependTo( $dlg ); //<hr style="margin:5px"/>

        $('<div class="heurist-helper3" style="padding:10px 0;display:block">This settings will be applied and remembered when you select a record type from the list</div>')
        .prependTo( $dlg );


        this._fillSelectRecordTypes( this.options.currentRecType );

        if(this.options.get_params_only===true){
            //this.element.find('#btnAddRecord').hide();
            this.element.find('#btnAddRecordInNewWin').hide();
        }
        //function(event){that.doAction(event)} );

        if(this.options.allowExpanded){
            this.element.find('#div_more_options').show();
            this._on(this.element.find('#btn_more_options'),{click:function(){
                this.element.find('.add_record').show();
                this.element.find('#div_more_options').hide();
                this.element.parent().height('auto');

                this._adjustHeight();
            }});

            window.hWin.HEURIST4.ui.showEntityDialog('usrTags', {
                isdialog : false,
                container: $('#div_sel_tags2'),
                select_mode:'select_multi', 
                layout_mode: '<div class="recordList"/>',
                list_mode: 'compact', //special option for tags
                selection_ids: [], //already selected tags
                select_return_mode:'recordset', //ids by default
                onselect:function(event, data){
                    if(data && data.selection){
                        that.options.currentRecTags = data.astext; //data.selection;
                        that._onRecordScopeChange();
                    }
                }
            });


            var that = this;
            //
            //$(window.hWin.document).on(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
                function(data) { 
                    if(!data || data.type=='rtg' || data.type=='rty'){
                        that._fillSelectRecordTypes(that.options.currentRecType);    
                    }
            });

        }

        return this._super();
    },

    
    //
    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        //$(window.hWin.document).off(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
        window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);        
        return this._super();
    },
    
    _adjustHeight:function(){
                var ele = this.element.find('#txt_add_link');
                var t1 = ele.offset().top;
                var t2 = this.element.find('.ent_footer').offset().top;
                var h = Math.max(t2-t1-40,40);
                ele.height(h);                
    },
    
    //
    //
    //    
    doExpand: function(is_expand){

        if(!this._toolbar) return;
        
        var $dlg = this.element.children('fieldset');
        //var $icon = this.element.find('.ui-heurist-header > span.ui-icon');
        
        if(is_expand){ //show preferences dialog
            
            this._toolbar.show();
            this.rectype_list.hide();
            this.expandBtn.hide();
            this.closeBtn.show();
            $dlg.css('bottom','40px').show(); //space to show button toolbar
            this.element.parent().width(500).height(450);
            //$icon.css('float','left').removeClass('ui-icon-gear').addClass('ui-icon-carat-2-w');
            this._innerTitle.text('Record addition settings');

            this.element.find('#div_more_options').show();
            this.element.find('.add_record').hide()
        
        }else{
             //show record type list
            
            this._toolbar.hide();
            this.rectype_list.show();
            this.expandBtn.show();
            this.closeBtn.hide();
            $dlg.css('bottom','2px').hide();
            this.element.parent().width(200).height('auto');
            //$icon.css('float','right').removeClass('ui-icon-carat-2-w').addClass('ui-icon-gear');
            this._innerTitle.text('Add Record');
        }    
                
        
    },

    //    
    //
    //
    _getActionButtons: function(){
        var res = this._super();
        res[1].text = window.hWin.HR(this.options.get_params_only?'Get Parameters':'Add Record');
        return res;
    },    

    //
    // extended
    //
    getSelectedParameters: function( showWarning ){
        
        var rtSelect = this.element.find('#sel_recordtype');
        if(rtSelect.val()>0){
            if(this._super( showWarning )){
                this.options.currentRecType = rtSelect.val();
                return true;
            }
        }else if ( showWarning ) {
            window.hWin.HEURIST4.msg.showMsgFlash('Select record type for record to be added');            
        }
        return false;
    },
    
    //
    //
    //
    doAction: function(){
        
        if (!this.getSelectedParameters(true))  return;
        
        var new_record_params = {
                'RecTypeID': this.options.currentRecType,
                'OwnerUGrpID': this.options.currentOwner,
                'NonOwnerVisibility': this.options.currentAccess,
                'NonOwnerVisibilityGroups':this.options.currentAccessGroups,
        };
                
        if(this.options.get_params_only==true){
            //return values as context
            new_record_params.RecTags = this.options.currentRecTags;
            this._context_on_close =  new_record_params;
        }else{
            
            var add_rec_prefs = [this.options.currentRecType, this.options.currentOwner, this.options.currentAccess, 
                        this.options.currentRecTags, this.options.currentAccessGroups];    

            window.hWin.HAPI4.save_pref('record-add-defaults', add_rec_prefs);        
            
            window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, 
                    {origin:'recordAdd', preferences:add_rec_prefs});
            
            var action = null;
            if(event){
                var ele = $(event.target);
                
                if(ele.is('button')){
                    action = ele.attr('id');
                }else{
                    action = ele.parent('button').attr('id');
                }
            }
                
            if(action=='btnSavePreferences'){
                
            }else if(action=='btnAddRecordInNewWin'){
                var url = this._onRecordScopeChange();
//                $('#txt_add_link').val();
               window.open(url, '_blank');
            }else{
                window.hWin.HEURIST4.ui.openRecordEdit(-1, null, {new_record_params:new_record_params});    
            }
        }
               
        this.closeDialog(); 
    },

    //
    // record type selector for change record type action
    // 
    _fillSelectRecordTypes: function( value ) {
        var rtSelect = this.element.find('#sel_recordtype');
        rtSelect.empty();
        
        var ele = window.hWin.HEURIST4.ui.createRectypeSelect( rtSelect.get(0), null, window.hWin.HR('select record type'), false );
        
        var that = this;
        
        ele.hSelect({change: function(event, data){
            var selval = data.item.value;
            rtSelect.val(selval);
            that._onRecordScopeChange();
        }});
        
        if(value>0){
            $(ele).val(value).hSelect("refresh"); 
        }
        
        if(this.rectype_list){
            
            this.rectype_list.empty();
            
            $.each(rtSelect.find('option'),function(i,item){
                if(i>0){
                    var sdis = $(item).attr('value')>0
                        ?' data-id="'+$(item).attr('value')+'" '
                        :(' class="ui-heurist-title truncate" style="font-size:1.0em;padding:6px;font-weight:bold"'); 
                            //+(i>1?'border-top:1px solid gray;margin-top:6px;':'')+'"');  // ui-state-disabled
                        
                    $('<li'+sdis
                        +'>'+$(item).text()+'</li>')
                        .appendTo(that.rectype_list);
                    
                }
            });
            
            this.rectype_list.find('li[data-id]').css({padding: '4px 4px 2px 20px', 
                cursor: 'pointer', border: 'none'        
            }).addClass('truncate');
    
            /*
            $.each(ele.hSelect('menuWidget').find('li'),function(i,item){
                if(i>0){
                    item = $(item).clone();
                    if(!item.hasClass('ui-state-disabled')){
                        item.css({cursor:'pointer'});  
                        //item.find('div').css({'border':'none !important'});  
                    } 
                    item.appendTo(that.rectype_list);
                }
            });
            */
            
//.not('.ui-state-disabled')
            this._on(this.rectype_list.find('li[data-id]'), {
              mouseover: function(e){ $(e.target).addClass('ui-state-active'); },
              mouseout: function(e){ $(e.target).removeClass('ui-state-active'); },
              click: function(e){ 
                  var rty_ID = $(e.target).attr('data-id'); 
                  
                  //$(e.target).text(); //send to parent

                  var prefs = window.hWin.HAPI4.get_prefs('record-add-defaults');
                  if(!$.isArray(prefs) || prefs.length<4){
                        prefs = [rty_ID, 0, 'viewable', '']; //default to everyone
                  }else{
                        prefs[0] = rty_ID; 
                  }
                  if(!this.options.get_params_only){
                      window.hWin.HAPI4.save_pref('record-add-defaults', prefs);
                      window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, {origin:'recordAdd'});
                      
                      window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                                        {new_record_params:{RecTypeID:rty_ID}});
                  }
                  this.closeDialog();
              }
            } );            

            //this.rectype_list.html(ele.hSelect('menuWidget').clone());
        }
        /*
        $.each(rtSelect.find('option'),function(i, item){
            item = $(item);
            $('<li data-id="'+item.attr('entity-id')+'" style="font-size:smaller;padding:6px">'
                +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                    + '" class="rt-icon" style="vertical-align:middle;background-image: url(&quot;'+item.attr('icon-url')+ '&quot;);"/>'
                //+'<img src="'+item.attr('icon-url')+'"/>'
                +'<span class="menu-text">'+item.text()+'</span>'
                +'<span style="float:right;">'+item.attr('rt-count')+'</span>'
               +'</li>').appendTo(cont);    
        });
        */
        
        return ele;
    },
    
    
    //
    // overwritten
    //
    _onRecordScopeChange: function () 
    {
        var isdisabled = !this.getSelectedParameters( false );
        
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction'), isdisabled );
        window.hWin.HEURIST4.util.setDisabled( this.element.find('#btnAddRecordInNewWin'), isdisabled);
        window.hWin.HEURIST4.util.setDisabled( this.element.find('#btnAddRecord'), isdisabled);
        
        var url = '';
        
        if(!isdisabled){
            
            url = window.hWin.HAPI4.baseURL+'hclient/framecontent/recordEdit.php?db='+window.hWin.HAPI4.database
            +'&rec_rectype=' + this.options.currentRecType
            +'&rec_owner='+this.options.currentOwner
            +'&rec_visibility='+this.options.currentAccess;
            
            if( !window.hWin.HEURIST4.util.isempty( this.options.currentAccessGroups )){
                url = url + '&visgroups='+this.options.currentAccessGroups;    
            }
            
            if( !window.hWin.HEURIST4.util.isempty( this.options.currentRecTags)){
                if($.isArray(this.options.currentRecTags) && this.options.currentRecTags.length>0){
                    this.options.currentRecTags = this.options.currentRecTags.join(',');
                }
                //encodeuricomponent
                url = url + '&tag='+this.options.currentRecTags;    
            }
            
        }
        $('#txt_add_link').val(url);
        
        return url;
        
    }
    
  
});
