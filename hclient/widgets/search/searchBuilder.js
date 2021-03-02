/**
*  Wizard to define advanced search
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

$.widget( "heurist.searchBuilder", {

    // default options
    options: {
        is_dialog: false,
        is_h6style: false,
        svsID: null,
        domain: null, // bookmark|all or usergroup ID
        is_modal: true,
        menu_locked: null,
        onsave: null
    },

    is_edit_continuing: false, //???
    _lock_mouseleave: false,
    _save_in_porgress: false,

    //controls
    select_main_rectype: null,
    select_additional_rectypes: null,
    svs_MultiRtSearch: null,

    current_tree_rectype_ids:null, //to avoid reload
    
    group_items:{}, //groups - to be implemented
    field_items:{}, //fields for current groups

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
                height: 650,
                width:850,
                modal: this.options.is_modal,

                resizable: true, //!is_h6style,
                draggable: this.options.is_modal, //!this.options.is_h6style,
                //position: this.options.position,
                
                title: window.hWin.HR('Query builder'),
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
                    {text:window.hWin.HR('Search'), id:'btnSearch',
                        class:'ui-button-action', 
                        click: function() {
                            that._doSearch()
                    }},
                    {text:window.hWin.HR('Save'), id:'btnSave',
                        click: function() {
                            that._doSaveSearch()
                            //that.navigateWizard(1);
                    }},
                ],
                show: {
                    effect: 'fade',
                    duration: 500
                }
            });
        
            this.element.parent().addClass('ui-dialog-heurist');
            this.element.parent().find('.ui-dialog-buttonset').css('margin-right','260px');
        }else{
            //add header and button set for inline mode
            
        }
        
        this.element.load(window.hWin.HAPI4.baseURL+"hclient/widgets/search/searchBuilder.html", function(){
        
            that._initControls();
            //that.pnl_Tree    
            //that.pnl_Items
            //that.pnl_Result
        });


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
        /*    
        var ch =   this._dialog[0].scrollHeight+100; 
        var width = this._dialog.dialog( 'option', 'width');
        var minw = 650;
            
            //var $dlg = this.step0.find("#facets_options");
            //minw = $dlg.find( ".ui-accordion-content-active" ).length>0?800:650;
            
        //ch = Math.max($dlg.height(),$dlg[0].scrollHeight)+100; 
        if(ch<350) ch = 350;
            
        //if(width<minw) 
        this._dialog.dialog( 'option', 'width', minw);
        
        var topPos = 0;
        var pos = this._dialog.dialog('option', 'position');
        if(pos && pos.of && !(pos.of instanceof Window)){
            topPos = $(pos.of).offset().top+40;
        }

        var dh =  this._dialog.dialog('option', 'height');

        var ht = Math.min(ch, window.innerHeight-topPos);
        this._dialog.dialog('option', 'height', ht);    
        */
        /*
        if(this.options.is_h6style){
            //var dialog_height = window.innerHeight - this.element.parent().position().top - 5;
            //this.element.dialog( 'option', 'height', dialog_height);
        }else{
            var ht = window.innerHeight; //$(window).height();
            if(ht>700) ht = 700;
            this.element.dialog( 'option', 'height',  ht );
        }
        */
    }

    ,show: function( ){
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
            this.adjustDimension();
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

    , _initControls: function(){
        
            var that = this;

            if(this.select_main_rectype==null){
                
                this.select_main_rectype = window.hWin.HEURIST4.ui.createRectypeSelectNew( this.element.find("#opt_rectypes").get(0),
                {
                    topOptions: [{key:'',title:'select...'}],
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
                    }}});
                    
                this.select_main_rectype.hSelect({change: function(event, data){
                    if(that.select_additional_rectypes){
                        that.select_additional_rectypes.editing_input('setValue', '');
                    }
                    //load list of field types
                    that._initFieldTreeView([that.select_main_rectype.val()]);
                }});
                    
            }
            
            this.pnl_Items = this.element.find('#pnl_Items');
            
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
                
                

        //window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, $dlg); 
        
    }

    //
    // record type selector (@todo move to utils_ui ???)
    //
    ,_createInputElement_RecordTypeSelector: function(){
        
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
            }    
        };

        return $("<div>").editing_input(ed_options).insertAfter( this.element.find('.main-rectype') );
    }
  

    // init fieldtreeview
    , _initFieldTreeView: function(rectypeIds){

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
                var allowed_fieldtypes = ['enum','freetext','blocktext',
                                'geo','year','date','integer','float','resource','relmarker'];
                      
                
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
                            
                            if(!treediv.is(':empty') && treediv.fancytree('instance')){
                                treediv.fancytree('destroy');
                            }

                            //setTimeout(function(){
                            treediv.addClass('tree-facets').fancytree({
                                //extensions: ["filter"],
                                //            extensions: ["select"],
                                checkbox: false,
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
                                    that.showHideReverse();
                                },
                                loadChildren: function(e, data){
                                    setTimeout(function(){
                                        that.showHideReverse();   
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
                                   if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                                       data.node.setExpanded(!data.node.isExpanded());
                                       //treediv.find('.fancytree-expander').hide();
                                   }else if( data.node.lazy) {
                                       data.node.setExpanded( true );
                                   }else{
                                        var code = data.node.data.code;
                                        console.log(code);
                                        //highlight or add the selected field
                                        //data.node.code
                                        if(that.field_items[code]){
                                            //highlight 
                                            
                                        }else{
                                            //add the selected field
                                            
                                            var codes = code.split(':');
                                            var ele = $('<div>').attr('data-code',code).appendTo(that.pnl_Items);
                                            that.field_items[code] = ele;
                                            
                                            
                                            ele.searchBuilderItem({
                                                    token: 'f',
                                                    code: code,
                                                    rty_ID: codes[codes.length-2],
                                                    dty_ID: codes[codes.length-1],
                                                    onremove: function(code){
                                                        that.field_items[code] = null;
                                                        that.pnl_Items.find('div[data-code="'+code+'"]').remove();
                                                    },
                                                    onchange: function(){
                                                        that._doCompose();
                                                    }
                                                });
                                        }
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
    
    , showHideReverse: function(){
        
        var showrev = $('#fsw_showreverse').is(":checked");
        var treediv = $('#field_treeview');
        var tree = treediv.fancytree("getTree");
        tree.visit(function(node){

            if(node.data.isreverse==1){ 

                if(showrev===true){
                    $(node.li).removeClass('fancytree-hidden');
                }else{
                    $(node.li).addClass('fancytree-hidden');
                }
            }
        });
    }
    

    //
    // save into database
    //
    ,_doSaveSearch: function(prevent_real_save){

    }

    ,_doSearch: function(){

    }
    
    ,_doCompose: function(){
        
        var query = {};
        
        if(this.current_tree_rectype_ids){
            query['t'] = this.current_tree_rectype_ids;
        }
        
        $.each(Object.keys(this.field_items),function(i, item){
            
        });
        
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