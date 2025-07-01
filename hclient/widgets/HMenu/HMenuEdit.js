/**
* HMenuEdit - extension for HMenu to compose its content. It is used in CMS editor
*
* @project     Heurist academic knowledge management system
* @package Wigets
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/
//import '../HBase/HBaseWidget.js';
import './HMenu.js';
import './HMenuOpts.js';

/* global selectEntity, browseRecords */

$.widget( 'heurist.HMenuEdit', $.heurist.HMenu, {

    // default options
    options: {
        viewMode: 'treeview',
        isEditMode: true,
        expandLevels: 2,
        onStructureChanged: null
    },
    
    _selectorInput: null, //hidden input to select/add menu intems in edit mode
    _browseFunction: null, //function that opens menu items selector popup

    
    _init: function(){
        /*             
        this.options.viewMode = 'treeview';
        this.options.isEditMode = true;
        this.options.expandLevels = 2;
        */
        
        this._super();
        
        let that = this;
        
        this._fancytreeOptionsEdit =
            {
                renderNode: function(event, data) {
                        let item = data.node;
                        that._defineActionIcons( item );
                },
                extensions:["dnd"], //"edit", 
                dnd:{
                    preventVoidMoves: true,
                    preventRecursiveMoves: true,
                    autoExpandMS: 400,
                    dragStart: function(node, data) {
                        return true; //data.has_access;
                    },
                    dragEnter: function(node, data) {
                        //data.otherNode - dragging node
                        //node - target node
                        return true;
                    },
                    dragDrop: function(node, data) {
                        //data.otherNode - dragging node
                        //node - target node
                        let source_parent = data.otherNode.parent.key;
                        if(!(source_parent>0))
                            source_parent = 0; //root

console.log(data.hitMode, node.data.isFolder);                            
                        let mode = data.hitMode;
                        if(mode=='over' && !(node.isRootNode() || node.data.isFolder)){
                            mode = 'after';
                        }
                            
                        data.otherNode.moveTo(node, mode);

                        //let target_parent = data.otherNode.parent.key; //data.pageId;
                        //if(!(target_parent>0))
                        //    target_parent = 0; //root
                        //data.otherNode.data.parent_id = target_parent;
                        
                        that._getMenuStructure();
                    }
                },
                edit:{
                    triggerStart: ["clickActive", "dblclick", "f2", "mac+enter", "shift+click"],
                    close: function(event, data){
                        // Editor was removed
                        if( data.save ) {
                            // Since we started an async request, mark the node as preliminary
                            $(data.node.span).addClass("pending");
                        }
                    },                                    
                    save:function(event, data){
                        if(''!=data.input.val()){
                            let new_name = data.input.val();
    //TBD                            _renameMenuEntry(data.node.data.pageId, new_name, function(){});
                        }else{
                            $(data.node.span).removeClass("pending");    
                        }
                    }
                }
            };
    },
    
    /*
    *  render actions buttons for edit mode
    */
    _defineActionIcons: function(item){
        
        let tree_element = this.element;
        let that = this;
        
        let item_li = $(item.li);
        let menu_id = item.key;//item.data.pageId;

        if($(item).find('.svs-contextmenu3').length==0){

            let parent_span = item_li.children('span.fancytree-node');

            //add,edit menu,edit page,remove
            let actionspan = $('<div class="svs-contextmenu3" style="padding: 0px 20px 0px 0px;" data-menuid="'+menu_id+'">'
                //since 12-05 +'<span class="ui-icon ui-icon-structure" title="Edit page"></span>'
                +'<span class="ui-icon ui-icon-plus" title="Add menu item"></span>'
                +'<span class="ui-icon ui-icon-pencil" title="Edit menu record"></span>'
                //+'<span class="ui-icon ui-icon-document" title="Edit page record"></span>'
                +'<span class="ui-icon ui-icon-trash" '
                +'" title="Remove menu entry"></span>'
                +'</div>').appendTo(parent_span);

            $('<div class="svs-contextmenu4"></div>').appendTo(parent_span); //progress icon

            actionspan.find('.ui-icon').on('click', function(event){
                let ele = $(event.target);
                window.hWin.HEURIST4.util.stopEvent(event);
                
                let parent_span = ele.parents('span.fancytree-node');
                
                //timeout need to activate current node    
                setTimeout(function(){                         
                    let ele2 = ele.parents('.svs-contextmenu3');
                    let menuid = ele2.attr('data-menuid');
                    //let parent_id = ele2.attr('data-parentid');

                    if(ele.hasClass('ui-icon-plus')){ //add new menu to 

                        that.addMenuEntry(menuid); 

                    }else if(ele.hasClass('ui-icon-pencil')){ //edit menu record

                        //parent_span.find('.svs-contextmenu4').show();
                        //parent_span.find('.svs-contextmenu3').hide();
                        
                        that.editMenuEntry(menuid);

                    }else 
                        if(ele.hasClass('ui-icon-trash')){    //remove menu entry
                            window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR("Delete menu entry? Please confirm"),  function(){
                                item.remove();  
                                that._getMenuStructure();  
                            });
                        }

                },500);
            });

            //hide icons on mouse exit
            function _onmouseexit(event){
                let node;
                if($(event.target).is('li')){
                    node = $(event.target).find('.fancytree-node');
                }else if($(event.target).hasClass('fancytree-node')){
                    node =  $(event.target);
                }else{
                    //hide icon for parent 
                    node = $(event.target).parents('.fancytree-node');
                    if(node) node = $(node[0]);
                }
                let ele = node.find('.svs-contextmenu3');
                ele.hide();
            }               

            function _onmouseenter(event){
                let node;
                if($(event.target).hasClass('fancytree-node')){
                    node =  $(event.target);
                }else{
                    node = $(event.target).parents('.fancytree-node');
                }
                if(! ($(node).hasClass('fancytree-loading') || $(node).find('.svs-contextmenu4').is(':visible')) ){
                    let ele = $(node).find('.svs-contextmenu3');
                    ele.css({'display':'inline-block'});
                }
            }

            $(parent_span).on('mouseenter',
                _onmouseenter
            ).on('mouseleave',
                _onmouseexit
            );                                                  
            
        }
    }, //end _defineActionIcons

    /*
    * Converts treeview strucuture to json {id1:{}   }
    */
    _getMenuStructure: function( struct ){
        
        let isRoot = false;
        if(!struct){
            isRoot = true;
            this._menuData = $.ui.fancytree.getTree( this.element ).toDict(true);
            struct = this._menuData.children;
        }                 
        
        let item = {};
        if(struct){
            for(let i=0; i<struct.length; i++){
                
                let node = struct[i];
                
                item[node.key] = {};
                
                if(node.children?.length>0){
                      item[node.key] = this._getMenuStructure(node.children);
                }
                if(node.data.isFolder){
                      item[node.key]['title'] = node.title;
                }
                
            }
        }
        
        if(isRoot && window.hWin.HEURIST4.util.isFunction(this.options.onStructureChanged)){
console.log(item);            
                this.options.onStructureChanged.call(this, item);
        }
        
        return item;
    },
    
    /*
    *
    */
    _verifyUniqueMenuKeys(menuData){
        
        let tree = $.ui.fancytree.getTree( this.element );
        let duplications = [];
        
        for (let idx in menuData){
            
            let item = menuData[idx];
            if(tree.getNodeByKey(item.key)){
                duplications.push(item.key);
            }
            
            if(item.children){
                let dups = this._verifyUniqueMenuKeys(item.children);            
                if(dups.length>0){
                    duplications = duplications.concat(dups);        
                }
            }
        }
        
        return duplications;
    },
    
    /*
    *
    */
    addMenuEntry: function( parentNodeKey ){
        
            if(this._selectorInput!=null){
                this._browseFunction();
                return;
            }

            this._selectorInput = $('<div>').uniqueId();
        
            //constraint
            let rty_IDs = [window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU']]; 
            if(window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_ACTION']){ //not used
                rty_IDs.push(window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_ACTION']);
            }
            
            let that = this;
        
            const ed_options = {
                recID: -1,                                                                                       
                dtID: this._selectorInput.attr('id'), //'group_selector',
                values: [],
                readonly: false,
                show_header: false,
                showclear_button: true,
                dtFields:{
                    dty_Type:"resource", rst_MaxValues:0,
                    rst_DisplayName: 'Menu items', rst_DisplayHelpText:'',
                    rst_PtrFilteredIDs: rty_IDs,
                    rst_FieldConfig: {entity:'records', csv:false}
                },
                change: ()=>{
                    //result
                    
                    const menuRecId = that._selectorInput.attr('data-value');
                    
                    //retrieve menu content from server side
                    let request = {website:1, ver:3, webmenu:[menuRecId], isTree:true, lang:that.options.language};
                    window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL, request, null, (response)=>{
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            let parentNode = that._getParentNode();
                            if(parentNode==null){ //treeview not inited - first call
                                that._menuData = response.data;
                                that._initControls(); 
                                return;
                            }
                            
                            const dups = that._verifyUniqueMenuKeys(response.data);
                            
                            if(dups.length>0){
                                window.hWin.HEURIST4.msg.showMsgErr('It is not possible to add new menu entry. It is already in menu');    
                            }else{
                                                                                //.hasChildren()
                                parentNode.addNode( response.data, parentNode.isRootNode() || parentNode.data.isFolder? 'child': 'after' );
                                that._getMenuStructure();
                            }
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                        
                    });
                }
            };
            
            this._selectorInput.editing_input(ed_options);
            this._selectorInput.appendTo($('<div>').appendTo(this.element));
            this._selectorInput.parent().hide();
            
            this._browseFunction = browseRecords(this._selectorInput.editing_input('instance'), this._selectorInput, 'Select menu items');
            
            this._browseFunction();
    },
    
    /*
    *  Adds submenu (folder)
    */ 
    addMenuFolder: function( parentNodeKey ){
        
        let that = this;
        
        window.hWin.HEURIST4.msg.showPrompt('', (submenuName)=>{
            
            if(submenuName=='') return;
        
            let toadd = {key:'folder', isFolder:true, title:submenuName };
            
            let parentNode = that._getParentNode();

            if(parentNode==null){ //treeview not inited
                that._menuData = toadd;
                that._initControls(); 
                return;
            }
            
            parentNode.addNode( toadd, parentNode.isRootNode() || parentNode.data.isFolder? 'child': 'after' );
            that._getMenuStructure();
            
            
        }, 'Define submenu title');
        
    },
    
    /*
    * Add saved filter as menu entry
    */
    addMenuFilterEntry: function( parentNodeKey ){
            
            let that = this;
        
            //see editing_exts.js    
            selectEntity(
                {entity:'usrSavedSearches', csv:true, title:'Select saved filters', default_palette_class:'ui-heurist-publish'},
                (sel)=>{
                    
                    if(!sel || sel.length()==0) return;
                    
                    let toadd = [];

                    sel.each2(( recID, record )=>{
                        toadd.push({key:'svs'+recID, action:'search-saved-filter', actionParams:recID, title: record['svs_Name']});
                    });


                    let parentNode = that._getParentNode();

                    if(parentNode==null){ //treeview not inited
                        that._menuData = toadd;
                        that._initControls(); 
                        return;
                    }
                    
                    parentNode.addNode( toadd, parentNode.isRootNode() || parentNode.data.isFolder? 'child': 'after' );
                    that._getMenuStructure();
            
                }
            );
    },

    /*
    *  Find parent node for new menu entry
    */    
    _getParentNode: function( parentNodeKey ){

        const tree = $.ui.fancytree.getTree( this.element );
        
        let parentNode = null;
        if(tree){
            if(parentNodeKey!='' && parentNodeKey!=0){
                parentNode = tree.getNodeByKey(parentNodeKey);
            }
            if(!parentNode){
                parentNode = tree.getRootNode();
                if(window.hWin.HEURIST4.util.isempty(this._menuData)){
                    tree.clear();
                }
            }
        }
        return parentNode; 
    },
    
    /*
    *
    */
    editMenuEntry: function(menuId){
        
        let that = this;
        
        if(menuId.indexOf('folder'==0)){
            
            let node = $.ui.fancytree.getTree( that.element ).getNodeByKey( menuId );
            
            window.hWin.HEURIST4.msg.showPrompt('', (submenuName)=>{
                    if(submenuName=='') return;
                    node.setTitle( submenuName );
                    that._defineActionIcons(node);
                    that._getMenuStructure();
            }, 'Edit submenu title', {value:node.title} );
            
        }else if(menuId.indexOf('svs'==0)){
            //TBD
            
        }else if(window.hWin.HEURIST4.util.isPositiveInt(menuId)){
            this._editPageRecord(menuId);   
        }
    },
    
    /*
    *
    */
    _editPageRecord: function (record_id){
            let that = this;
            //edit menu item
            window.hWin.HEURIST4.ui.openRecordEdit(record_id, null,
                {selectOnSave:true,
                    onClose: function(){ 
                        //parent_span.find('.svs-contextmenu4').hide();
                    },
                    onselect:function(event, data){
                        if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                            
                            let recordset = data.selection;
                            let pageId = recordset.getOrder()[0];
                            
                            // Update tree
                            let newTitle = recordset.fld(recordset.getFirstRecord(), window.hWin.DT_NAME);
                            let node = $.ui.fancytree.getTree( that.element ).getNodeByKey( pageId );
                            if(node.title != newTitle){
                                    node.setTitle( newTitle );
                                    that._defineActionIcons(node);
                                    that._getMenuStructure();
                            }
                        }
                    }
                }
            );
    },


/*
1) menu treeview to add/edit/delete (same as site menu)  redundant?
   or just root items (as it is)
   
2) Add - select CMS_page, 
                Filter group or Saved filter, (UsrSavedSearchs entry or CMS Filter record)
                Add Record  (CMS Action record or Dashboard entry)
                View Record 
                Execute Smarty with saved filter 
                External Link (Web bookmark record)
                
3) 
   
Wrokflow/Widget links   
   
                

*/    

    
});
