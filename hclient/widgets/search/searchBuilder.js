/**
*  Wizard to define advanced search
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

$.widget( "heurist.searchBuilder", {

    // default options
    options: {
        is_dialog: false,
        is_h6style: false,
        svsID: null,
        domain: null, // bookmark|all or usergroup ID
        is_modal: true,
        menu_locked: null,
        onsave: null,
        onClose: null,
        beforeClose: null
    },

    is_advanced: false,
    
    _dialog: null,
    is_edit_continuing: false, //???
    _lock_mouseleave: false,
    _save_in_porgress: false,

    //controls
    select_main_rectype: null,
    select_additional_rectypes: null,
    svs_MultiRtSearch: null,
    //field_selector: null,

    current_tree_rectype_ids:null, //to avoid reload
    
    group_items:{}, //groups - to be implemented
    field_array:[], //defined fields for current groups
    sort_array:[],
    
    select_field_for_id: null,

    // the widget's constructor
    _create: function() {

        var that = this;

        // prevent double click to select text
        //this.element.disableSelection();

        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);

        var that = this;

        this.element.css({overflow: 'hidden !important'}).addClass('ui-heurist-bg-light');

        var ht = $(window).height();
        if(ht>700) ht = 700;

        //this.options.is_h6style = (window.hWin.HAPI4.sysinfo['layout']=='H6Default');

        if(this.options.is_dialog){
        
            this._dialog = this.element.dialog({
                autoOpen: false,
                height: 450,
                width:750,
                modal: this.options.is_modal,

                resizable: true, //!is_h6style,
                draggable: this.options.is_modal, //!this.options.is_h6style,
                //position: this.options.position,
                
                title: window.hWin.HR('Filter builder'),
                resizeStop: function( event, ui ) {//fix bug
                        var pele = that.element.parents('div[role="dialog"]');
                        that.element.css({overflow: 'none !important', 'width':pele.width()-24 });
                },
    //@ remove            
    /*            
                beforeClose: function( event, ui ) {
                    if(event && event.currentTarget){
                        var that_dlg = this;
                        if($( that_dlg ).dialog( 'option', 'modal' )){
                            window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR("Discard changes?"),
                                function(){ $( that_dlg ).dialog( "close" ); });
                            return false;
                        }
                    }
                },
    */            
                buttons: [
                    /*{text:window.hWin.HR('Preview'), id:'btnSave',
                        click: function() {
                            that._doSaveSearch()
                    }},*/
                    {text:window.hWin.HR('Filter'), id:'btnSearch',
                        class:'ui-button-action', 
                        click: function() {
                            that._doSearch()
                    }}
                ],
                show: {
                    effect: 'fade',
                    duration: 500
                }
            });
        
            this.element.parent().addClass('ui-dialog-heurist');
            this.element.parent().find('.ui-dialog-buttonset').css('margin-right','260px');
        }else{
            
            
            
        }
        
        this.element.load(window.hWin.HAPI4.baseURL+"hclient/widgets/search/searchBuilder.html", function(){
        
            that._initControls();
            //that.pnl_Tree    
            //that.pnl_Items
            //that.pnl_Result
        });

        
        //click outside - hide treeview
        this._on(window.hWin.document, { 
            click: function(e){
                if(this.pnl_Tree) this.pnl_Tree.hide();
            }}
        );
        

        this._refresh();

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash,
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
    },

    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    /*
    _setOptions: function( options ) {
    },*/

    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

    },
    //
    // custom, widget-specific, cleanup.
    _destroy: function() {
        
        if(this.select_main_rectype){
            if(this.select_main_rectype.hSelect("instance")!=undefined){
               this.select_main_rectype.hSelect("destroy"); 
            }
            this.select_main_rectype.remove();   
            this.select_main_rectype = null;
            
            this.select_additional_rectypes.remove();   
            this.select_additional_rectypes = null;
        }
        
        
        // remove generated elements
    }
    
    //
    //
    //
    , adjustDimension: function(){
        
        if(this.field_array.length==0){
            this.btnAddFieldItem.click();
        }else{
            this.pnl_Items.find('.field_header:first').css('visibility','visible');
        }
        if(this.field_array.length>1){
            this.pnl_Items.find('.search_conjunction').css('visibility','visible');    
        }else{
            this.pnl_Items.find('.search_conjunction').css('visibility','hidden');    
        }

        if(this.sort_array.length==0){
            this.addSortItem();
        }else{
            this.pnl_Items.find('.sort_header').css('visibility','hidden');
            this.pnl_Items.find('.sort_header:first').css('visibility','visible');
        }
            

        //var ch = this.pnl_Items[0].scrollHeight;
        var ch = this.btnAddSortItem.position().top + 24;   
            
        ch = this.pnl_Rectype.height() + ch + 115; 
        if(ch<450) ch = 450;

        var topPos = 0;
        if(this.options.is_dialog){        
            var pos = this._dialog.dialog('option', 'position');
            if(pos && pos.of && !(pos.of instanceof Window)){
                topPos = $(pos.of).offset().top+40;
            }

            //var dh =  this._dialog.dialog('option', 'height');

            var ht = Math.min(ch, window.innerHeight-topPos);

            //console.log(ch+'  '+dh+'  new '+ht);                   
            
            this._dialog.dialog('option', 'height', ht);    
        }else{
            topPos = this.element.parent().offset().top + 10;
            
            if(ch > window.innerHeight-topPos){
                ch = window.innerHeight-topPos;
            }
            
            this.element.parent().height(ch);
        }
                
    }
    
    //
    //  show either treeview or menuWidget field selector
    //
    ,showFieldSelector: function( ele_id ){
        
        if(!(this.select_main_rectype.val()>0)){
            this.pnl_Tree.hide();
            /*
            //show all fields
            this.pnl_Tree.find('.rty-selected').hide();
            this.pnl_Tree.find('.rty-not-selected').show();
            var iTop = this.pnl_Tree.find('#header_treeview').position().top+45;
            var cont = this.element.find('#field_all_list');
            cont.css('top',iTop);
            
            this.field_selector.hSelect('open');
            
            var menu = this.field_selector.hSelect( "menuWidget" );
            menu.css({position:'absolute', width:'99%', top: 0, bottom:1, 'max-height':2000});                
            */
        }else{
            this.select_field_for_id = ele_id;
            
            this.pnl_Tree.show();
            //var iTop = this.pnl_Tree.find('#header_treeview').position().top+60;
            //this.pnl_Tree.find('#field_treeview').css('top',iTop);
        }

        
    }

    //
    //
    //
    ,show: function( )
    {
        this.current_tree_rectype_ids = null;
        
        this.element.dialog( 'option', 'modal', this.options.is_modal);
        this.element.dialog( 'option', 'draggable', this.options.is_modal);
        
        if(this.options.position!=null){
            this._dialog.dialog( 'option', 'position', this.options.position );   
        }
        this._dialog.dialog('open');                           
        
        if(this.options.is_modal || !this.is_edit_continuing){
            
            if(!(this.options.is_modal && this.options.svsID>0) || !this.is_edit_continuing){
                //this.options.params = {};
            }
            
            this.is_edit_continuing = !this.options.is_modal;
        }
        
        if(this.options.is_h6style){
            this.element.parent().addClass('ui-heurist-explore');
        }
        
        if($.isFunction(this.options.menu_locked)){
            
            this._on(this.element.parent('.ui-dialog'), {
                        mouseover:function(){ this.options.menu_locked.call( this, false, false );},  //just prevent close
                        mouseleave: function(e){ 
                            this.options.menu_locked.call( this, false, true ); //close after delay
                        }}); //that.closeEditDialog();
            
        }else{
            
            this._off(this.element.parent('.ui-dialog'), 'mouseover mouseleave');
        }
        
        //window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, $dlg); 
    }

    //
    //
    //
    , addSortItem: function(){
        
        var rty_ID = this.select_main_rectype.val();
                                                   
        var ele = $('<div>').uniqueId().insertBefore(this.btnAddSortItem);
        this.sort_array.push(ele);
        
        var that = this;

        ele.searchBuilderSort({ rty_ID: rty_ID ,
                        onchange: function(){
                            that._doCompose();
                        },
                        onremove: function(){
                            var id = this.element.attr('id');
                            $.each(that.sort_array,function(k,item){
                                if(item.attr('id')==id){
                                    that.sort_array.splice(k,1);
                                    that.pnl_Items.find('#'+id).remove();    
                                    return false;
                                }
                            });

                            that.adjustDimension();
                            that._doCompose();
                            
                        }
        });
     
        if(this.sort_array.length>1){
            ele.find('.sort_header').css('visibility','hidden');
        }
     
        this.adjustDimension();
        
    } 
    
    //
    // add or change selected field
    //    
    , addFieldItem: function( code, codes, ele_id ){

            if(true){
                
                if(!codes) codes = code.split(':');
        
                var rty_ID = codes[codes.length-2];
                var dty_ID = codes[codes.length-1];
                var top_rty_ID = codes[0];

                if(!(top_rty_ID>0)) top_rty_ID = 0;
                if(!(rty_ID>0)) rty_ID = 0;
                //if(!(dty_ID>0)) dty_ID = 0;
                
                if(ele_id){
                   
                    var ele = this.pnl_Items.find('#'+ele_id)
//console.log('assign '+code);                                            
                    ele.searchBuilderItem('changeOptions',{
                            code: code,
                            top_rty_ID: top_rty_ID, 
                            rty_ID: rty_ID,
                            dty_ID: dty_ID});

                }else{
                    var ele = $('<div>').uniqueId().attr('data-code',code).insertBefore(this.btnAddFieldItem);
                    this.field_array.push(ele);
                
                    var that = this;
                    ele.searchBuilderItem({
                            //token:  dty_ID>0?'f':dty_ID,
                            hasFieldSelector: !this.is_advanced,
                            code: code,
                            top_rty_ID: top_rty_ID, 
                            rty_ID: rty_ID,
                            dty_ID: dty_ID,
                            onremove: function(){
                                var id = this.element.attr('id');
                                $.each(that.field_array,function(k,item){
                                    if(item.attr('id')==id){
                                        that.field_array.splice(k,1);
                                        that.pnl_Items.find('#'+id).remove();    
                                        
                                        return false;
                                    }
                                });

                                that.adjustDimension();
                                that._doCompose();
                            },
                            onchange: function(){
                                that._doCompose();
                            },
                            onselect_field: function(){
                                var id = this.element.attr('id');
                                that.showFieldSelector( id );
                            }
                    });
                    
                    if(this.field_array.length>1){
                        var conjunct = (this.search_conjunction.val()=='any')?'OR':'AND';

                        ele.find('.field_header').text(conjunct).attr('title', 'Change value in dropdown above fields');
                    }
                }
                
                this.adjustDimension();
            }
    }

    , _initControls: function(){
        
            var that = this;

            if(this.select_main_rectype==null){
                
                this.select_main_rectype = window.hWin.HEURIST4.ui.createRectypeSelectNew( this.element.find("#opt_rectypes").get(0),
                {
                    topOptions: [{key:'-1',title:'select record type to search...'},
                                 {key:'',title:'any record type'}],
                    useHtmlSelect: false,
                    showAllRectypes: true
                });

                //additional rectypes                
                this.select_additional_rectypes = this._createInputElement_RecordTypeSelector();
                this.select_additional_rectypes.hide();
                
                this.svs_MultiRtSearch = this.element.find('#svs_MultiRtSearch');
                
                this._on(this.svs_MultiRtSearch, {change:function(event){
                    if(this.select_additional_rectypes.editing_input('instance')){
                        if(this.svs_MultiRtSearch.is(':checked')){
                            this.select_additional_rectypes.show();
                        }else{
                            
                            //reset flag - facet was changed - need to proceed all steps of wizard
                            if(this.select_additional_rectypes.editing_input('getValues')[0]){
                                //this._resetFacets();
                            }
                            this.select_additional_rectypes.editing_input('setValue', '');
                            this.select_additional_rectypes.hide();
                        }
                        //this.adjustTreePanel();
                    }}});
                    
                this.select_main_rectype.hSelect({change: function(event, data){
                    
                    if(that.select_additional_rectypes){
                        //reset
                        that.select_additional_rectypes.editing_input('setValue', '');
                    }
                    
                    if(that.select_main_rectype.val()<0){
                        //AAA that.pnl_Items.hide();
                        that.pnl_CoverAll 
                            .css({ top:that.pnl_Items.css('top'),bottom:that.pnl_Items.css('bottom') })
                            .show();
                    }else{
                        //that.pnl_Items.show();
                        that.pnl_CoverAll.hide();
                        //load list of field types
                        //that.adjustTreePanel();
                        that._initTreeView([that.select_main_rectype.val()]);
                    }
                    
                    that.clearAll();

                }});
                
/*                        
                var topOptions2 = [
                        {key:'anyfield',title:window.hWin.HR('Any field')},
                        {key:0,title:'Generic fields', group:1, disabled:true},
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

                var allowed_fieldtypes = ['enum','freetext','blocktext',
                                'geo','year','date','integer','float','resource','relmarker'];

                this.field_selector = this.element.find('#field_selector');
                
                this.field_selector = window.hWin.HEURIST4.ui.createRectypeDetailSelect(
                        this.field_selector[0], 
                            null, allowed_fieldtypes, topOptions2, 
                            {show_parent_rt:true, show_latlong:true, bottom_options:bottomOptions, 
                                useIds: true, useHtmlSelect:false});                
                this.field_selector.hide();                
                
                var menu = this.field_selector.hSelect( "menuWidget" );
                menu.show().appendTo( this.element.find('#field_all_list' ) );                        

                //menu.css({'width':'90%','max-height':null,height:'100%'});                
                this._on(menu,{click:function(event){
                    
                    if ($(event.target).hasClass('ui-state-disabled')) return;
                    
                    var dty_ID = that.field_selector.val();
                    
                    var rty_ID = that.select_main_rectype.val();
                        
                    that.addFieldItem( 'any:'+dty_ID, [rty_ID , dty_ID] );

                }});
*/
            
                this.pnl_Rectype  = this.element.find('#pnl_Rectype');
                this.pnl_Tree  = this.element.find('#pnl_Tree');
                this.pnl_Items = this.element.find('#pnl_Items');
                this.pnl_CoverAll = this.element.find('#pnl_CoverAll');
                
                this.pnl_Result = this.element.find('#pnl_Result');
                this.btnAddFieldItem = this.pnl_Items.find('.search_field_add');

                this._on(this.btnAddFieldItem, {click:function(event){
                    
                    var rty_ID = that.select_main_rectype.val();
                    that.addFieldItem( 'any:anyfield', [rty_ID , 'anyfield'] );
                }});
                
                this._on(this.pnl_Tree, {click:function(event){
                        event.stopPropagation(); 
                }});

                //
                //
                this.btnAddSortItem = this.pnl_Items.find('.sort_field_add');

                this._on(this.btnAddSortItem, {click:function(event){
                    
                    that.addSortItem();
                }});
                
                this.search_conjunction = this.pnl_Items.find('.search_conjunction').find('select');
                this._on(this.search_conjunction, {change:this._doCompose});
                
                this._on(this.pnl_Rectype.find('#btn-clear').button(), { click:this.clearAll });
                
                                
            }
            
            
            /*                    
                var rectypeIds = [this.select_main_rectype.val()];
                if(this.select_additional_rectypes && this.select_additional_rectypes.editing_input('instance')){
                    var val = this.select_additional_rectypes.editing_input('getValues');
                    if(val){
                        val = val[0].split(',');
                        var is_reduced = false, vals=[];
                        for (var i=0; i<val.length; i++){
                            if(val[i] && window.hWin.HEURIST4.util.findArrayIndex(val[i], rectypeIds)<0){
                                rectypeIds.push(val[i]);
                                vals.push(val[i]);
                            }else{
                                is_reduced = true;
                            }
                        }
                        if(is_reduced){
                            this.select_additional_rectypes.editing_input('setValue',vals.join(','));    
                        }
                        //rectypeIds = rectypeIds.concat(val);
                    }
                }
                 */
                
                
            
            if(!this.options.is_dialog){
                //add header and button set for inline mode
                var h = this.element.find('.btn-preview').is(':checked') ?'88px':'50px';

                this.element.css({'font-size':'0.9em'});
                this.pnl_Rectype.css({top:'35px'});
                //var itop = 36+this.pnl_Rectype.height()+1;
                this.pnl_Tree.css({top:35}); //, bottom:h
                this.pnl_Items.css({top:115, bottom:h});
                this.pnl_CoverAll.css({top:115, bottom:h});
                this.pnl_Result.css({bottom:'40px'});
                var _innerTitle = $('<div class="ui-heurist-header" style="top:0px;padding-left:10px;text-align:left">Filter builder</div>')
                    .insertBefore(this.pnl_Rectype);
                
                this._on(    
                $('<button>').button({icon:'ui-icon-closethick',showLabel:false, label:'Close'}) 
                     .css({'position':'absolute', 'right':'4px', 'top':'6px', height:20, width:20})
                     .appendTo(_innerTitle),
                     {click:function(){
                         that.closeDialog();
                     }});
                    
                    
                //button panel on the botom                        
                var ele = this.element.find('.popup_buttons_div').show();
            
                ele.find('.btn-search').button({icon:'ui-icon-filter'});
                this._on(ele.find('.btn-search'),{click:this._doSearch});

                ele.find('.btn-save').button().hide();
                this._on(ele.find('.btn-save'),{click:this._doSaveSearch});
                
                this._on(ele.find('.btn-preview'),{change:function(e){
                    
                    var h;
                    if(this.element.find('.btn-preview').is(':checked')){
                        h = this.options.is_dialog ? '50px':'88px';                       
                        this.pnl_Result.show();
                        this._doCompose();
                    }else{
                        h = this.options.is_dialog ? '0px':'50px';                       
                        this.pnl_Result.hide();
                    }
                        this.pnl_Items.css('bottom',h);
                        this.pnl_CoverAll.css('bottom',h);
                        //this.pnl_Tree.css('bottom',h);
                }});
                
                
                this._on(ele.find('.btn-copy'),{click:function(e){
                        var s = this.pnl_Result.text();
                        if(s) window.hWin.HEURIST4.util.copyStringToClipboard(s);
                }});
                
            }
                
                
            //this.adjustTreePanel();
        //window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, $dlg); 
        
        //add first field item
        /*
        if(!this.is_advanced && $.isEmptyObject(this.field_array)){
            
            this.btnAddFieldItem.click();
            //var rty_ID = this.select_main_rectype.val();
            //this.addFieldItem( 'any:anyfield', [rty_ID , 'anyfield'] );
            //this.addSortItem();
        }
        */
        this.adjustDimension();
        
        
        
    },

    
    clearAll: function()
    {
        this._doCompose();        
        
        this.pnl_Items.children('div:not(.btn_field_add)').remove();
        this.field_array = [];
        this.sort_array = [];
        this.adjustDimension();
        
        //var rty_ID = this.select_main_rectype.val();
        //this.addFieldItem( 'any:anyfield', [rty_ID , 'anyfield'] );
        //this.addSortItem();
        
    },
    
    //
    // nulti record type selector
    //
    _createInputElement_RecordTypeSelector: function(){
        
        var that = this;

        var ed_options = {
            recID: -1,
            dtID: "dty_PtrTargetRectypeIDs",
            dtFields:{
                "dty_Type":"resource",
                "rst_DisplayName":"Also search for:",
                "rst_DisplayHelpText": "", 
//This determines the record type which will be retrieved by the search. The facets can, however, be based on attributes of other record types linked from this type                
                "rst_FieldConfig": {"entity":"DefRecTypes","csv":true}
            },
            change: function(){
                    var val = this.getValues();
                    val = val[0].split(',');
                    //$.each(val,function(i,item){ names.push( $Db.rty(item,'rty_Name') ) });
                    /*
                    that.pnl_Tree.find('#field_treeview').hide();
                    setTimeout(function(){
                        that.adjustTreePanel();
                        that.pnl_Tree.find('#field_treeview').fadeIn(500);
                    },1000);
                    */
            }    
        };

        return $("<div>").editing_input(ed_options).insertAfter( this.element.find('.main-rectype') );
    }
  

    // init fieldtreeview
    , _initTreeView: function(rectypeIds){
        
        //if(!this.is_advanced) return;
        

        if(window.hWin.HEURIST4.util.isArrayNotEmpty(rectypeIds) && this.current_tree_rectype_ids != rectypeIds.join(',') ){
            /*if(!this.options.params.rectypes ||
            !($(rectypeIds).not(this.options.params.rectypes).length == 0 &&
            $(this.options.params.rectypes).not(rectypeIds).length == 0))*/
            {

                var that = this;
                //this.options.params.rectypes = rectypeIds;
                var treediv = this.element.find('#field_treeview');
                var rectype = rectypeIds.join(',');

                /*
                if(this.options.params.rectypes){
                    rectype = this.options.params.rectypes.join(',');
                }
                */
                
                //window.hWin.HEURIST4.util.setDisabled($('#btnNext'),true);

                //'title','modified',
                var allowed_fieldtypes = ['header_ext','enum','freetext','blocktext',
                                'geo','year','date','integer','float','resource','relmarker','relationtype','file'];
                      
/*              
                            {key:'title',title:'Title (constructed)', depth:1},
                            {key:'added',title:'Date added', depth:1},
                            {key:'modified',title:'Date modified', depth:1},
                            {key:'addedby',title:'Creator (user)', depth:1},
                            {key:'url',title:'URL', depth:1},
                            {key:'notes',title:'Notes', depth:1},
                            {key:'owner',title:'Owner (user or group)', depth:1},
                            {key:'access',title:'Visibility', depth:1},
                            {key:'tag',title:'Tags', depth:1}
*/              
                
                var treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 5, rectype, allowed_fieldtypes );
/*                
                //load definitions for given rectypes
                window.hWin.HAPI4.SystemMgr.get_defs({rectypes: rectype,
                    mode:5, //special node - returns data for treeview
                    fieldtypes:allowed_fieldtypes},  //ART20150810 this.options.params.fieldtypes.join() },
                    function(response){

                        if($.isArray(response) || response.status == window.hWin.ResponseStatus.OK){

                            //create unique identificator=code for each leaf fields - rt:ft:rt:ft....
                            if($.isArray(response)){
                                  treedata = response;
                            }else 
                            if(response.data.rectypes) {
                                treedata = response.data.rectypes;
                            }
*/
//console.log(treedata);
                            treedata[0].expanded = true; //first expanded
                            
                            //add additional entry for direct links (resource fields)
                            function __addResField(tdata){
                                
                                //$.each(tdata, function(i, node)
                                var i = 0;
                                while (i<tdata.length)
                                {
                                    node = tdata[i];
                                    if(node.type=='resource'){

                                        var codes = node.code.split(':');
                                        var dtid = codes[codes.length-1];
                                        var linktype = dtid.substr(0,2);
                                        if(linktype=='lt'){
                                            codes[codes.length-1] = dtid.substr(2);                        
                                            tdata.splice(i, 0, 
                                                {key:node.key, type:'resource',
                                                title: node.title
                                                       +'<span style="font-size:0.8em"> (record pointer)</span>',
                                                code:codes.join(':')});                                        
                                            node.title = ' fields'; //node.title + 
                                            node.extraClasses = 'green-triangle';
                                            i++;                       
                                        }
                                    }else if(node.type=='relmarker'){

                                        var codes = node.code.split(':');
                                        var dtid = codes[codes.length-1];
                                        var linktype = dtid.substr(0,2);
                                        if(linktype=='rt'){
                                            codes[codes.length-1] = dtid.substr(2);                        
                                            tdata.splice(i, 0, 
                                                {key:node.key, type:'relmarker',
                                                title: node.title
                                                       +'<span style="font-size:0.8em"> (related record)</span>',
                                                code:codes.join(':')});                                        
                                            node.title = ' fields'; //node.title + 
                                            node.extraClasses = 'green-triangle';
                                            i++;                       
                                        }
                                        
                                    }

                                    i++;    
                                }
                                
                            }
                            
                            __addResField(treedata[0].children);
                            
                            
                            if(!treediv.is(':empty') && treediv.fancytree('instance')){
                                treediv.fancytree('destroy');
                            }

                            //setTimeout(function(){
                            treediv.addClass('tree-facets hidden_checkboxes').fancytree({
                                //extensions: ["filter"],
                                //            extensions: ["select"],
                                checkbox: true,
                                selectMode: 1,  // single
                                source: treedata,
                                beforeSelect: function(event, data){
                                    // A node is about to be selected: prevent this, for folder-nodes:
                                    if( data.node.hasChildren() ){
                                        return false;
                                    }
                                },
                                lazyLoad: function(event, data){
                                    
                                    var node = data.node;
                                    var parentcode = node.data.code; 
                                    var rectypes = node.data.rt_ids;
                                    
                                    var res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 5, rectypes, allowed_fieldtypes, parentcode );
                                    if(res.length>1){
                                        data.result = res;
                                    }else{
                                        data.result = res[0].children;
                                    }
                                    
                                    if(node.data.type=='resource'){
                                        
                                        __addResField(data.result);                                        
                                        
                                        /* option: add the same item as in __addResField but on next level
                                        var codes = parentcode.split(':');
                                        var dtid = codes[codes.length-1];
                                        var linktype = dtid.substr(0,2);
                                        if(linktype=='lt'){
                                            codes[codes.length-1] = dtid.substr(2); 
                                            data.result.unshift(
                                                {key:node.data.key,type:'resource',
                                                title:'<span style="font-size:0.9em;font-style:italic;padding-left:22px">Pick the specific resource</span>',
                                                name:'Known resource',code:codes.join(':')});
                                        }
                                        */
                                    }
                                    
                                    return data;                                                   
                                    /* from server
                                    var node = data.node;
                                    var sURL = window.hWin.HAPI4.baseURL + "hsapi/controller/sys_structure.php";
                                    data.result = {
                                        url: sURL,
                                        data: {db:window.hWin.HAPI4.database, mode:5, parentcode:node.data.code, 
                                            rectypes:node.data.rt_ids, fieldtypes:allowed_fieldtypes}
                                    } 
                                    */                                   
                                },
                                expand: function(e, data){
                                    that.showHideReverse(data);
                                },
                                collapse: function(e, data){
                                    that._update_GenericField(data.node);
                                },
                                loadChildren: function(e, data){
                                    setTimeout(function(){
                                        that.showHideReverse(data);   
                                        //that._assignSelectedFacets();
                                    },500);
                                },
                                select: function(e, data) {
                                    /* Get a list of all selected nodes, and convert to a key array:
                                    var selKeys = $.map(data.tree.getSelectedNodes(), function(node){
                                    return node.key;
                                    });
                                    $("#echoSelection3").text(selKeys.join(", "));

                                    // Get a list of all selected TOP nodes
                                    var selRootNodes = data.tree.getSelectedNodes(true);
                                    // ... and convert to a key array:
                                    var selRootKeys = $.map(selRootNodes, function(node){
                                    return node.key;
                                    });
                                    $("#echoSelectionRootKeys3").text(selRootKeys.join(", "));
                                    $("#echoSelectionRoots3").text(selRootNodes.join(", "));
                                    */
                                },
                                click: function(e, data){
                                   e.stopPropagation(); 
                                    
                                   if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                                       data.node.setExpanded(!data.node.isExpanded());
                                       //treediv.find('.fancytree-expander').hide();
                                   }else if( data.node.lazy) {
                                       data.node.setExpanded( true );
                                   }else{
                                        var code = data.node.data.code;
                                        var codes = code.split(':');

                                        var codes2 = code.split(':');
                                        codes2[0] = 'any';
                                        code = codes2.join(':');
                                      
                                        //add or replace field in builderItem
                                        that.addFieldItem( code, codes, that.select_field_for_id);
                                        that.select_field_for_id = null;
                                        that.pnl_Tree.hide();
                                   }
                                },
                                dblclick: function(e, data) {
                                    data.node.toggleSelected();
                                },
                                keydown: function(e, data) {
                                    if( e.which === 32 ) {
                                        data.node.toggleSelected();
                                        return false;
                                    }
                                }
                                // The following options are only required, if we have more than one tree on one page:
                                //          initId: "treeData",
                                //cookieId: "fancytree-Cb3",
                                //idPrefix: "fancytree-Cb3-"
                            });
                            //},1000);


                            //hide all folder triangles
                            //treediv.find('.fancytree-expander').hide();

                            that.current_tree_rectype_ids = rectypeIds.join(',');

                            $("#fsw_showreverse").change(function(event){

                                that.showHideReverse();
                                /*
                                var showrev = $(event.target).is(":checked");
                                var tree = treediv.fancytree("getTree");
                                tree.visit(function(node){
                                    if(node.data.isreverse==1){ //  window.hWin.HEURIST4.util.isArrayNotEmpty(node.children) &&
                                                if(showrev===true){
                                                    $(node.li).removeClass('fancytree-hidden');
                                                }else{
                                                    $(node.li).addClass('fancytree-hidden');
                                                }
                                    }
                                });*/
                            });

                            //tree.options.filter.mode = "hide";
                            //tree.options.filter.highlight = false;
                            $("#fsw_showreverse").attr('checked', false);
                            $("#fsw_showreverse").change();

