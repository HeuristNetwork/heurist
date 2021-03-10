/**
* manageDefRecStructure.js - main widget to manage record type structure
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

$.widget( "heurist.manageDefRecStructure", $.heurist.manageEntity, {
    
//specific options
//   rty_ID: 
//   rec_ID_sample: 
//   external_preview: send this widget to use as preview
    
    
    _entityName:'defRecStructure',
    
    _fakeSepIdsCounter:0,
    _lockDefaultEdit: false,
    _dragIsAllowed: true,

    _menuTimeoutId: -1,

    _stillNeedUpdateForRecID: 0,
    usrPreferences:{
        treepanel_closed: true,          
        treepanel_width:400,
        help_on: false
    },
    
    menues: {}, //popup menu for this widget
    _open_formlet_for_recID: 0,
    _show_optional: false,
    defval_container: null,
    
    //
    //
    //    
    _init: function() {

        this.options.default_palette_class = 'ui-heurist-design';
        
        //special header field stores UI structure
        if(!(this.options.rec_ID_sample>0)) {
            this.options.rec_ID_sample = -1; //record id that will be loaded in preview
        }
        if(!(this.options.rty_ID>0)) {
            this.options.rty_ID = 4; //57; //by default - it is required   
        }
        this.previewEditor = null; // record editor for preview
        
        this.options.layout_mode = 'short';
        this.options.use_cache = true;
        this.options.use_structure = false;
        
        //this.options.select_return_mode = 'recordset';
        this.options.edit_height = 640;
        this.options.edit_width = 640;
        
        this.options.width = 1200;
        this.options.height = 640;
        this.options.edit_mode = 'inline';//'popup'; //editonly
        this.options.editClassName = 'ui-heurist-bg-light'; // was 'ui-widget-content';

        if(this.options.edit_mode=='editonly'){
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
            
        }else if(this.options.external_preview){ 
            
            this.options.layout_mode = 
                '<div class="treeview_with_header" style="background:white">'
                    +'<div style="padding:10px 20px 4px 10px;border-bottom:1px solid lightgray">' //instruction and close button
                        +'<span style="font-style:italic;display:inline-block">Drag to reposition<br>'
                        +'Select or <span class="ui-icon ui-icon-gear" style="font-size: small;"/> to modify</span>&nbsp;&nbsp;&nbsp;'
                        //+'<button style="vertical-align:top;margin-top:4px;" class="closeRtsEditor"/>'
                        +'<span style="position:absolute; right:4px;width:32px;top:26px;height:32px;font-size:32px;cursor:pointer" class="closeTreePanel ui-icon ui-icon-carat-2-w"/>'
                    +'</div>'
                    +'<div class="treeView" style="margin-left:-27px;margin-right:-10px;"/>' //treeview
                    +'<div class="editForm editFormRtStructure" style="top:0px;display:none">EDITOR</div>'
                    +'<div class="recordList" style="display:none"/>'
                +'</div>';
            
        }else {
            
            this.options.layout_mode =                 
                '<div style="display:none">'
                    +'<div class="ent_header searchForm"/>'     
                    +'<div class="ent_content_full recordList"/>'
                +'</div>'
                
                +'<div class="main-layout ent_wrapper">'
                        
                        +'<div class="ui-layout-west">'
                                +'<div class="treeview_with_header" style="background:blue">'
                                    +'<div style="padding:10px 20px 4px 10px;border-bottom:1px solid lightgray">' //instruction and close button
                                        +'<span style="font-style:italic;display:inline-block">Drag to reposition<br>'
                                        +'Select or <span class="ui-icon ui-icon-gear" style="font-size: small;"/> to modify</span>&nbsp;&nbsp;&nbsp;'
                                        //+'<button style="vertical-align:top;margin-top:4px;" class="closeRtsEditor"/>'
                                        +'<span style="position:absolute; right:4px;width:32px;top:26px;height:32px;font-size:32px;cursor:pointer" class="closeTreePanel ui-icon ui-icon-carat-2-w"/>'
                                    +'</div>'
                                    +'<div class="treeView" style="margin-left:-27px;"/>' //treeview
                                +'</div>'
                        +'</div>'
                                
                        +'<div class="ui-layout-center">'
                                +'<div class="preview_and_edit_form">'
                                +    '<div class="editForm" style="top:0px">EDITOR</div>'
                                +    '<div class="previewEditor" style="display:none;top:0px"/>'
                                +'</div>'
                        +'</div>'
                +'</div>';
        }

        this._super();
        
        
    },
    
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
        
        this.usrPreferences = this.getUiPreferences();
        
        if(this.options.edit_mode=='editonly'){
            this._initEditorOnly();  //by this.options.rec_ID
            return;
        }
        
        //update dialog title
        if(this.options.isdialog){ // &&  !this.options.title
            var title = null;
            
            if(this.options.title){
                title = this.options.title;
            }else{
                title = 'Manage Record Structure';    
                if(this.options.rty_ID>0){
                    title = title+': '+window.hWin.HEURIST4.dbs.rtyField(this.options.rty_ID, 'rty_Name');  //rectypes.names[this.options.rty_ID];
                }
            }
            
            this._as_dialog.dialog('option', 'title', title);    
        }

        var that = this;
        
        if(!this.options.external_preview){
        
            var layout_opts =  {
                applyDefaultStyles: true,
                togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-w"></div>',
                togglerContent_closed:  '<div class="ui-icon ui-icon-carat-2-e"></div>',
                //togglerContent_open:    '&nbsp;',
                //togglerContent_closed:  '&nbsp;',
                west:{
                    size: this.usrPreferences.treepanel_width,
                    maxWidth:800,
                    minWidth:340,
                    spacing_open:6,
                    spacing_closed:40,  
                    togglerAlign_open:'center',
                    //togglerAlign_closed:'top',
                    togglerAlign_closed:16,   //top position   
                    togglerLength_closed:40,  //height of toggler button
                    initClosed: (this.usrPreferences.treepanel_closed==true || this.usrPreferences.treepanel_closed=='true'),
                    slidable:false,  //otherwise it will be over center and autoclose
                    contentSelector: '.treeview_with_header',   
                    onopen_start : function( ){ 
                        var tog = that.element.find('.ui-layout-toggler-west');
                        tog.removeClass('prominent-cardinal-toggler');
                    },
                    onclose_end : function( ){ 
                        var tog = that.element.find('.ui-layout-toggler-west');
                        tog.addClass('prominent-cardinal-toggler');
                    }
                },
                center:{
                    minWidth:400,
                    contentSelector: '.preview_and_edit_form'    
                }
            };

            this.mainLayout = this.element.find('.main-layout');
            this.mainLayout.layout(layout_opts); //.addClass('ui-heurist-bg-light')

            if(this.usrPreferences.treepanel_closed==true || this.usrPreferences.treepanel_closed=='true'){
                        var tog = that.mainLayout.find('.ui-layout-toggler-west');
                        tog.addClass('prominent-cardinal-toggler');
            }
            
            this.previewEditor = this.element.find('.previewEditor');
            
        }else{
            this.previewEditor = this.options.external_preview;
        }
        
        
        
        
        this.recordList.resultList('option', 'show_toolbar', false);
        this.recordList.resultList('option', 'pagesize', 9999);
        this.recordList.resultList('option', 'view_mode', 'list');
        
        if(this.options.use_cache){ //init list only once

            if(this.options.use_structure){  //take fata from $Db
                //take recordset from HEURIST4.rectypes format     
                this._cachedRecordset = this._getRecordsetFromStructure();
                
            }else{
                
                //via entity data
                this._cachedRecordset = $Db.rst(this.options.rty_ID);  //from  rst_Index
                if(this._cachedRecordset==null){
                    this._cachedRecordset = new hRecordSet({entityName:'defRecStructure',count:0,offset:0,order:[]});
                }
                /*from server
                var that = this;
                window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, false,
                    function(response){
                        that._cachedRecordset = response;
                        that.recordList.resultList('updateResultSet', response);
                    });
                */    
            }
            this.recordList.resultList('updateResultSet', this._cachedRecordset);
            this._initTreeView();
            this._showRecordEditorPreview( true );
        }    
        
        if(this.options.external_toolbar){
            // IJ: need to edit record and rt in the same time
            this.toolbarOverRecordEditor(this.options.external_toolbar);    
        }
        
        if(this._toolbar) this._toolbar.find('.ui-dialog-buttonset').css({'width':'100%','text-align':'right'});
        
        this._initMenu('rep');
        this._initMenu('req');
        
        /*
        this.btnClose = this.element.find('.closeRtsEditor').button({label:window.hWin.HR('Close')});
        this._on(this.btnClose, {click:this.closeDialog});
        */

        //this.btnCloseTree = this.element.find('.closeTreePanel').button({text:false,icon:'ui-icon-carat-2-w'});
        this._on(this.element.find('.closeTreePanel'), {click:function(){ 
            if(this.options.external_preview){
                this.options.external_preview.manageRecords('closeWestPanel');
            }else{
                this.mainLayout.layout().close("west"); 
            }
        }});
        
        return true;
    },            
    
    //
    // get recordset from HEURIST4.rectypes - NOT USED
    //
    _getRecordsetFromStructure: function(){
        
        var rdata = { 
            entityName:'defRecStructure',
            count: 0,
            total_count: 0,
            fields:[],
            records:{},
            order:[] };

        var rectypes = window.hWin.HEURIST4.rectypes; //_getRecordsetFromStructure

        rdata.fields = window.hWin.HEURIST4.util.cloneJSON(rectypes.typedefs.dtFieldNames);
        //add fields to top
        rdata.fields.unshift('rst_RecTypeID'); 
        rdata.fields.unshift('rst_DetailTypeID'); 
        rdata.fields.unshift('rst_ID'); //add as first
        
        //
        // note rectypes.typedefs does not have real rst_ID 
        // combination rty_ID(rst_RecTypeID) and dty_ID (rst_DetailTypeID) is unique
        // we keep dty_ID as rst_ID
        //
        
        if(this.options.rty_ID>0 && rectypes.typedefs[this.options.rty_ID]){
            
            var dtFields = window.hWin.HEURIST4.util.cloneJSON( rectypes.typedefs[this.options.rty_ID].dtFields );

            for (var dty_ID in dtFields)
            {
                if(dty_ID>0){
                    var field = dtFields[dty_ID];
                    if(field){
                        field.unshift(this.options.rty_ID); 
                        field.unshift(dty_ID); 
                        field.unshift(dty_ID); //rts_ID is dty_ID
                        rdata.records[dty_ID] = field;
                        rdata.order.push( dty_ID );
                    }else{
                        console.log('BROKEN RTS '+dty_ID);
                    }
                }
            }
            rdata.count = rdata.order.length;
        
        }
/*
dty_PtrTargetRectypeIDs ->rst_PtrFilteredIDs
dty_JsonTermIDTree -> rst_FilteredJsonTermIDTree
dty_TermIDTreeNonSelectableIDs
        
*/        

        return new hRecordSet(rdata);
        
    },
    
    
    _initTreeView: function(){
        
        var recset = this._cachedRecordset;
        
        if(recset.length()==0){
            this.showBaseFieldEditor(-1, 0);
        }
        
        
        //find all separators and detect json tree data in Extended Description field
        //if such treeview data is missed creates new one based on header/separators and rst_DisplayOrder

        var treeData = false;

        //if such treeview data is missed creates new one based on header/separators and rst_DisplayOrder
        if(!treeData){
            
            //order by rts_DisplayOrder
            var _order = recset.getOrder();
            _order.sort(function(a,b){  
                return (recset.fld( recset.getById(a), 'rst_DisplayOrder')
                        <recset.fld( recset.getById(b), 'rst_DisplayOrder'))
                                ?-1:1;
            });
            recset.setOrder( _order );
            
            treeData = [];
            
            var groupIdx = -1;
            var seps = [];

            recset.each(function(dty_ID, record){
                    
                        var sType = $Db.dty(dty_ID, 'dty_Type');
                        var isSep = (sType=='separator');
                        var title = recset.fld(record,'rst_DisplayName');
                        var req = recset.fld(record,'rst_RequirementType');
                        if(isSep ){
                            title = '<span data-dtid="'+dty_ID+'">' + title 
                                    +'</span>';
                        }else{
                            title = '<span data-dtid="'+dty_ID+'" style="padding-left:10px">' + title 
                                    +'</span>';
//'<span style="font-size:smaller;"> ('+$Db.baseFieldType[sType]+')</span>';
                        }
                        if(req=='forbidden'){
                            title =  title + '<span style="font-size:smaller;text-transform:none;"> (hidden)</span>';
                        }
                    
                    var node = {title: title, key: dty_ID, extraClasses:isSep?'separator2':req};
                    node['data'] = {header:isSep, type:'group'};
                    treeData.push( node );

                    });
                
        }
        
        //init treeview
        var that = this;
        
        var fancytree_options =
        {
            checkbox: false,
            //titlesTabbable: false,     // Add all node titles to TAB chain
            focusOnSelect:true,
            source: treeData,
            quicksearch: true,
            
            /*
            expand: function(event, data){
                // it need to assign action after DnD (for lazy load)
                if(data.node.children && data.node.children.length>0){
                   setTimeout(function(){
                    $.each( $('.fancytree-node'), function( idx, item ){
                        that.__defineActionIcons(item);
                    });
                    }, 500);  
                }
            },
            */
            
            click: function(event, data){

                var ele = $(event.target);
                if(!ele.hasClass('ui-icon')){
                    if(data.node.isActive()){
                        window.hWin.HEURIST4.util.stopEvent(event);
                        that._saveEditAndClose(null, 'close'); //close editor on second click
                    }
                }
                    //add new group/separator
                    //that.addNewSeparator();
                    //window.hWin.HEURIST4.util.stopEvent(event)
                
            },
            activate: function(event, data) { 
                //main entry point to start edit rts field - open formlet
                if(data.node.key>0){
                    that.selectedRecords([data.node.key]);
                    if(!that._lockDefaultEdit){
                    
                        if(that.options.external_preview){
                            that.options.external_preview.manageRecords('saveQuickWithoutValidation',
                                function(){
                                    that.previewEditor.manageRecords('setDisabledEditForm', true)
                                    that._onActionListener(event, {action:'edit'}); //default action of selection            
                                });
                        }else{
                            that.previewEditor.manageRecords('setDisabledEditForm', true)
                            that._onActionListener(event, {action:'edit'}); //default action of selection            
                        }
                        
                    }
                }
            }
        };

        fancytree_options['extensions'] = ["dnd"]; //, "filter", "edit"
        fancytree_options['dnd'] = {
                autoExpandMS: 400,
                preventRecursiveMoves: true, // Prevent dropping nodes on own descendants
                preventVoidMoves: true, // Prevent moving nodes 'before self', etc.
                dragStart: function(node, data) {
                    return that._dragIsAllowed;
                },
                dragEnter: function(node, data) {
                    return (node.folder) ?true :["before", "after"];
                },
                dragDrop: function(node, data) {
                    
                    data.otherNode.moveTo(node, data.hitMode);    
                    //save treeview in database
                    that._dragIsAllowed = false;
                    
                    if(that.options.external_preview){
                        that.options.external_preview.manageRecords('saveQuickWithoutValidation',
                               function(){ that._saveRtStructureTree() });
                    }else{
                        setTimeout(function(){
                            that._saveRtStructureTree();
                        },500);
                    }
                    
                }
            };
/*            
        fancytree_options['edit'] = {
                save:function(event, data){
                    if(''!=data.input.val()){
                        that._avoidConflictForGroup(groupID, function(){
                            that._saveTreeData( groupID );
                        });
                    }
                }
            };     
        fancytree_options['filter'] = { highlight:false, mode: "hide" };  
*/
            this._treeview = this.element.find('.treeView').addClass('tree-rts')
                                .fancytree(fancytree_options); //was recordList
            this.__updateActionIcons(500);

    },
    
    //
    //add and init action icons
    //
    __updateActionIcons: function(delay){ 
        
        if(!(delay>0)) delay = 1;
        var that = this;
        setTimeout(function(){
            $.each( that.element.find('.treeView .fancytree-node'), function( idx, item ){
                that.__defineActionIcons(item);
            });
        }, delay);
    },

    //
    // for treeview on mouse over toolbar
    //
    __defineActionIcons: function(item){ 
           if($(item).find('.svs-contextmenu3').length==0){
               
               if(!$(item).hasClass('fancytree-hide')){
                    $(item).css('display','block');   
               }

               var is_folder = $(item).hasClass('fancytree-folder') || $(item).hasClass('separator2'); 
               
               var actionspan = $('<div class="svs-contextmenu3" style="position:absolute;right:8px;display:none;padding:2px;margin-top:0px;background:#95A7B7 !important;'
                    +'font-size:9px;font-weight:normal;text-transform:none">'
                   //+'<span data-action="block" style="background:lightgreen;padding:4px;font-size:9px;font-weight:normal" title="Add a new group/separator">&nbsp;±&nbsp;&nbsp;Block</span>'               
                   //+'<span data-action="field" style="background:#ECF1FB;padding:4px"><span class="ui-icon ui-icon-plus" title="Add a new field to this record type" style="font-size:9px;font-weight:normal"/>Field</span>'
                   +'<span data-action="delete" style="background:red;padding:4px"><span class="ui-icon ui-icon-close" title="'
                        +((is_folder)?'Delete header':'Exclude field from record type')+'" style="font-size:9px;font-weight:normal"/>Delete</span>'
                   +(true || is_folder?'':
                    '<span class="ui-icon ui-icon-star" title="Requirement"></span>'
                   +'<span class="ui-icon ui-icon-menu" title="Repeatability"></span>')
                   +'</div>').appendTo(item);
                   
               var that = this;

               actionspan.find('span').click(function(event){
                    var ele = $(event.target);
                    that._lockDefaultEdit = true;
                    //timeout need to activate current node    
                    setTimeout(function(){
                        that._lockDefaultEdit = false;
                        var action = ele.attr('data-action');
                        if(!action) action = ele.parent().attr('data-action');
                        if(action=='field'){
                           
                           //add field   
                           that.showBaseFieldEditor(-1, null);
                            
                        }else if(action=='block'){
                            
                            //add new group/separator
                            that.addNewSeparator();

                        }else if(action=='delete'){
                            //different actions for separator and field
                            that._removeField();
                            
                        }else if(ele.hasClass('ui-icon-star')){
                            // requirement
                            that._showMenu(that.menues['menu_req'], ele);
                            
                        }else if(ele.hasClass('ui-icon-menu')){
                            // repeatability
                            that._showMenu(that.menues['menu_rep'], ele);
                            
                        }
                    },100); 
                    //window.hWin.HEURIST4.util.stopEvent(event); 
                    //return false;
               });
               
               /*
               $('<span class="ui-icon ui-icon-pencil"></span>')                                                                
               .click(function(event){ 
               //tree.contextmenu("open", $(event.target) ); 
               
               ).appendTo(actionspan);
               */

               //hide icons on mouse exit
               function _onmouseexit(event){
                       var node;
                       if($(event.target).is('li')){
                          node = $(event.target).find('.fancytree-node');
                       }else if($(event.target).hasClass('fancytree-node')){
                          node =  $(event.target);
                       }else{
                          //hide icon for parent 
                          node = $(event.target).parents('.fancytree-node');
                          if(node) node = $(node[0]);
                       }
                       var ele = node.find('.svs-contextmenu3'); //$(event.target).children('.svs-contextmenu3');
                       ele.hide();//css('visibility','hidden');
                       that.previewEditor.find('div[data-dtid]').removeClass('ui-state-active');
               }               
               
               $(item).hover(
                   function(event){
                       var node;
                       if($(event.target).hasClass('fancytree-node')){
                          node =  $(event.target);
                       }else{
                          node = $(event.target).parents('.fancytree-node');
                       }
                       var ele = $(node).find('.svs-contextmenu3');
                       ele.css('display','inline-block');//.css('visibility','visible');
                       
                       //highlight in preview
                       var dty_ID = $(node).find('span[data-dtid]').attr('data-dtid');
                       that.previewEditor.find('div[data-dtid]').removeClass('ui-state-active');
                       if(dty_ID>0)
                       that.previewEditor.find('div[data-dtid="'+dty_ID+'"]').addClass('ui-state-active');
                       
                   }
               );               
               $(item).mouseleave(
                   _onmouseexit
               );
           }
    },
    
    //
    // init popup menu for repeatability and requirement
    //
    _initMenu: function(name){

        var that = this;
        
        var menu_content = '';
        if(name=='req'){
            menu_content = '<li><a href="#" data-req="required" class="required">required</a></li>'
            +'<li><a href="#" data-req="recommended" class="recommended">recommended</a></li>'
            +'<li><a href="#" data-req="optional">optional</a></li>'
            +'<li><a href="#" data-req="forbidden" class="forbidden">hidden</a></li>';
            
        }else if(name=='rep'){
            menu_content = '<li><a href="#" data-rep="1">single</a></li>'
            +'<li><a href="#" data-rep="0">repeatable</a></li>'
            +'<li><a href="#" data-rep="2">limited 2</a></li>'
            +'<li><a href="#" data-rep="3">limited 3</a></li>'
            +'<li><a href="#" data-rep="5">limited 5</a></li>'
            +'<li><a href="#" data-rep="10">limited 10</a></li>';
        }

        // Load content for all menus except Database when user is logged out
        this.menues['menu_'+name] = $('<ul>'+menu_content+'</ul>')
            .addClass('menu-or-popup')
            .hide()
            .css({'position':'absolute', 'padding':'5px'})
            .menu({select: function(event, ui){
                    var tree = that._treeview.fancytree("getTree");
                    var node = tree.getActiveNode();
                    if(node){
                        
                        var fields = {
                            rst_ID: node.key,
                            rst_RecTypeID: that.options.rty_ID,
                            rst_DetailTypeID: node.key}
                        
                        var ele = ui.item.find('a');
                        newVal = ele.attr('data-rep');
                        if(newVal>=0){
                            fieldName = 'rst_MaxValues';
                            //fields['rst_Repeatability']
                        }else{
                            newVal = ele.attr('data-req');
                            
                            if(node.extraClasses) {
                                $(node.li).find('span.fancytree-node').removeClass(node.extraClasses);      
                            }
                            node.extraClasses = newVal;
                            $(node.li).find('span.fancytree-node').addClass(newVal);
                            node.setActive( false );
                            fieldName = 'rst_RequirementType';
                        }
                            
                        fields[fieldName] = newVal;

                        that._saveEditAndClose(fields, function( recID, fields ){
                            
                            //update only rectype or maxval field
                            that._cachedRecordset.setFldById(recID, fieldName, newVal);
                            
                            that._showRecordEditorPreview();
                            
                        });
                    }
                    $('.menu-or-popup').hide();
                    return false; 
            }})
            .appendTo(this.element);

        /*
        this.menues['btn_'+name] = $('<li>')
            .css({'padding-right':'1em'})
            .append(link)
            .appendTo( parentdiv?parentdiv:this.divMainMenuItems );
        this._on( this.menues['btn_'+name], {
            mouseenter : function(){_show(this.menues['menu_'+name], this.menues['btn_'+name])},
            mouseleave : function(){_hide(this.menues['menu_'+name])}
        });
        */
        this._on( this.menues['menu_'+name], {
            //mouseenter : function(){_show(this.menues['menu_'+name], this.menues['btn_'+name])},
            mouseleave : function(){this._hideMenu(this.menues['menu_'+name])}
        });

    },
    
        //show hide function
    _hideMenu: function(ele) {
            this._menuTimeoutId = setTimeout(function() {
                $( ele ).hide();
                }, 800);
            //$( ele ).delay(800).hide();
    },
    
    _showMenu: function(ele, parent) {
            clearTimeout(this._menuTimeoutId);
            
            $('.menu-or-popup').hide(); //hide other
            var menu = $( ele )
            //.css('width', this.btn_user.width())
            .show()
            .position({my: "left-2 top", at: "left top", of: parent });
            //$( document ).one( "click", function() { menu.hide(); });
            return false;
    },
    
    
    //----------------------
    // list view is not visbile - we show everything in treeview
    //
    //
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return recordset.fld(record, fldname);
        }
        function fld2(fldname, col_width){
            swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+window.hWin.HEURIST4.util.htmlEscape(fld(fldname))+'</div>';
        }
        
        var is_narrow = true;//(this.options.edit_mode=='inline');
        
        var recID   = fld('rst_ID');
        
        var recTitle = fld2('rst_ID','4em')
                + fld2('rst_DisplayName','14em')
                + ' ('+$Db.baseFieldType[$Db.dty(recID, 'dty_Type')]+')';

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID
                    +'" style="height:'+(is_narrow?'1.3':'2.5')+'em">'
                    + '<div class="recordTitle" style="left:24px">'
                    + recTitle + '</div>';
        
        // add edit/remove action buttons
        //@todo we have _rendererActionButton and _defineActionButton - remove ?
        //current user is admin of database managers
        if(this.options.select_mode=='manager' && this.options.edit_mode=='popup' && window.hWin.HAPI4.is_admin()){
                
            html = html + '<div class="rec_actions user-list" style="top:4px;width:60px;">'
                    + '<div title="Click to edit field" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;&nbsp;';
               if(true){ //not 
                    html = html      
                    + '<div title="Click to delete field" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                    + '</div>';    
               }   
             html = html + '</div>'                   
        }
        
        if(false && this.options.edit_mode=='popup'){
                //+ (showActionInList?this._rendererActionButton('edit'):'');
            html = html
            + this._defineActionButton({key:'edit',label:'Edit', title:'', icon:'ui-icon-pencil'}, null,'icon_text')
            + this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-minus'}, null,'icon_text');

        }
        html = html + '</div>'; //close recordDiv
        return html;
        
    },
    
    updateRecordList: function( event, data ){
        this._super(event, data);
        this.selectRecordInRecordset();
    },
        
    //
    // can remove group with assigned fields
    //     
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            var that = this;
            
            var rst_ID = this._currentEditID;
            if(rst_ID.indexOf('.')>0){
                rst_ID = rst_ID.split('.')[1];
            }
            
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete field "'
                + window.hWin.HEURIST4.util.htmlEscape(this._cachedRecordset.fld(rst_ID, 'rst_DisplayName'))
                +'"?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'},
                {default_palette_class:this.options.default_palette_class});        
        }
        
    },
    
    //
    // set visibility of buttons on toolbar (depends on isModified)
    //
    onEditFormChange: function( changed_element ){
        //this._super(changed_element);
            
        //show hide buttons in treeview
        var isEditOpen = this.editForm.is(':visible');
        
        this.editForm.find('#btnCloseEditor_rts').css('display', 
                (isEditOpen)?'block':'none');
                
        //show/hide action buttons in tree
        this._treeview.find('.svs-contextmenu3').css('visibility', isEditOpen?'hidden':'visible' );
        if(!isEditOpen){
            //deactivate node - add action buttons
            var tree = this._treeview.fancytree('getTree');
            var node = tree.getActiveNode();
            if(node && node.key!=this._currentEditID){
                node.setActive(false);  
            } 
        }

        
        var isChanged = this.editForm.is(':visible') 
                        && this._editing && this._editing.isModified();
        this.editForm.find('#btnRecSaveAndClose_rts').css('display', 
                (isChanged)?'block':'none');
            
        if(this._toolbar){
            this._toolbar.find('#btnRecPreview_rts').css('display', 
                    (isChanged)?'none':'block');
        }
            
    },  
    
    //
    // special case when rts editor is placed over record editor (manageRecords)
    // in this method it hides native record toolbar and replace it with rts toolbar
    //
    toolbarOverRecordEditor: function( re_toolbar ){
        
        if(re_toolbar){ //replace
        
            //hide native record toolbar
            re_toolbar.find('.ui-dialog-buttonset').hide();
            
            var btn_array = this._getEditDialogButtons();
            
            this._toolbar = re_toolbar;
            var btn_div = $('<div>').addClass('ui-dialog-buttonset rts_editor').appendTo(this._toolbar);
            for(var idx in btn_array){
                this._defineActionButton2(btn_array[idx], btn_div);
            }
        
        }else{ //restore record edit toolbar
            re_toolbar.find('.rts_editor').remove();
            re_toolbar.find('.ui-dialog-buttonset').show();
        }
        
    },
    
    //
    // array of buttons for toolbar
    //  
    _getEditDialogButtons: function(){
                                    
            var that = this;   

            var btns = [                       
                {text:window.hWin.HR('Refresh Preview'),
                    css:{'float':'left',display:'block'}, id:'btnRecPreview_rts',
                    click: function() { that._showRecordEditorPreview(); }},
                    
                {text:window.hWin.HR(this.options.external_toolbar?'Back to Whole Form':'Close Dialog'), 
                      css:{'float':'right'}, id:'btnClose_rts',
                      click: function() { 
                          that.closeDialog(); 
                      }},
            ];
            
            return btns;
    },    
    
    //
    // Opens defDetailTypes editor
    // arg1 - dty_ID (if -1 - add/select new field)
    // arg1 - not defined or not integer - use current 
    // arg2 - add new field after dty_ID 
    // allow_proceed - flag to ask user first
    //
    showBaseFieldEditor: function( arg1, arg2, allow_proceed ){
        
        var that = this;
        
        if(allow_proceed!==true){
            this._allowActionIfModified( function(){ 
                that.showBaseFieldEditor( arg1, arg2, true );                            
            } );
            return;
        }
        
        var dtyID;
        if(isNaN(parseInt(arg1))){ //event - use curent 
            dtyID = this._currentEditID;
            if(!(dtyID>0)) return;
        }else{
            //edit spesific
            dtyID = arg1;
        }
        
        var popup_options = {
                select_mode: 'manager',
                edit_mode: 'editonly', //only edit form is visible, list is hidden
                rec_ID: (dtyID>0)?dtyID:-1
            };
        
        var that = this;
        
        if(!(dtyID>0)){ //new field
        
            var after_dty_ID = 0;
            if(arg2>0){ //add after
                this._lockDefaultEdit = true;
                var tree = this._treeview.fancytree("getTree");
                node = tree.getNodeByKey(arg2);
                if(node) node.setActive();
                after_dty_ID = arg2;
            }
            if(!(after_dty_ID>=0)) after_dty_ID = 0; //add as first
            
            //add new field to this record type structure
            popup_options['title'] = 'Select or Define new field';
            popup_options['newFieldForRtyID'] = this.options.rty_ID;
            popup_options['selectOnSave'] = true;
            popup_options['onselect'] = function(event, res)
            {
                //update recordset
                //user can add new record types while add new field 
                //need refresh structure - obtain from rst_Index again
                that._cachedRecordset = $Db.rst(that.options.rty_ID);  //from  rst_Index
                
                if(res && res.selection){
                    if(window.hWin.HEURIST4.util.isArrayNotEmpty(res.selection)){
                        var dty_ID = res.selection[0];
                        that.addNewFieldToStructure(dty_ID, after_dty_ID, res.rst_fields);
                    }else
                    if(window.hWin.HEURIST4.util.isRecordSet(res.selection)){
                        var recordset = res.selection;
                        var record = recordset.getFirstRecord();
                        //console.log(record);                    
                    }
                }
            };
            that._lockDefaultEdit = false;
        }else{
            popup_options['onClose'] = function(){
                //update recordset
                //user can add new record types while add new field 
                //need refresh structure - obtain from rst_Index again
                that._cachedRecordset = $Db.rst(that.options.rty_ID);  //from  rst_Index
        

                //reload formlet after edit
                that._initEditForm_step3( dtyID );
                //make it changed
                that._editing.setModified(true);
                
            }
        }
        
        
        window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypes', popup_options);
    },
    
    _initEditForm_step3: function( recID ){
        
            if(recID>0){
                var basefield = $Db.dty(recID);
                if(basefield){
                    this._cachedRecordset.setFldById(recID, 'dty_Type', basefield['dty_Type']);
                    this._cachedRecordset.setFldById(recID, 'rst_FilteredJsonTermIDTree', basefield['dty_JsonTermIDTree']);
                    this._cachedRecordset.setFldById(recID, 'rst_PtrFilteredIDs', basefield['dty_PtrTargetRectypeIDs']);
                }
            }
                            
            this._super( recID );
    },
    
    //
    // add field to structure
    //
    addNewFieldToStructure: function(dty_ID, after_dty_ID, rst_fields){
        
        if(this._cachedRecordset.getById(dty_ID) ){
            window.hWin.HEURIST4.msg.showMsgFlash('Such field already exists in structure');
            return;
        }
        
        if(window.hWin.HEURIST4.util.isnull(rst_fields) || !$.isPlainObject(rst_fields)){
            rst_fields = {};
        }
        
        //check that this field is not exists in structure
        
        var fields = {
            rst_ID: dty_ID,
            rst_RecTypeID: this.options.rty_ID,
            rst_DisplayOrder: "001",
            rst_DetailTypeID: dty_ID,
            //rst_Modified: "2020-03-16 15:31:23"
            rst_DisplayName: $Db.dty(dty_ID,'dty_Name'),
            rst_DisplayHelpText: $Db.dty(dty_ID,'dty_HelpText'),
            rst_RequirementType: rst_fields['rst_RequirementType'] ?rst_fields['rst_RequirementType']:'optional',
            //rst_Repeatability: 'single',
            rst_MaxValues: (rst_fields['rst_MaxValues']>=0) ?rst_fields['rst_MaxValues']:'1',  //0 repeatable
            rst_DisplayWidth: rst_fields['rst_DisplayWidth'] ?rst_fields['rst_DisplayWidth']:'100',  
            /*
            dty_Type: dtFields[fi['dty_Type']]
            rst_DisplayHeight: "3"
            rst_TermPreview: ""
            rst_FilteredJsonTermIDTree: "497"
            dty_TermIDTreeNonSelectableIDs: ""
            rst_PtrFilteredIDs: ""
            rst_PointerMode: "addorbrowse"
            rst_PointerBrowseFilter: ""
            rst_CreateChildIfRecPtr: "0"
            rst_DefaultValue: ""
            rst_SeparatorType: ""
            rst_DisplayExtendedDescription: "Please provide an extended description for display on rollover ..."
            rst_Status: "open"
            rst_NonOwnerVisibility: "viewable"
            rst_LocallyModified: "1"    
            */
        };
        
        if($Db.dty(dty_ID,'dty_Type')=='separator'){
            fields['rst_SeparatorType'] = 'tabs';    
        }
        
/*            
            refreshEntityData('defRecStructure',function(){
                
            });
*/            
        
        
        var that = this;
        this._saveEditAndClose(fields, function( recID, fields ){
            
            //get full field info to update local definitions
            var request = {
                'a'          : 'search',
                'entity'     : that.options.entity.entityName,
                'request_id' : window.hWin.HEURIST4.util.random(),
                'details'    : 'list', //'structure',
                'rst_RecTypeID': that.options.rty_ID,
                'rst_DetailTypeID':dty_ID
                };
            
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    var recset = hRecordSet(response.data);
                    fields = recset.getRecord( response.data.order[0] ); //get as JSON
                    fields['rst_ID'] = recID;

                    that._cachedRecordset.setRecord(recID, fields);
                    
                    var tree = that._treeview.fancytree("getTree");
                    var parentnode;
                    if(after_dty_ID>0){
                        parentnode = tree.getNodeByKey(after_dty_ID);
                    }else{
                        parentnode = tree.rootNode; //getRootNode();//getActiveNode();
                    }
                    if(!parentnode){
console.log('No active tree node!!!!')                      
                        return;  
                    } 
                    
                    //add new node to tree
                    parentnode.addNode({key:recID}, 
                            (parentnode.isRootNode() || parentnode.folder==true)
                                                    ?'firstChild':'after');
                    if(parentnode.folder){
                        parentnode.setExpanded(true);
                    }
                    
                    that._stillNeedUpdateForRecID = 0;
                    
                    //update 1)recordset, 2) defintions 3)treeview 
                    that._afterSaveEventHandler(recID, fields);
                    
                    //
                    that._show_optional = (fields['rst_RequirementType']=='optional');
                    //
                    that._open_formlet_for_recID = recID;
                    //save order  it calls _showRecordEditorPreview to update preview and open formlet field editor
                    that._saveRtStructureTree();
            
                }else{
                    
                }
            });
            
            
        });

    },
    
    //
    // show record editor form - it reflects the last changes of rt structure
    // recID - dty_ID - field that was saved
    //
    _showRecordEditorPreview: function( hard_reload ) {
        
        //hide editor - show and updated preview
        if(this.previewEditor){
            
            this._closeFormlet();

            var that = this.previewEditor;
            var that2 = this;
            
            if(!this.previewEditor.manageRecords('instance')){ //OLD VERSION
                // record editor not defined - create new one
                // this is old option when record editor is slave to rts editor
                
                var options = {
                        rts_editor: this.element,
                        select_mode: 'manager',
                        edit_mode: 'editonly',
                        in_popup_dialog: false,
                        allowAdminToolbar: false, //if false it hides attribute and titlemask links
                        rec_ID: this.options.rec_ID_sample,
                        new_record_params: {RecTypeID: this.options.rty_ID},
                        layout_mode:'<div class="ent_wrapper editor">'
                            + '<div class="ent_content_full recordList"  style="display:none;"/>'

                            //+ '<div class="ent_header editHeader"></div>'
                            + '<div class="editFormDialog ent_content" style="bottom:0px">'
                                    + '<div class="ui-layout-center"><div class="editForm"/></div>'
                                    + '<div class="ui-layout-east"><div class="editFormSummary">....</div></div>'
                            + '</div>'
                        +'</div>',
                    onInitFinished:function(){
                            //load record in preview
                            that.manageRecords('addEditRecord', this.options.rec_ID); //call widget method
                    },
                    onInitEditForm:function(){
                        if(that2._show_optional){
                            that.manageRecords('showOptionalFieds', true);    
                        }
                        if(that2._open_formlet_for_recID>0){
                            that2.editField( that2._open_formlet_for_recID );
                        }
                        that2._show_optional = false;
                        that2._open_formlet_for_recID = -1;
                    }
                }                
                
                this.previewEditor.manageRecords( options ).addClass('ui-widget');
            }else{
                if(this.options.external_preview){
                    this.previewEditor.manageRecords('option','onInitEditForm',function(){
                            if(that2._show_optional){
                                that.manageRecords('showOptionalFieds', true);    
                            }
                            if(that2._open_formlet_for_recID>0){
                                
                                var sType = $Db.dty(that2._open_formlet_for_recID, 'dty_Type');
                                var isSep = (sType=='separator');
                                
                                if(isSep ||
                                  window.hWin.HAPI4.get_prefs_def('edit_rts_open_formlet_after_add',0)==1){
                                    that2._clear_title_for_separator = isSep; // clear title for separator
                                    that2.editField( that2._open_formlet_for_recID); 
                                }
                            }
                            that2._show_optional = false;
                            that2._open_formlet_for_recID = -1;
                        });

                    this.previewEditor.manageRecords('reloadEditForm', true);//hard_reload);    
                }else{
                    this.previewEditor.manageRecords('reloadEditForm');    
                }
                
            }
        }
    },
    
     
    //-----
    // for formlet
    // assign event listener for rst_Repeatability and dty_Type fields
    // show formlet
    //
    _afterInitEditForm: function(){

        var that = this;
            
        if(this.previewEditor)
        {
            if(true || this.options.showEditorInline){
                
                //place rts editor into record edit form
                var isHeader = false;
                var ed_ele = this.previewEditor.find('fieldset[data-dtid='+this._currentEditID+']');
                
                if(ed_ele.length==0){
                    ed_ele = this.previewEditor.find('div[data-dtid='+this._currentEditID+']');
                }else{
                    isHeader = true;
                }
                    
                if(ed_ele.length==0){ //popup edit  not used anymore
                    if(!this.editForm.hasClass('ent_content_full')){
                        //put editForm back to original container
                        this.editForm
                            .css({'margin-left':'0px'})
                            .addClass('ent_content_full')
                            .appendTo(this.previewEditor.parent());
                    }
                    //open in popup editor
                    this.options.edit_height = 250;
                    this.showEditFormDialog(false);
                    this._edit_dialog.dialog('option','close', function(){
                        that._closeFormlet();
                    });
                }else{
                
                    this.editForm
                        .css({'margin-left':'209px'})
                        .removeClass('ent_content_full');
                    
                    var ed_cont;
                    if(this.editForm.parent().hasClass('editor-container')){
                        //already created
                        ed_cont = this.editForm.parent();
                    }else{
                        //create new table div for editor
                        ed_cont = $('<div class="editor-container" style="display:table">');
                        this.editForm.appendTo(ed_cont);
                    }
                    
                    if(isHeader){
                        //insert as fist child for header
                        ed_ele.prepend( ed_cont );
                        //ed_cont.appendTo(ed_ele); //prepend
                    }else{
                        ed_cont.insertAfter(ed_ele);    
                    }
                    //for empty fieldsets
                    if(!this.editForm.parents('fieldset:first').is(':visible')){
                        this.editForm.parents('fieldset:first').show();
                    }
                    
                    //expand accordion tab
                    var ele = this.editForm.parents('.ui-accordion:first');
                    if(ele.length>0){
                        ele.accordion( 'option', 'active', 0);
                    }else{
                        ele = this.editForm.parents('.ui-tabs');
                        if(ele.length>0){
                            var tabIndex = this.editForm.parents('fieldset:first').attr('data-tabindex');
                            ele.tabs( 'option', 'active', tabIndex);
                        }
                    }
                    
                    //adjust preview editor position
                    var ele_ed = this.previewEditor.find('.editForm'); //editFormDialog
                    setTimeout(function(){
                        ele_ed.scrollTop(0);
                        var top = $(ed_cont).position().top - 60;
                        
                        var ele = that.editForm.parents('.ui-tabs');
                        if(ele.length>0){
                            top = top + $(ele).position().top;
                        }
                        ele_ed.scrollTop(top);
                        
                    },200); //without timeout preview form scrolls to kept position
                    
                }
                this.editForm.show();
                this._editing.setFocus();
                
                //this.editForm.position({my:'left top', at:'left bottom', of:ed_ele}).show();
            }else{
                this.previewEditor.hide();
            }
        }
            
        //----------
        // hint with base field details
        var baseFieldDetails = 'ID: '+this._currentEditID+'   Code: '+$Db.getConceptID('dty',this._currentEditID);        
        var dt_fields = $Db.dty(this._currentEditID);
        if(dt_fields){
            var s = dt_fields['dty_HelpText'];
            var s1 = '', k = 0;
            if(s){
                s = s.trim();
                while (k<s.length && k<500){
                    s1 = s1 + s.substring(k,k+60) + "\n";
                    k = k + 60;
                }
            }
            var s2 = ''; 
            s = dt_fields['dty_ExtendedDescription'];
            if(s){
                s = s.trim();
                k = 0;
                while (k<s.length && k<500){
                    s2 = s2 + s.substring(k,k+60) + "\n";
                    k = k + 60;
                }
            }

            baseFieldDetails +=
            (dt_fields['dty_ConceptID']?("\n\nConcept code: "+dt_fields['dty_ConceptID']):'')+
            "\n\nBase field type: "+dt_fields['dty_Name']+"\n\n"+
            ((s1!='')?("Help: "+s1+"\n"):'')+
            ((s2!='')?("Ext: "+s2+"\n"):'');
        }
        
        //----------------
        var edit_ele = this._editing.getFieldByName('rst_CreateChildIfRecPtr');

        var help_button = $('<span style="padding-left:20px;color:gray;cursor:pointer" class="ui-icon ui-icon-circle-info"/>')
                .appendTo(edit_ele.find('.input-div'));
        window.hWin.HEURIST4.ui.initHelper( {button:help_button, title:'Creation of records as children', 
                    url:window.hWin.HAPI4.baseURL+'context_help/parent_child_instructions.html #content',
                    no_init:true} );                
        
            
        edit_ele= this._editing.getFieldByName('rst_Repeatability');
        if(edit_ele){
            
            edit_ele.editing_input('option','change', function(){
                var res = this.getValues()[0];
                if(res=='single' || res=='repeatable'){
                    res = (res=='repeatable')?0:1;
                    that._editing.getFieldByName('rst_MaxValues').hide();
                }else if(res=='limited'){
                    res = 2;
                    that._editing.getFieldByName('rst_MaxValues').show();
                }
                that._editing.setFieldValueByName('rst_MaxValues', res, true);
                that.onEditFormChange(); //trigger change   
            });
        }
        

        edit_ele= this._editing.getFieldByName('rst_CreateChildIfRecPtr');
        if(edit_ele){
            edit_ele.editing_input('option','change', function(){
                //var input = this.getInputs()[0];
                that.onCreateChildIfRecPtr( this );  
            }); 
        }
        
        
        //fill init values of virtual fields
        //add lister for dty_Type field to show hide these fields
        //var elements = this._editing.getInputs('dty_Type');
        var edit_ele = this._editing.getFieldByName('rst_PtrFilteredIDs');
        if(edit_ele){
            edit_ele.editing_input('option','showclear_button',false);
        }
        edit_ele = this._editing.getFieldByName('dty_Type');
        if(edit_ele){
            edit_ele.editing_input('option','showclear_button',false);
            var ele = this._editing.getInputs('dty_Type')
            window.hWin.HEURIST4.util.setDisabled(ele, true);
            this._onDetailTypeChange();
        }
        
        
        
        //add show explanation checkbox
        var ishelp_on = (this.usrPreferences['help_on']==true || this.usrPreferences['help_on']=='true');
        var ele = $('<div><label style="float:right;padding-right:30px"><input type="checkbox" '
                        +(ishelp_on?'checked':'')+'/>show explanations</label></div>').prependTo(this.editForm);
        
        this._on( ele.find('input'), {change: function( event){
            var ishelp_on = $(event.target).is(':checked');
            this.usrPreferences['help_on'] = ishelp_on;
            window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper3');
        }});
        this.editForm.find('.heurist-helper1').removeClass('heurist-helper1').addClass('heurist-helper3');
        window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper3');
        
        
        var bottom_div = $('<div style="width:100%;min-height:26px">').appendTo(this.editForm);
        
        var  dt_type = this._editing.getValue('dty_Type')[0];

        if(dt_type=='separator'){
            
            var sep_type;
            if(false){  //HARDCODE for Casey databases  && window.hWin.HAPI4.database.indexOf('Casey')>=0
                sep_type = 'group';
                edit_ele = this._editing.getFieldByName('rst_SeparatorType');
                edit_ele.hide();
            }else{
                sep_type = this._editing.getValue('rst_DefaultValue')[0]; //take from db
                if(!(sep_type=='accordion' || sep_type=='tabs' || sep_type=='tabs_new' || sep_type=='expanded')){
                    sep_type = 'group';
                }
            }
            this._editing.setFieldValueByName( 'rst_SeparatorType', sep_type, false ); //assign to entry selector

            sep_type = this._editing.getValue('rst_RequirementType')[0];
            sep_type = (sep_type=='forbidden')?sep_type:'optional';
            this._editing.setFieldValueByName( 'rst_SeparatorRequirementType', sep_type, false );
            
            
            //clear title to force change default one
            if(this._clear_title_for_separator){
                this._editing.setFieldValueByName( 'rst_DisplayName', '', true );
                //sep_type = 'tabs'; //default for new separator    
                this._editing.setFocus();
                this._editing.setModified(true);
            }
            this._clear_title_for_separator = false;

            this.editForm.find('.ui-accordion').hide();
        }else{
            
            var s = '';
            if (dt_type=='enum' || dt_type=='relmarker' || dt_type=='resource'){
                s = 'To change';
                if (dt_type=='enum' || dt_type=='relmarker'){
                    s += ' vocabulary';    
                }
                if (dt_type=='relmarker' || dt_type=='resource'){
                    s += (dt_type=='relmarker'?' or ':' ')+'target entity types';    
                }
                s += ': ';
            }
            
            var ele = $('<div style="font-style:italic;padding:10px;display:inline-block">'
                +s+'<a href="#">Edit base field definitions</a></div>');
            if(s==''){
                ele.appendTo(bottom_div); //usual field
            }else{
                //enum,relmarker,resource
                var edit_ele = this._editing.getFieldByName('rst_FilteredJsonTermIDTree');
                ele.css({'border-top':'1px lightgray solid','padding':'10px 0px 0px',margin:'10px 0 0 126px'});
                ele.insertBefore(edit_ele);                
            }
            this._on(ele.find('a'),{click: this.showBaseFieldEditor}); 
            
            
            $('<span style="padding-left:40px;color:gray;cursor:pointer">ID: '
                +this._currentEditID+' <span class="ui-icon ui-icon-circle-info"/></span>')
                .attr('title', baseFieldDetails)
                .appendTo(bottom_div);
                
                
            var edit_ele= this._editing.getFieldByName('rst_DisplayWidth');
            if(edit_ele){
                edit_ele.editing_input('option','change', function(){
                    
                    var res = this.getValues()[0];
                    if(res>120){
                        window.hWin.HEURIST4.msg.showMsgDlg(
                        'This field width might result in the field being wider than the screen. '
                        +'Click OK for this width, Cancel to set to 120 (conservative setting).',
                        function(){
                            that._editing.setFieldValueByName('rst_DisplayWidth', 120, true);        
                            that.onEditFormChange(); //trigger change   
                        }, 
                        {title:'Warning',yes:'Cancel',no:'OK'},
                        {default_palette_class:that.options.default_palette_class});  
                    }else{
                        that.onEditFormChange(); //trigger change
                    }
                }); 
            }
                
        }

        var btnCancel = $('<button>').attr('id', 'btnCloseEditor_rts')
                .button({label:window.hWin.HR('Close')})
                .css({'margin-right':'1em','float':'right',display:'none','margin-top':'2px'})
                .appendTo(bottom_div);

        var btnSave = $('<button>').attr('id', 'btnRecSaveAndClose_rts')
                .button({label:window.hWin.HR('Save')})
                .css({'font-weight':'bold','float':'right',display:'none','margin-top':'2px','margin-right':'6px'})
                .appendTo(bottom_div);
            
        this._on( btnCancel,{click: function() { 


                        if(that._editing && that._editing.isModified() && that._currentEditID!=null){
                            var $dlg, buttons = {};
                            buttons['Save'] = function(){ that._saveEditAndClose(null, 'close'); $dlg.dialog('close'); }; 
                            buttons['Ignore and close'] = function(){ 
                                    that._closeFormlet(); 
                                    $dlg.dialog('close'); 
                            };

                            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                                'You have made changes to the data. Click "Save" otherwise all changes will be lost.',
                                buttons,
                                {title:'Confirm',yes:'Save',no:'Ignore and close'},
                                {default_palette_class:this.options.default_palette_class});
                        }else{
                            that._closeFormlet();
                        }
                }});
                
        this._on( btnSave, {click: function() { that._saveEditAndClose( null, 'close' ); }});
        
        
        this._super();
        
        //change default styles
        this.editForm.find('.text').css({background: '#ECF1FB'});
        this.editForm.find('.entity_selector').css({background: '#ECF1FB'});
        this.editForm.find('.ui-selectmenu-button').css({background: '#ECF1FB'});
        this.editForm.find('.required > label').css({color: '#6A7C99'});

    },    
    
    
    //
    //
    //
    _closeFormlet: function() {
                            
        this._currentEditID = null;
        
        if(this._edit_dialog && this._edit_dialog.dialog('instance')){
            this._edit_dialog.dialog('close');
            if(this._edit_dialog) this._edit_dialog.dialog('destroy');
            this._edit_dialog = null;
        }
        
        this.editForm.hide();
        if(!this.options.external_preview){
            this.previewEditor.show();
        }
        
        this.previewEditor.manageRecords('setDisabledEditForm', false);
        this.onEditFormChange(); //after close
    },  
                      
    
    //
    // Event listener for dty_Type - shows/hides dependent fields
    //
    _onDetailTypeChange: function()
    {
                       var dt_type = this._editing.getValue('dty_Type')[0]
                       
                       //hide all virtual 
                       var virtual_fields = this._editing.getFieldByValue("dty_Role","virtual");
                       for(var idx in virtual_fields){
                           $(virtual_fields[idx]).hide();
                       }
                       
                       //hide all 
                       var depended_fields = this._editing.getFieldByValue("rst_Class","[not empty]");
                       for(var idx in depended_fields){
                           $(depended_fields[idx]).hide();
                       }
                       //show specific
                       depended_fields = this._editing.getFieldByClass( (dt_type=='separator')?'group_separator':dt_type );
                       for(var idx in depended_fields){
                           $(depended_fields[idx]).show();
                       }
                       
                       if(dt_type=='separator'){
                           this._editing.getFieldByName('rst_RequirementType').hide();
                       }else{

                           this._editing.getFieldByName('dty_Type').show();
                           
                           this._editing.getFieldByName('rst_RequirementType').show();
                           this._editing.getFieldByName('rst_Repeatability').show();

                           if(dt_type=='enum' || dt_type=='relmarker' || dt_type=='relationtype'){
                               this._recreateTermsPreviewSelector();
                           }
                           if(dt_type=='relmarker' || dt_type=='resource'){
                               this._recreateResourceSelector();
                           }

                           var maxval = parseInt(this._editing.getValue('rst_MaxValues')[0]);
                           var res = 'repeatable';
                           if(maxval==1){
                               res = 'single';
                           }else if(maxval>1){
                               res = 'limited';
                           }else{
                               this._editing.setFieldValueByName('rst_MaxValues', 0, false);
                           }
                           
                           this._editing.setFieldValueByName('rst_Repeatability', res, false);
                           if(maxval>1){
                               this._editing.getFieldByName('rst_MaxValues').show();
                           }else{
                               this._editing.getFieldByName('rst_MaxValues').hide();
                           }
                           
                           if(dt_type=='freetext' || dt_type=='integer' || dt_type=='float'){
                               this._recreateDefaultValue();
                           }

                       }
                       
                    
    },    
    
    //
    //
    //
    _recreateTermsPreviewSelector: function(){
        
        var allTerms = this._editing.getValue('rst_FilteredJsonTermIDTree')[0];
        var disTerms = null;
        var term_type = this._editing.getValue('dty_Type')[0];//'enum', 'relmarker' or 'relationtype'
        var defval = '';
        if(term_type=='relationtype'){
            allTerms = 'relation';
        }

        //remove old content
        //var edit_ele = this._editing.getFieldByName('rst_TermPreview');
        //edit_ele.find('.input-div').empty();

        if(!window.hWin.HEURIST4.util.isempty(allTerms)) {

            disTerms = this._editing.getValue('dty_TermIDTreeNonSelectableIDs')[0];
            
            term_type = this._editing.getValue('dty_Type')[0];
            if(term_type!="enum"){
                term_type="relation";
            }
        }

        var defval = this._editing.getValue('rst_DefaultValue')[0];
        if(!window.hWin.HEURIST4.util.isempty(allTerms) && defval){
            this._editing.setFieldValueByName('rst_DefaultValue', '', false);
        }
        var ele = this._editing.getFieldByName('rst_TermPreview');
        //ele.editing_input('fset','dty_Type',(term_type!='relation')?'enum':'relationtype');
        ele.editing_input('fset','rst_FilteredJsonTermIDTree', allTerms);
        ele.editing_input('fset','rst_TermIDTreeNonSelectableIDs', disTerms);
        this._editing.setFieldValueByName('rst_TermPreview', defval, false); //recreates

        
        this._editing.setFieldValueByName('rst_FilteredJsonTermIDTree', $Db.trm(allTerms,'trm_Label'), false); //recreates
        
        /*
        var ele = this._editing.getFieldByName('rst_DefaultValue_enum');
        var defval = this._editing.getValue('rst_DefaultValue_enum')[0];
        ele.editing_input('fset','dty_Type',term_type!='relation'?'enum':'relationtype');
        ele.editing_input('fset','rst_FilteredJsonTermIDTree', allTerms);
        ele.editing_input('fset','rst_TermIDTreeNonSelectableIDs', disTerms);
        ele.editing_input('setValue',[defval]);
        */
    },
    
    //
    //
    //
    _recreateResourceSelector: function(){
        
        var ptrIds = this._editing.getValue('rst_PtrFilteredIDs')[0];
        
        //disable
        var edit_ele = this._editing.getFieldByName('rst_PtrFilteredIDs');
        if(edit_ele){
            //edit_ele.editing_input('option','showclear_button',false);
            var ele = this._editing.getInputs('rst_PtrFilteredIDs')
            window.hWin.HEURIST4.util.setDisabled(ele, true);
        }
        
        
        var defval = this._editing.getValue('rst_DefaultValue')[0];
        if(!window.hWin.HEURIST4.util.isempty(ptrIds) && defval){
            this._editing.setFieldValueByName('rst_DefaultValue', '', false);
        }
        var ele = this._editing.getFieldByName('rst_DefaultValue_resource');
        ele.editing_input('fset','rst_PtrFilteredIDs', ptrIds);
        ele.editing_input('fset','rst_PointerMode', 'browseonly');
        this._editing.setFieldValueByName('rst_DefaultValue_resource', defval, false); //recreates
        
    },
    
    //
    //
    //
    _recreateDefaultValue: function(){
        
        var defval = this._editing.getValue('rst_DefaultValue')[0];
        var ele = this._editing.getFieldByName('rst_DefaultValue_inc');          
        ele = ele.find('.input-div');
        //remove old content
        ele.empty();
            
            
        //if(this.defval_container) return; //already inited
        this.defval_container = ele;
        
        var is_increment = (defval=='increment_new_values_by_1');
            
        $('<div style="line-height:2ex;padding-top:4px">'
                    +'<input type="radio" value="0" name="defvalType">'  //'+(is_increment?'':'checked="true"')+'
                    +'<input class="text ui-widget-content ui-corner-all" autocomplete="disabled" autocorrect="off" autocapitalize="none" spellcheck="false" style="min-width: 22ex; width: 10ex;">'
                    +'<label style="text-align:left;line-height: 12px;">'
                    +'<input type="radio" value="1" name="defvalType" style="margin-top:0px" '
                    +'>&nbsp;Increment value by 1</label> '   //    +(is_increment?'checked="true"':'')
            +'</div>').appendTo(this.defval_container);
                
            //create event listeneres
            this._on(this.defval_container.find('input[name="defvalType"]'),{change:
                function(event){
                    var ele_inpt = this.defval_container.find('input.text');
                    var is_increment = (this.defval_container.find('input[name="defvalType"]:checked').val()=='1');

                    window.hWin.HEURIST4.util.setDisabled(ele_inpt, is_increment);
                    
                    var res = is_increment ?'increment_new_values_by_1':ele_inpt.val();
                    if(defval!=res){
                        this._editing.setFieldValueByName('rst_DefaultValue', res, true);    
                    }
                    
                    
                    //var ele = this._editing.getFieldByName('rst_DefaultValue');
                    //ele.editing_input( 'setValue',  res); 
                    //this.onEditFormChange();
                    
                }});
            this._on(this.defval_container.find('input.text'),{keyup:function(event){
                var res = this.defval_container.find('input.text').val();
                this._editing.setFieldValueByName('rst_DefaultValue', res, true);    
            }});
                
            
            this.defval_container.find('input[name="defvalType"][value="'+(is_increment?1:0)+'"]').prop('checked',true).change();
            if(!is_increment){
                this.defval_container.find('input.text').val( defval );
            }
            
    },

    
    //
    // trigger to update rst_DisplayOrder
    //
    _saveRtStructureTree: function(){
        
            var recset = this._cachedRecordset;
            var tree = this._treeview.fancytree("getTree");
            var order = 0;
            var that = this;
            var dtyIDs = [];
            var orders = [];
            tree.visit(function(node){
            
                
                
                var dty_ID = node.key;
                recset.setFldById(dty_ID, 'rst_DisplayOrder', order);
                
                dtyIDs.push( dty_ID );
                orders.push( order );
                order++;
            });
            //update order on server
            var request = {};
            request['a']        = 'action'; //batch action
            request['entity']   = this._entityName;
            request['rtyID']    = this.options.rty_ID;
            request['recID']    = dtyIDs;
            request['orders']   = orders;
            request['request_id'] = window.hWin.HEURIST4.util.random();
            
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        that._dragIsAllowed = true;
                        
                        that._showRecordEditorPreview();  
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);      
                    }
                });
        
    },
    
    //
    // for tree rt structure
    // Remove all data excect keys for fields 
    //
    _cleanTreeStructure: function(data){
        
        if($.isArray(data)){
            for(var i=0; i<data.length; i++){
                if(data[i].folder || $.isArray(data[i].children)){
                    this._cleanTreeStructure( data[i].children );        
                }else{
                    if(data[i].title) delete data[i].title;
                    if(data[i].data) delete data[i].data;
                }
                if(!window.hWin.HEURIST4.util.isempty(data[i].expanded)) delete data[i].expanded;
                if(data[i].extraClasses) delete data[i].extraClasses;
            }
        }
    },
    
    //-not used
    _composeTreeItem: function(title, type, req){
        
        if(type=='separator'){
            title = '<span data-dtid="'+dty_ID+'">' + title +'</span>';
        }else{
            title =  '<span style="padding-left:10px;">' + title 
                    + '</span>';
//'<span style="font-size:smaller;"> ('+$Db.baseFieldType[type]+')</span>';
        }
        if(req=='forbidden'){
            title =  title + '<span style="font-size:smaller;text-transform:none;"> (hidden)</span>';
        }
        
        return title;
    },
    
    //-----------------------------------------------------
    //
    // special case for separator field
    // 1. update content of data field in treeview for this separator
    // 2. get treeview.toDict
    // 3. save this json in ExtDescription of field 2-57 ("Header 1") 
    //
    _saveEditAndClose: function( fields, afterAction ){

            var that = this;

            if(window.hWin.HAPI4.is_callserver_in_progress()) {
                //console.log('prevent repeatative call')
                return;   
            }
        
            if(!fields){
                if(this._editing && this._editing.isModified() && this._currentEditID!=null){
                    fields = this._getValidatedValues(); 
                }else{
                    this._closeFormlet();   
                }
                        
            }
            if(fields==null) return; //validation failed

            var treeview = this._treeview.fancytree("getTree");
            var recID = fields['rst_ID'];
            var dt_type = $Db.dty(fields['rst_DetailTypeID'], 'dty_Type');
            
//console.log('SAVE '+fields['rst_DetailTypeID']+'  '+dt_type);
            
            if(dt_type=='enum' || dt_type=='relmarker' || dt_type=='relationtype'){
                fields['rst_DefaultValue'] = fields['rst_TermPreview'];
            }else if(dt_type=='resource'){
                fields['rst_DefaultValue'] = fields['rst_DefaultValue_resource'];
            }else if(dt_type=='separator'){
                fields['rst_DefaultValue'] = fields['rst_SeparatorType'];
                fields['rst_RequirementType'] = fields['rst_SeparatorRequirementType'];
            }else if(dt_type=='freetext' || dt_type=='integer' || dt_type=='float'){                
                //fields['rst_DefaultValue'] = fields['rst_DefaultValue_inc'];
            }
            if(window.hWin.HEURIST4.util.isempty(fields['rst_DisplayOrder'])){
                fields['rst_DisplayOrder'] = '0';
            }
            
            if(fields['rst_DisplayWidth']>255) fields['rst_DisplayWidth'] = 255;
            
            
            this._stillNeedUpdateForRecID = recID;    
            
            if(afterAction=='close'){
                //after save on server - close edit form and refresh preview
                afterAction = function( recID ){
                    that._stillNeedUpdateForRecID = 0;
                    that._afterSaveEventHandler( recID ); //to update definitions and tree
                    that._showRecordEditorPreview();  //refresh 
                };
            }
                
            //save record    
            this._super( fields, afterAction );                
            
    },    
    
    //
    // update tree on save/exit/load other record
    //
    _initEditForm_step2: function( recID ){
 
        if(this._stillNeedUpdateForRecID>0) {
            this._afterSaveEventHandler( this._stillNeedUpdateForRecID );
        }      
        this._super( recID );
    },
    
    //
    //
    //
    closeDialog: function(is_force){
        
        if($.isFunction(this.saveUiPreferences)) this.saveUiPreferences();


        if(this.options.external_toolbar){
            //rts editor is opened from record editor

            if(is_force || this.defaultBeforeClose()){
                if($.isFunction(this.options.onClose)){
                    this.options.onClose.call();
                } 
            }

        }else{
            this._super( is_force )   
        }
    },
    
    //--------------------------------------------------------------------------
    //  
    // update 1) defintions 2)treeview 
    // (recordset is already updated in _saveEditAndClose)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){

        //rare case when edited edit form reload with another record
        var is_usual_way = (!(this._stillNeedUpdateForRecID>0)); 
        this._stillNeedUpdateForRecID = 0;
        //record is already updated in _saveEditAndClose
        //this._super( recID, fieldvalues );
        if(is_usual_way){
            //window.hWin.HEURIST4.msg.showMsgFlash(this.options.entity.entityTitle+' '+window.hWin.HR('has been saved'),500);
        }
            

        //recordset was updated in manageEntity._saveEditAndClose so we pass null
        this.refreshRecset_Definition_TreeNodeItem(recID, null);    
        
        this._dragIsAllowed = true;
    },
    
    //
    //
    //
    refreshRecset_Definition_TreeNodeItem: function( recID, fieldvalues ){
      
            //1. update recordset if fieldvalues are set
            var recset = this.getRecordSet();
            if(fieldvalues!=null){
                recset.setRecord(recID, fieldvalues);  
            }

            var record = recset.getById(recID);
            
            /*if(fieldvalues==null){
                fieldvalues = recset.getRecord(recID);
            }*/

            //2. update $Db
//console.log($Db.rst(this.options.rty_ID, recID));           
      
            //3. refresh treeview
            var tree = this._treeview.fancytree("getTree");
            if(tree){
                    var node = tree.getNodeByKey( recID );
                    if(node) {
                        var sType = $Db.dty(recID,'dty_Type');
                        var isSep = (sType=='separator');
                        var title = record['rst_DisplayName'];
                        var req = record['rst_RequirementType'];
                        if(isSep){
                            title = '<span data-dtid="'+recID+'">' + title +'</span>';
                        }else{
                            title =  '<span  data-dtid="'+recID+'" style="padding-left:10px">' + title 
                                        + '</span>';
//'<span style="font-size:smaller;"> ('+$Db.baseFieldType[sType]+')</span>';
                        }
                        if(req=='forbidden'){
                            title =  title + '<span style="font-size:smaller;text-transform:none;"> (hidden)</span>';
                        }
                        node.setTitle( title );   
                        if(!node.folder){
                            
                            if(node.extraClasses){
                                $(node.li).find('span.fancytree-node').removeClass(node.extraClasses);
                            }
                            
                            node.extraClasses = isSep?'separator2':req;
                            $(node.li).find('span.fancytree-node').addClass( isSep?'separator2':req );
                        }
                        this.__defineActionIcons( $(node.li).find('.fancytree-node') );
//$(node.li).find('.svs-contextmenu3').css('visibility','hidden');
                        
                    }
            }        
        
    },

    
    //  -----------------------------------------------------
    //
    // add new separator/group
    //
    addNewSeparator: function( after_dtid, allow_proceed ){
        
        var that = this;
        
        if(allow_proceed!==true){
            this._allowActionIfModified( function(){ 
                that.addNewSeparator( after_dtid, true );                            
            } );
            return;
        }
        
            if(after_dtid>0){
                this._lockDefaultEdit = true;
                var tree = this._treeview.fancytree("getTree");
                node = tree.getNodeByKey(after_dtid);
                if(node) node.setActive();
            }
            
            this._lockDefaultEdit = false;
            
            
            //THIS IS nearly exact piece of code from editRecStructure
            
            var rty_ID = this.options.rty_ID;
    
            //find seprator field type ID that is not yet added to this record strucuture
            
            var ft_separator_id =  null;
            var ft_separator_group =  $Db.dtg().getOrder()[0]; //add to first group
            
            var all_fields_ids = $Db.rst(rty_ID).getIds();

            var k = 1;
            $Db.dty().each(function(dty_ID, rec){
               if($Db.dty(dty_ID,'dty_Type')=='separator'){
                   k++;
                   if( window.hWin.HEURIST4.util.findArrayIndex( dty_ID, all_fields_ids )<0){
                       ft_separator_group = $Db.dty(dty_ID,'dty_DetailTypeGroupID');
                       ft_separator_id = dty_ID; //not used yet
                       return false;
                   }
                   
               } 
            });
            
            if(!window.hWin.HEURIST4.util.isnull(ft_separator_id)){
                this.addNewFieldToStructure( ft_separator_id, after_dtid );
            }else{ //"not used" separator field type not found - create new one
            
                var fields = {                
                    dty_DetailTypeGroupID: ft_separator_group,
                    dty_ID: -1,
                    dty_Name: 'Header '+k+' - edit the name',
                    dty_HelpText: 'new separator',
                    dty_NonOwnerVisibility: "viewable",
                    dty_Status: "open",
                    dty_Type: "separator"};
                    
                var request = {
                    'a'          : 'save',
                    'entity'     : 'defDetailTypes',
                    'fields'     : fields                     
                    };
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            var dty_ID = response.data[0];
                            fields[ 'dty_ID' ] = (''+dty_ID);
                        
                            $Db.dty(dty_ID, null, fields); //add on client side  
                            
                            that.addNewFieldToStructure( dty_ID, after_dtid );
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }

                    });
            }  
        
        
    },

    //  -----------------------------------------------------
    //
    // remove field, special mode for separator/group
    //
    _removeField: function(recID){
        
        var tree = this._treeview.fancytree("getTree");
        var node = null;
        if(recID>0){
            node = tree.getNodeByKey(String(recID));
        }else {
            node = tree.getActiveNode();   
        }
        if(!node) return;
        
        if(node.folder){
            //remove from recset
            var recID = node.key;
            this._cachedRecordset.removeRecord( recID );
            this._afterDeleteEvenHandler( recID );
        }else if(node.key>0){
            this._onActionListener(null,  {action:'delete', recID:(this.options.rty_ID+'.'+node.key)});
        }
     
    },
    
    //
    //
    //
    _afterDeleteEvenHandler: function( recID ){
        
        if(recID.indexOf(this.options.rty_ID+'.')===0){
            recID = recID.substring(recID.indexOf('.')+1);
        }
        
        this._cachedRecordset.removeRecord( recID );
        
        this._super(recID);
        
        var tree = this._treeview.fancytree("getTree");
        var node = tree.getNodeByKey(String(recID));
        if(node){
            if(node.folder){
                // remove from tree
                // all children moves to parent
                var children = node.getChildren();
                if(children && children.length>0){
                    var parent = node.getParent();
                    parent.addChildren(children, node);
                }
            }else{

            }
            node.remove();
        }
        
        this._showRecordEditorPreview(); //redraw 
        
    },
    
    //
    //
    //
    editField: function(recID){
        var tree = this._treeview.fancytree("getTree");
        tree.getRootNode().setActive();
        var node = tree.getNodeByKey(String(recID));
        node.setActive();
    },
    
    //
    //
    //
    onCreateChildIfRecPtr: function ( ed_input ){
        
        var rty_ID = this.options.rty_ID;
        var dty_ID = this._currentEditID;
        
        var $dlg;
        var value = ed_input.getValues()[0];   //!$(ed_input).is(':checked')
 
        if(value==0){ 
            var that = this;
            //warning on cancel
            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                '<h3>Turning off child-record function</h3><br>'
                +'<div><b>DO NOT DO THIS</b> if you have entered data for child records, unless you fully understand the consequences. It is likely to invalidate the titles of child records, make it hard to retrieve them or to identify what they belong to.</div><br>'
                +'<div>If you do accidentally turn this function off, it IS possible to turn it back on again (preferably immediately …) and recover most of the information/functionality.</div><br>'
                +'<div><label><input type="checkbox">Yes, I want to turn child-record function OFF for this field</label></div>',
                {'Proceed':function(){ 
                    ed_input.setValue(0, false); 
                    that.onEditFormChange();
                    $dlg.dialog('close'); },
                'Cancel':function(){ ed_input.setValue(1, true); $dlg.dialog('close'); } },
                {title:'Warning'},
                {default_palette_class:this.options.default_palette_class});    

        }else{
            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                '<h3>Convert existing records to children</h3><br>'
                +'<div>Records referenced by this pointer field will become child records of the record which references them. Once allocated as a child record, the parent cannot be changed. </div><br>'
                +'<div>WARNING: It is difficult to undo this step ie. to change a child record pointer field back to a standard pointer field.</div><br>'
                +'<div><label><input type="checkbox">Yes, I want to turn child-record function ON for this field</label></div>',
                {'Proceed': function(){

                    window.hWin.HEURIST4.msg.showMsgFlash('converting to child records, may take up to a minute, please wait …', false);
                    window.hWin.HEURIST4.msg.bringCoverallToFront( $(this.document).find('body') );            

                    //start action - it adds reverse links and set rst_CreateChildIfRecPtr
                    var request = {
                        a: 'add_reverse_pointer_for_child',
                        rtyID: rty_ID,   //rectype id
                        dtyID: dty_ID,   //field type id 
                        allow_multi_parent:true
                    };

                    window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){

                        window.hWin.HEURIST4.msg.closeMsgFlash();
                        window.hWin.HEURIST4.msg.sendCoverallToBack();

                        if(response.status == hWin.ResponseStatus.OK){
                            //show report

                            var link = '<a target="blank" href="'+window.hWin.HAPI4.baseURL + '?db='+window.hWin.HAPI4.database+'&q=ids:';
                            var link2 = '"><img src="'+window.hWin.HAPI4.baseURL+'common/images/external_link_16x16.gif">&nbsp;';

                            function __getlink(arr){
                                return link+arr.join(',')+link2+arr.length; 
                            }           


                            var sName = $Db.rst(rty_ID, dty_ID, 'rst_DisplayName');

                            sMsg = '<h3>Conversion of records to child records</h3><br><b>Pointer field:'+ sName +'</b><br><br>'
                            +'<div>'+response.data['passed']+' record pointer values were found for this field</div>'
                            +(response.data['disambiguation']>0?('<div>'+response.data['disambiguation']+' values ignored. The same records were pointed to as a child record by more than one parent</div>'):'')
                            +(response.data['noaccess']>0?('<div>'+response.data['noaccess']+' records cannot be converted to child records (no access rights)</div>'):'');

                            if(response.data['passed']>0)
                            {
                                if(response.data['processedParents'] && response.data['processedParents'].length>0){
                                    sMsg = sMsg
                                    +'<br><div>'+__getlink(response.data['processedParents'])+' parent records</a> (records of this type with this pointer field) were processed</div>'
                                    +((response.data['childInserted'].length>0)?('<div>'+__getlink(response.data['childInserted'])+' records</a> were converted to child records</div>'):'')
                                    +((response.data['childUpdated'].length>0)?('<div>'+__getlink(response.data['childUpdated'])+' child records</a> changed its parent</div>'):'')
                                    +((response.data['titlesFailed'].length>0)?('<div>'+__getlink(response.data['titlesFailed'])+' child records</a> failed to update tecord title</div>'):'');
                                }
                                if(response.data['childAlready'] && response.data['childAlready'].length>0){
                                    sMsg = sMsg
                                    +'<div>'+__getlink(response.data['childAlready'])+' child records</a> already have the required reverse pointer (OK)</div>';
                                }

                                if(response.data['childMiltiplied'] && response.data['childMiltiplied'].length>0){
                                    sMsg = sMsg
                                    +'<div>'+__getlink(response.data['childMiltiplied'])+' records</a> were pointed to as a child record by more than one parent (Problem)</div>'
                                    +'<br><div>You will need to edit these records and choose which record is the parent (child records can only have one parent).</div>'
                                    +'<div>To find these records use Verify > Verify integrity <new tab icon></div><br>'
                                }
                            }
                            //sMsg = sMsg 
                            sMsg = sMsg 
                            +'<br>Notes<br><div>We STRONGLY recommend removing - from the record structure of the child record type(s) -  any existing field which points back to the parent record</div>'
                            +'<br><div>You will also need to update the record title mask to use the new Parent Entity field to provide information (rather than existing fields which point back to the parent)</div>'
                            +'<br><div>You can do both of these changes through Structure > Modify / Extend <new tab icon> or the Modify structure <new tab icon> link when editing a record.</div>';

                            window.hWin.HEURIST4.msg.showMsgDlg(sMsg);

                            $Db.rst(rty_ID, dty_ID, 'rst_CreateChildIfRecPtr', 1);

                        }else{
                            ed_input.setValue(0, true);
                            //$(ed_input).prop('checked', false);
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                },
                'Cancel':function(){ ed_input.setValue(0, false); $dlg.dialog('close'); } },
                {title:'Warning'});    
        }

        //enable proceed button on checkbox mark    
        var btn = $dlg.parent().find('button:contains("Proceed")');
        var chb = $dlg.find('input[type="checkbox"]').change(function(){
            window.hWin.HEURIST4.util.setDisabled(btn, !chb.is(':checked') );
        })
        window.hWin.HEURIST4.util.setDisabled(btn, true);

        return false;
    },

    //
    getUiPreferences:function(){
        this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, {
            treepanel_closed: true,
            treepanel_width: 400
        });
        
        return this.usrPreferences;
    },
    
    //    
    saveUiPreferences:function(){
   
        if(this.mainLayout){
            var myLayout = this.mainLayout.layout();                
            sz = myLayout.state.west.size;
            isClosed = myLayout.state.west.isClosed;

            var params = {
                treepanel_closed: isClosed,
                treepanel_width: sz,
                help_on: this.usrPreferences['help_on']
            }
            
            window.hWin.HAPI4.save_pref('prefs_'+this._entityName, params);
        }
        return true;
    },
    
    
    
});