/* server response
                        }else{
                            window.hWin.HEURIST4.msg.redirectToError(response.message);
                        }
                        window.hWin.HEURIST4.util.setDisabled($('#btnNext'), false);
                });
                */

            }
        }
    }
    
    , showHideReverse: function(data){
        
        var showrev = $('#fsw_showreverse').is(":checked");
        var treediv = $('#field_treeview');
        var tree = treediv.fancytree("getTree");
        var that = this;
        tree.visit(function(node){

            if(node.data.isreverse==1){ 

                if(showrev===true){
                    $(node.li).removeClass('fancytree-hidden');
                }else{
                    $(node.li).addClass('fancytree-hidden');
                }
            }
            if(node.data.is_generic_fields){ 
                   that._update_GenericField(node);
            }
        });
        
        //this._update_GenericField(data);
    }
    
    ,_update_GenericField: function(node){
        
            if(!node) return;
        
            if(node.data.is_generic_fields){ 
                    var ele = $(node.li).find('.fancytree-checkbox');
                    if(node.isExpanded()){
                       ele.css({'background-position': '-32px -80px'});   //-48px -80px  
                    }else{
                       ele.css({'background-position': '0px -80px'});     
                    }
            }
            
        
    }
    
     //
    // close dialog
    //
    , closeDialog: function(is_force){
        if(this.options.is_dialog){
            
            if(is_force===true){
                this._dialog.dialog('option','beforeClose',null);
            }
            this._dialog.dialog("close");
            
        }else{
            
            var canClose = true;
            if($.isFunction(this.options.beforeClose)){
                canClose = this.options.beforeClose();
            }
            if(canClose){
                if($.isFunction(this.options.onClose)){
                    this.options.onClose();
                }
            }
        }
    }

    //
    // save into database
    //
    ,_doSaveSearch: function(prevent_real_save){
        
        this._doCompose();

    }

    ,_doSearch: function(){
        
        this._doCompose();
        
        var query = this.pnl_Result.text();
        
        if(query){
        
            var request = {};
                request.q = query;
                request.w  = 'a';
                request.detail = 'ids';
                request.source = this.element.attr('id');
                request.search_realm = this.options.search_realm;
                
                window.hWin.HAPI4.SearchMgr.doSearch( this, request );
            
            this.closeDialog();
        }else{
           window.hWin.HEURIST4.msg.showMsgFlash('Define at least one criterion');
        }

    }
    
    ,_doCompose: function(){
        
        this.pnl_Result.empty()
        
        var mainquery = [];
        
        var rty_IDs = this.select_main_rectype.val();
        
        if(rty_IDs<0){
            return '';
        }
        

        //sort by code
        this.field_array.sort( function(a, b){
            return (a.searchBuilderItem('getCodes')<b.searchBuilderItem('getCodes'))?-1:1;            
        });
        
        /*
        var aCodes = Object.keys(this.field_items);
        aCodes.sort(function(a,b){
            return (a<b)?-1:1;            
        });
        */
        
/*
console.log(aCodes);    
0: "10:20"
1: "10:lt134:12:1147"
2: "10:lt134:12:26"
3: "10:lt241:12:133"
4: "10:lt241:12:rt:17:135"
     0    1    2   3    4
*/ 

        var fields_query = [];

        var that = this;
        
        $.each(this.field_array, function(i, ele){
            
            //var ele = that.field_array[i]; // that.field_items[code];
            var code = ele.searchBuilderItem('getCodes');
//console.log(code);            
            
            var value = ele.searchBuilderItem('getValues');
            var branch;
            if(value!=null){
                codes = code.split(':');
                
                branch = fields_query;    
                //find branch
                if(codes.length>2){

                    //add new ones if not found
                    var key;
                    for(var k=1; k<codes.length-1; k++){
                        if(k%2 == 0){ //rectype
                            //key = 't:'+codes[k];    
                            if(codes[k]!=''){ //unconstrainded
                                var not_found = true;
                                $.each(branch,function(m,item){
                                   if(item['t']==codes[k]){
                                       not_found = false; 
                                       return false;
                                   }else if(item['t']){
                                        not_found = false; 
                                        if(item['t'].split(',').indexOf(codes[k])<0){
                                            item['t'] = item['t']+','+codes[k];
                                        }
                                        return false;
                                   }
                                });
                                
                                if(not_found){
                                    branch.push({t:codes[k]});    
                                }
                            }
                        }else{
                            var dtid = codes[k];
                            var linktype = dtid.substr(0,2);
                            var slink = '';
                            if(linktype=='rt'){
                                slink = "related_to:";
                            }else if(linktype=='rf'){
                                slink = "relatedfrom:";
                            }else if(linktype=='lt'){
                                slink = "linked_to:";
                            }else if(linktype=='lf'){
                                slink = "linkedfrom:";
                            }
                            if(slink!=''){
                                dtid = dtid.substr(2);
                                key = slink+dtid;    
                            }else{
                                /*if($Db.dty(dtid,'rty_Type')=='relmarker'){
                                    key = 'related_to:'+dtid;
                                }else{
                                    key = 'f:'+dtid;    
                                }*/
                                key = 'f:'+dtid;
                            }
                        
                            //find
                            var not_found = true;
                            $.each(branch, function(i,item){
                                if(item[key]){
                                    branch = item[key];
                                    not_found = false;
                                    return false;
                                }
                            });

                            //add new branch 
                            if(not_found){
                                var newbranch = {};
                                newbranch[key] = [];
                                branch.push(newbranch);
                                branch = newbranch[key];
                            }   
                        }   
                        /*                    
                        if(!branch[key]){
                            var newbranch = {};
                            newbranch[key] = [];
                            branch.push(newbranch);
                            branch = newbranch;
                        }else{
                            branch = branch[key]; //step down
                        }
                        */
                    }//for
                }
                
                branch.push(value);        
            }
            
        });
        
        if(fields_query.length>0){
        
            var fields_conjunction = this.search_conjunction.val();
            if(fields_query.length>1 && fields_conjunction=='any'){
                mainquery.push({any:fields_query});
            }else{
                mainquery = mainquery.concat(fields_query)
            }
        }
        
        
        if(mainquery.length>0){

        
            if(rty_IDs>0){
                
                if(this.svs_MultiRtSearch.is(':checked')){
                    var s = this.select_additional_rectypes.editing_input('getValues')[0];
                    if(s){
                        if(s.split(',').indexOf(rty_IDs)<0){
                            rty_IDs = rty_IDs+','+s;    
                        }else{
                            rty_IDs = s;    
                        }
                    }
                }
                
                mainquery.unshift({t:rty_IDs});
            }            
            
            $.each(this.sort_array, function(i, ele){
                var val = ele.searchBuilderSort('getValue');
                if(val){
                    mainquery.push({sortby:val});    
                }    
            });
            
            this.pnl_Result.text( JSON.stringify(mainquery) );    
        } 
        
        var conjunct = (this.search_conjunction.val()=='any')?'OR':'AND';
        var $fields_headers = $('.field_header');
        var cnt = $fields_headers.length;

        for(var i=1; i<cnt; i++){
            $($fields_headers[i]).text(conjunct);
        }
        
    }
});

//
//
//
function showSearchBuilder( params ){
    
        var manage_dlg = $('#heurist-searchBuilder');
        
        params = (!params)?{is_h6style:true}:params;
        
        params.is_dialog = true;

        var need_create = (manage_dlg.length<1);
        
        if( need_create ){

            manage_dlg = $('<div id="heurist-searchBuilder">')
            .addClass('save-filter-dialog')
            .appendTo( $('body') );
            manage_dlg.searchBuilder( params );
        }else{
            if(!params.is_modal && params.params==null){
                params.params = manage_dlg.searchBuilder('option', 'params');
            }
            
            manage_dlg.searchBuilder('option', params);
        }

        manage_dlg.searchBuilder( 'show' );
}