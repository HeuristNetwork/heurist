/**
* manageDefRecStructure.js - main widget to manage record type structure
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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

    _entityName:'defRecStructure',
    
    _fakeSepIdsCounter:0,
    _lockDefaultEdit: false,
    _dragIsAllowed: true,
    
    //
    //
    //    
    _init: function() {
        
        //special header field stores UI structure
        this.DT_ENTITY_STRUCTURE = window.hWin.HAPI4.sysinfo['dbconst']['DT_ENTITY_STRUCTURE'];
        
        this.options.rty_ID = 4;//is required
        this.options.previewEditor = null; // record editor for preview
        
        this.options.layout_mode = 'short';
        this.options.use_cache = true;
        this.options.use_structure = true;
        //this.options.edit_mode = 'popup';
        
        //this.options.select_return_mode = 'recordset';
        this.options.edit_need_load_fullrecord = false; //true;
        this.options.edit_height = 640;
        this.options.edit_width = 640;
        
        this.options.width = 1200;
        this.options.height = 640;
        this.options.edit_mode = 'inline';//'popup'; //editonly

        if(this.options.edit_mode=='editonly'){
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
        }else{
            this.options.layout_mode =                 
                '<div class="ent_wrapper">'
                        +    '<div class="ent_header searchForm" style="display:none"/>'     
                        +    '<div class="ent_content_full"  style="top:0px">'
                                +'<div class="ent_content_full recordList" style="top:0px;bottom:50%;width:320px"/>'
                                +'<div class="ent_content_full treeView" style="top:50%;width:320px"/>' //treeview
                                +'<div class="ent_wrapper" style="left:321px">'
                                +    '<div class="ent_header editForm-toolbar"/>'
                                +    '<div class="ent_content_full editForm" style="top:0px"/>'
                                +    '<div class="ent_content_full previewEditor" style="display:none;top:0px"/>'
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
                    title = title+': '+window.hWin.HEURIST4.rectypes.names[this.options.rty_ID];
                }
            }
            
            this._as_dialog.dialog('option', 'title', title);    
        }
        
        this.previewEditor = this.element.find('.previewEditor');
        
        this.recordList.resultList('option', 'show_toolbar', false);
        this.recordList.resultList('option', 'pagesize', 9999);
        this.recordList.resultList('option', 'view_mode', 'list');
        
        if(this.options.use_cache){ //init list only once

            if(this.options.use_structure){  //take fata from HEURIST4 
                //take recordset from HEURIST.detailtypes format     
                this._cachedRecordset = this._getRecordsetFromStructure();
                this.recordList.resultList('updateResultSet', this._cachedRecordset);
                
                //this._selectAndEditFirstInRecordset(this._cachedRecordset);
            
                //var treeData = this._cachedRecordset.getTreeViewData('trm_Label','trm_ParentTermID');
                this._initTreeView();
                this._showRecordEditorPreview();
                
            }else{
                //usual way from server - NOT USED  
                // @todo use doRequest to filter by this.options.rty_ID
                var that = this;
                window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, false,
                    function(response){
                        that._cachedRecordset = response;
                        that.recordList.resultList('updateResultSet', response);
                    });
            }
        }    
        return true;
    },            
    
    //
    // get recordset from HEURIST4.rectypes
    //
    _getRecordsetFromStructure: function(){
        
        var rdata = { 
            entityName:'defRecStructure',
            count: 0,
            total_count: 0,
            fields:[],
            records:{},
            order:[] };

        var rectypes = window.hWin.HEURIST4.rectypes;

        rdata.fields = window.hWin.HEURIST4.util.cloneJSON(rectypes.typedefs.dtFieldNames);
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
                    field.unshift(this.options.rty_ID); 
                    field.unshift(dty_ID); 
                    field.unshift(dty_ID); //rts_ID is dty_ID
                    rdata.records[dty_ID] = field;
                    rdata.order.push( dty_ID );
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
        //find all separators and detect json tree data in Extended Description field
        //if such treeview data is missed creates new one based on header/separators and rst_DisplayOrder

        var treeData = false;
        var record = recset.getById(this.DT_ENTITY_STRUCTURE);
        if(record){
            treeData = window.hWin.HEURIST4.util.isJSON(recset.fld(record, 'rst_DisplayExtendedDescription'));    
        }
        

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
                
                var node = {title: recset.fld(record,'rst_DisplayName'), key: dty_ID};
                
                if(recset.fld(record, 'dty_Type')=='separator'){
                    
                    node['key'] = 9000000+seps.length;
                    //debug node['title'] = node['key']+' '+node['title'];
                    node['folder'] = true;
                    node['data'] = {help:recset.fld(record,'rst_DisplayHelpText'), type:'group'};
                    node['children'] = [];
                    groupIdx = treeData.length;
                    treeData.push( node );
                    seps.push(dty_ID);
                    
                }else{
                    if(groupIdx<0){
                        treeData.push( node );    
                    }else{
                        treeData[groupIdx]['children'].push(node);
                    }
                }
            });
            
            //add fake records to keep group header data (id>9M)
            /*
            for(var i=0; i<seps.length; i++){
               var record = recset.getById(seps[i]);
               recset.removeRecord(seps[i]); 
               var newid = 9000000+i;
               recset.setFld(record,'rst_ID',newid);
               recset.setFld(record,'rst_DetailTypeID',newid);
               recset.setFld(record,'rst_SeparatorType','group');
               
               recset.removeRecord(seps[i]);
               recset.addRecord(newid, record);
            }
            //add new service separator that will keep rt structure
            recset.addRecord(this.DT_ENTITY_STRUCTURE,{
                rst_ID: this.DT_ENTITY_STRUCTURE, 
                rst_RecTypeID: this.options.rty_ID, 
                rst_DetailTypeID: this.DT_ENTITY_STRUCTURE,
                rst_DisplayName: 'Record type structure', 
                rst_DisplayHelpText: '',
                rst_DefaultValue: 'group',
                dty_Type: 'separator',
                rst_RequirementType: 'forbidden',  //it will not be visible
                rst_DisplayExtendedDescription: JSON.stringify(treeData)}); 
                
            this.recordList.resultList('updateResultSet', recset);
            */
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
            /*
            click: function(event, data){
                var ele = $(event.target);
                if(ele.hasClass('ui-icon-folder')){
                    //add new group/separator
                    that._addNewSeparator();
                    window.hWin.HEURIST4.util.stopEvent(event)
                }
            },*/
            activate: function(event, data) {
                
                that.selectedRecords([data.node.key]);
                if(!that._lockDefaultEdit)
                    that._onActionListener(event, {action:'edit'}); //default action of selection        
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
                    return node.folder ?true :["before", "after"];
                },
                dragDrop: function(node, data) {
                    
                    data.otherNode.moveTo(node, data.hitMode);    
                    //save treeview in database
                    that._dragIsAllowed = false;
                    setTimeout(function(){
                        that._saveRtStructureTree();
                    },500);
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
            this._treeview = this.element.find('.treeView').fancytree(fancytree_options); //was recordList
            
            setTimeout(function(){
                    var tree = that._treeview.fancytree('getTree');
                    //add missed fields
                    //if some fields were added in old ui they are missed in tree data - need to add them manually
                    recset.each(function(dty_ID, record){
                        if(recset.fld(record,'dty_Type')!='separator')
                        {
                            var node = tree.getNodeByKey(String(dty_ID));
                            if(!node){
                                node = {title: recset.fld(record,'rst_DisplayName'), key: dty_ID};
                                tree.getRootNode().addNode(node);
                            }
                        }
                    });
                    
                    //add fake records to keep group header data (id>9M)
                    tree.visit(function(node){
                        if(node.folder){
                            node.setExpanded(true);//since v2.3 tree.expandAll();

                            var record = {
                                rst_ID: node.key, 
                                rst_RecTypeID: that.options.rty_ID, 
                                rst_DetailTypeID: node.key,
                                rst_DisplayName: node.title, 
                                rst_DisplayHelpText: (node.data && node.data.help)?node.data.help:'',
                                rst_SeparatorType: (node.data && node.data.type)?node.data.type:'group',
                                dty_Type: 'separator'
                            };
                            recset.addRecord(node.key, record);
                            that._fakeSepIdsCounter = Math.max(that._fakeSepIdsCounter, node.key);
                        }
                    });
                    //add/update new service separator that will keep rt structure
                    recset.addRecord(that.DT_ENTITY_STRUCTURE,{
                        rst_ID: that.DT_ENTITY_STRUCTURE, 
                        rst_RecTypeID: that.options.rty_ID, 
                        rst_DetailTypeID: that.DT_ENTITY_STRUCTURE,
                        rst_DisplayName: 'Record type structure', 
                        rst_DisplayHelpText: '',
                        rst_DefaultValue: 'group',
                        dty_Type: 'separator',
                        rst_RequirementType: 'forbidden',  //it will not be visible
                        rst_DisplayExtendedDescription: JSON.stringify(tree.toDict(false))}); 
                        
                    that.recordList.resultList('updateResultSet', recset);
                    
                    that.__updateActionIcons();
                }, 500);  


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

               
               
               var actionspan = $('<div class="svs-contextmenu3" style="position:absolute;right:4px;display:none;padding-top:2px">'
                   +'<span class="ui-icon ui-icon-folder" title="Add a new group/separator"></span>'               
                   +'<span class="ui-icon ui-icon-plus" title="Add a new field to this record type"></span>'
                   +'<span class="ui-icon ui-icon-trash" title="Delete this field"></span>'
                   +($(item).hasClass('fancytree-folder')?'':
                    '<span class="ui-icon ui-icon-arrowthick-1-w" title="Requirement"></span>'
                   +'<span class="ui-icon ui-icon-arrowthick-1-e" title="Repeatability"></span>')
                   +'</div>').appendTo(item);
                   
               var that = this;

               actionspan.find('.ui-icon').click(function(event){
                    var ele = $(event.target);
                    that._lockDefaultEdit = true;
                    //timeout need to activate current node    
                    setTimeout(function(){
                        that._lockDefaultEdit = false;
                        if(ele.hasClass('ui-icon-plus')){
                           
                           //add field   
                           that._showBaseFieldEditor(-1);
                            
                        }else if(ele.hasClass('ui-icon-folder')){
                            
                            //add new group/separator
                            that._addNewSeparator();

                        }else if(ele.hasClass('ui-icon-trash')){
                            //different actions for separator and field
                            that._removeField();
                            
                        }else if(ele.hasClass('ui-icon-arrowthick-1-w')){
                            // requirement
                            
                        }else if(ele.hasClass('ui-icon-arrowthick-1-e')){
                            // repeatability
                            
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
                   }
               );               
               $(item).mouseleave(
                   _onmouseexit
               );
           }
    },
    
    
    //----------------------
    //
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
                + ' ('+window.hWin.HEURIST4.detailtypes.lookups[fld('dty_Type')]+')';

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID
                    +'" style="height:'+(is_narrow?'1.3':'2.5')+'em">'
                    + '<div class="recordTitle" style="left:24px">'
                    + recTitle + '</div>';
        
        // add edit/remove action buttons
        //@todo we have _rendererActionButton and _defineActionButton - remove ?
        //current user is admin of database managers
        if(this.options.select_mode=='manager' && this.options.edit_mode=='popup' && window.hWin.HAPI4.is_admin()){
             /*
            html = html 
                + '<div class="rec_view_link logged-in-only" style="width:60px">'
                + '<div title="Click to edit field" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>'
                +'<div title="Click to delete reminder" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div></div>';
              */  
                
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
            
        /* special case for show in list checkbox
        html = html 
            +  '<div title="Make type visible in user accessible lists" class="item inlist logged-in-only" '
            + 'style="width:3em;padding-top:5px" role="button" aria-disabled="false" data-key="show-in-list">'
            +     '<input type="checkbox" checked="'+(fld('dty_ShowInLists')==0?'':'checked')+'" />'
            + '</div>';
            
            var group_selectoptions = this.searchForm.find('#sel_group').html();
                        
            html = html 
                //  counter and link to rectype + this._rendererActionButton('duplicate')
                //group selector
            +  '<div title="Change group" class="item inlist logged-in-only"'
            +  ' style="width:8em;padding-top:3px" data-key2="group-change">'
            +     '<select style="max-width:7.5em;font-size:1em" data-grpid="'+fld('dty_DetailTypeGroupID')
            + '">'+group_selectoptions+'</select>'
            +  '</div>'
                + this._rendererActionButton('delete');
        */
        
        html = html + '</div>'; //close recordDiv
        
        /* 
            html = html 
        +'<div title="Click to edit group" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
        + '</div>&nbsp;&nbsp;'
        + '<div title="Click to delete group" class="rec_view_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
        + '</div>'
        + '</div>';*/


        return html;
        
    },
    
    updateRecordList: function( event, data ){
        //this._super(event, data);
        if (data){
            if(this.options.use_cache){
                this._cachedRecordset = data.recordset;
                //there is no filter feature in this form - thus, show list directly
            }
            this.recordList.resultList('updateResultSet', data.recordset, data.request);
            this._selectAndEditFirstInRecordset(this.recordList);
            
            //var treeData = this._cachedRecordset.getTreeViewData('trm_Label','trm_ParentTermID');
            //this._initTreeView( treeData );

        }
    },
        
    //
    // can remove group with assigned fields
    //     
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this field? Proceed?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
        
    },
    
    //
    // extend dialog button bar
    //    
    _initEditForm_step3: function(recID){
        
        if(this._toolbar){
            this._toolbar.find('.ui-dialog-buttonset').css({'width':'100%','text-align':'right'});
            this._toolbar.find('#btnRecDelete').css('display', 
                    (recID>0?'block':'none'));
        }
        
        this._super(recID);
    },
    
    //
    // set visibility of buttons on toolbar (depends on isModified)
    //
    onEditFormChange: function( changed_element ){
        this._super(changed_element);
            
        if(this._toolbar){
            var isChanged = this._editing.isModified();
            this._toolbar.find('#btnRecDelete').css('display', 
                    (isChanged)?'none':'block');
            this._toolbar.find('#btnRecPreview').css('display', 
                    (isChanged)?'none':'block');
                    
            this._toolbar.find('#btnRecSave').css('display', 
                    (isChanged)?'block':'none');
            this._toolbar.find('#btnRecSaveAndClose').css('display', 
                    (isChanged)?'block':'none');
            this._toolbar.find('#btnRecCancel').css('display', 
                    (isChanged)?'block':'none');
        }
            
    },  
    
    //
    // array of buttons for toolbar
    //  
    _getEditDialogButtons: function(){
                                    
            var that = this;        
            
            var btns = [       /*{text:window.hWin.HR('Reload'), id:'btnRecReload',icons:{primary:'ui-icon-refresh'},
                click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form*/
                      
                {showText:true, icons:{primary:'ui-icon-plus'},text:window.hWin.HR('Add Field'),
                      css:{'margin-right':'0.5em','float':'left'}, id:'btnAddButton',
                      click: function() { 
                          //that._onActionListener(null, 'add'); 
                          //that._showBaseFieldEditor();
                      }},
                {text:window.hWin.HR('Save Order'),
                      css:{'float':'left',display:'none'}, id:'btnApplyOrder',
                      click: function() { that._onActionListener(null, 'save-order'); }},
                {text:window.hWin.HR('Preview in Editor'),
                      css:{'float':'left',display:'none'}, id:'btnRecPreview',
                      click: function() { that._showRecordEditorPreview(); }},
                      
                {text:window.hWin.HR('Close'), 
                      css:{'margin-left':'3em','float':'right'},
                      click: function() { 
                          that.closeDialog(); 
                      }},
                      
                {text:window.hWin.HR('Drop Changes'), id:'btnRecCancel', 
                      css:{'margin-left':'0.5em','float':'right',display:'none'},
                      click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form
                {text:window.hWin.HR('Save'), id:'btnRecSave',
                      accesskey:"S",
                      css:{'font-weight':'bold','float':'right',display:'none'},
                      click: function() { that._saveEditAndClose( null, 'none' ); }},
                {text:window.hWin.HR('Save and Close'), id:'btnRecSaveAndClose',  //save editor and show preview
                      accesskey:"C",
                      css:{'font-weight':'bold','float':'right',display:'none'},
                      click: function() { that._saveEditAndClose( null, 'close' ); }},
                {text:window.hWin.HR('Delete'), id:'btnRecDelete',
                      css:{'float':'right',display:'none'},
                      click: function() { that._onActionListener(null, 'delete'); }},
                      
                      ];
        
            return btns;
    },    
    
    //
    // Opens defDetailTypes editor
    //
    _showBaseFieldEditor: function( arg ){
        
        if(isNaN(parseInt(arg))){
            dtyID = this._currentEditID;
            if(!(dtyID>0)) return;
        }else{
            dtyID = arg;
        }
        
        var popup_options = {
                select_mode: 'manager',
                edit_mode: 'editonly', //only edit form is visible, list is hidden
                rec_ID: (dtyID>0)?dtyID:-1
            };
        
        if(!(dtyID>0)){
            //add new field to this record type structure
            popup_options['title'] = 'Select or Define new field';
            popup_options['newFieldForRtyID'] = this.options.rty_ID;
            popup_options['selectOnSave'] = true;
            popup_options['onselect'] = function(event, res)
            {
                if(res && res.selection){
                    if(window.hWin.HEURIST4.util.isArrayNotEmpty(res.selection)){
                        var dty_ID = res.selection[0];
                        console.log(dty_ID);                    
                    }else
                    if(window.hWin.HEURIST4.util.isRecordSet(res.selection)){
                        var recordset = res.selection;
                        var record = recordset.getFirstRecord();
                        console.log(record);                    
                    }
                }
            };
        }
        
        window.hWin.HEURIST4.ui.showEntityDialog('DefDetailTypes', popup_options);
    },
    
    //
    // show record editor form - that reflect the last changes of rt structure
    //
    _showRecordEditorPreview: function( recID, fields ){
        
        if(recID>0){
            this._afterSaveEventHandler(recID, fields);
        }
        //hide editor - show and updated preview
        if(this.previewEditor){
            this.previewEditor.show();
            if(!this.previewEditor.manageRecords('instance')){
                
                var that = this.previewEditor;
                
                var options = {
                        select_mode: 'manager',
                        edit_mode: 'editonly',
                        in_popup_dialog: false,
                        allowAdminToolbar: false,
                        new_record_params: {RecTypeID: this.options.rty_ID},
                        layout_mode:'<div class="ent_wrapper editor">'
                            + '<div class="ent_content_full recordList"  style="display:none;"/>'

                            + '<div class="ent_header editHeader"></div>'
                            + '<div class="editFormDialog ent_content">'
                                    + '<div class="ui-layout-center"><div class="editForm"/></div>'
                                    + '<div class="ui-layout-east"><div class="editFormSummary">....</div></div>'
                            + '</div>'
                        +'</div>',
                    onInitFinished:function(){
                            that.manageRecords('addEditRecord',-1); //call widget method
                    }
                }                
                
                this.previewEditor.manageRecords( options ).addClass('ui-widget');
            }else{
                this.previewEditor.manageRecords('reloadEditForm');
            }
        }
    },
    
     
    //-----
    //
    // assign event listener for rst_Repeatability and dty_Type fields
    //
    _afterInitEditForm: function(){

        if(this.previewEditor) this.previewEditor.hide();
        
        this._super();

        //hide after edit init btnRecRemove for status locked 
        if(this._toolbar){ //@todo
            this._toolbar.find('#btnRecDelete').hide();
            this._toolbar.find('#btnRecSave').hide(); 
            this._toolbar.find('#btnRecSaveAndClose').hide(); 
            this._toolbar.find('#btnRecCancel').hide(); 
        }
        
        var edit_ele= this._editing.getFieldByName('rst_Repeatability');
        if(edit_ele){
            var that = this;
            
            edit_ele.editing_input('option','change', function(){
                var res = this.getValues()[0];
                if(res=='single' || res=='repeatable'){
                    res = (res=='repeatable')?0:1;
                    that._editing.getFieldByName('rst_MaxValues').hide();
                }else if(res=='limited'){
                    res = 2;
                    that._editing.getFieldByName('rst_MaxValues').show();
                }
                that._editing.setFieldValueByName('rst_MaxValues', res);    
            });
        }
        
        //fill init values of virtual fields
        //add lister for dty_Type field to show hide these fields
        var elements = this._editing.getInputs('dty_Type');
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(elements)){
            this._on( $(elements[0]), {'change': this._onDetailTypeChange});
            this._onDetailTypeChange();
            //$(elements[0]).change(); //trigger for current type
        }

        if(this._editing.getValue('dty_Type')=='separator'){
            this.editForm.find('.ui-accordion').hide()    
        }else{
            var ele = $('<span style="font-style:italic;padding:5px">'
                +'To change terms list or target entity types: <a href="#">Edit base field definitions</a></span>')
                .appendTo(this.editForm);
            this._on(ele.find('a'),{click: this._showBaseFieldEditor}); 
        }

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
                       depended_fields = this._editing.getFieldByClass(dt_type);
                       for(var idx in depended_fields){
                           $(depended_fields[idx]).show();
                       }
                       
                       if(dt_type=='separator'){
                           this._editing.getFieldByName('rst_RequirementType').hide();
                       }else{

                           this._editing.getFieldByName('rst_RequirementType').show();
                           this._editing.getFieldByName('rst_Repeatability').show();

                           if(dt_type=='enum' || dt_type=='relmarker' || dt_type=='relationtype'){
                               this._recreateTermsPreviewSelector();
                           }

                           var maxval = parseInt(this._editing.getValue('rst_MaxValues')[0]);
                           var res = 'repeatable';
                           if(maxval===1){
                               res = 'single';
                           }else if(maxval>1){
                               res = 'limited';
                           }
                           
                           this._editing.setFieldValueByName('rst_Repeatability', res, false);
                           if(maxval>1){
                               this._editing.getFieldByName('rst_MaxValues').show();
                           }else{
                               this._editing.getFieldByName('rst_MaxValues').hide();
                           }

                       }
                       
                    
    },    
    
    //
    //
    //
    _recreateTermsPreviewSelector: function(){
        
        var allTerms = this._editing.getValue('rst_FilteredJsonTermIDTree')[0];

        //remove old content
        var edit_ele = this._editing.getFieldByName('rst_TermPreview');
        edit_ele.find('.input-div').empty();

        if(!window.hWin.HEURIST4.util.isempty(allTerms)) {

            var disTerms = this._editing.getValue('dty_TermIDTreeNonSelectableIDs')[0];
            
            var term_type = this._editing.getValue('dty_Type')[0];
            if(term_type!="enum"){
                term_type="relation";
            }
            
            var new_selector = $('<select>');
            edit_ele.find('.input-div').prepend(new_selector); 
            
            new_selector = window.hWin.HEURIST4.ui.createTermSelectExt2(new_selector[0],
                {datatype:term_type, termIDTree:allTerms, headerTermIDsList:disTerms,
                    defaultTermID:null, topOptions:false, supressTermCode:true, useHtmlSelect:false});
            
            new_selector.css({'backgroundColor':'#cccccc','min-width':'220px'})
                    .change(function(event){event.target.selectedIndex=0;});
            
        }
        
    },

    
    //
    // trigger save DT_ENTITY_STRUCTURE
    //
    _saveRtStructureTree: function(){
        
        var fields = {rst_ID: this.DT_ENTITY_STRUCTURE};
        this._saveEditAndClose(fields, 'close');
        
        //that.__updateActionIcons(200);
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
                fields = this._getValidatedValues(); 
            }
            if(fields==null) return; //validation failed

            var treeview = this._treeview.fancytree("getTree");
            var recID = fields['rst_ID'];
            
            
            //save structure (tree) in DT_ENTITY_STRUCTURE field
            if(fields['dty_Type']=='separator' || recID==this.DT_ENTITY_STRUCTURE)
            {
                var recset = this.getRecordSet();
                
                if(recID!=this.DT_ENTITY_STRUCTURE){
                    // 1. update content of data field in treeview for this separator
                    if(treeview){
                        var node = treeview.getNodeByKey( String(recID) );
                        if(node){
                            node.setTitle( fields['rst_DisplayName'] ); 
                            node.data = {help:fields['rst_DisplayHelpText'], type:fields['rst_SeparatorType']};   
                        } 
                    }
                    // 2. update fake separator in recordset 
                    recset.setRecord(recID, fields);
                }
                
                // 3. get treeview.toDict and pur it in ExtDescription of field 2-57 ("Header 1") 
                treeview.visit(function(node){
                        if(!node.folder){
                            node.data = {}; //remove garbage fancytree may put here
                        }});
                var treeData = treeview.toDict(false);
                recset.setFldById(this.DT_ENTITY_STRUCTURE, 'rst_DisplayExtendedDescription', JSON.stringify(treeData));
                
                // 5. save DT_ENTITY_STRUCTURE in database (not fake separator field)
                fields = recset.getRecord(this.DT_ENTITY_STRUCTURE);
                
            }
            
            if(afterAction=='close'){
                //after save on server - close edit form and refresh preview
                afterAction = function( recID ){
                    that._showRecordEditorPreview(recID);  //refresh 
                };
            }
                
            //save record    
            this._super( fields, afterAction );                
    },    
    //--------------------------------------------------------------------------
    //
    // update 1)list, 2)treeview and 3)preview after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){

        //record is already updated in _saveEditAndClose
        //this._super( recID, fieldvalues );
        window.hWin.HEURIST4.msg.showMsgFlash(this.options.entity.entityTitle+' '+window.hWin.HR('has been saved'),500);
        
        //update recordset
        var recset = this.getRecordSet()
        var record = recset.getById(recID);
        //recset.setRecord(recID, fieldvalues);
        
        //update UI
        if(this.options.edit_mode == 'editonly'){
            this.closeDialog(true); //force to avoid warning
        }else{
//console.log('_afterSaveEventHandler: refresh tree and recordList');           
            // 1) list
            this.recordList.resultList('refreshPage');  
            // 2) refresh treeview
            var tree = this._treeview.fancytree("getTree");
            if(tree){
                var node = tree.getNodeByKey( recID );
                if(node) {
console.log('set naem '+recset.fld(record, 'rst_DisplayName'));                    
                    node.setTitle( recset.fld(record, 'rst_DisplayName') );   
                }
            }
            // 3) update HEURIST4.rectype (for preview)
            var rectypes = window.hWin.HEURIST4.rectypes;
            if(this.options.rty_ID>0 && rectypes.typedefs[this.options.rty_ID]){
                var fields = rectypes.typedefs[this.options.rty_ID].dtFields[recID];
                var fi = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;
                for(var fname in fi)
                if(fname){
                    fields[fi[fname]] = recset.fld(record, fname);
                }
                /*
                var fi = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;
                recset.each(function(dty_ID, record){
                    for(var fname in fi)
                    if(fname){
                        fields[fi[fname]] = recset.fld(record, fname);
                    }
                });
                */
            }
            //4. update action buttons
            this.__updateActionIcons(200);
        }
        this._dragIsAllowed = true;
    },

    //  -----------------------------------------------------
    //
    // perform special action for virtual fields 
    //
    _getValidatedValues: function(){
        
        //fieldvalues - is object {'xyz_Field':'value','xyz_Field2':['val1','val2','val3]}
        var fieldvalues = this._super();
        /*
        if(fieldvalues!=null){
            var data_type =  fieldvalues['dty_Type'];
            if(data_type=='freetext' || data_type=='blocktext' || data_type=='date'){
                var val = fieldvalues['dty_Type_'+data_type];
                
                fieldvalues['dty_JsonTermIDTree'] = val;
                delete fieldvalues['dty_Type_'+data_type];
            }
        } 
        */
        return fieldvalues;
        
    },

    
    //  -----------------------------------------------------
    //
    // add new separator/group
    //
    _addNewSeparator: function(){
        
        var tree = this._treeview.fancytree("getTree");
        var node = tree.getActiveNode();
        if(!node) return;
   
        //add to recordset        
        this._fakeSepIdsCounter++;    
        var recset = this._cachedRecordset;
        var record = {
            rst_ID: this._fakeSepIdsCounter, 
            rst_RecTypeID: this.options.rty_ID, 
            rst_DetailTypeID: this._fakeSepIdsCounter,
            rst_DisplayName: '', 
            rst_DisplayHelpText: '',
            rst_SeparatorType: 'group',
            dty_Type: 'separator'
        };
        recset.addRecord(this._fakeSepIdsCounter, record);
        
        //add to treeview
        var newnode = {key:this._fakeSepIdsCounter, folder:true, title:'New header', data:{type:'group'}};
        node = node.addNode(newnode, 'before');
        node.setActive(); //start edit
     
    },

    //  -----------------------------------------------------
    //
    // remove field, special mode for separator/group
    //
    _removeField: function(){
        
        var tree = this._treeview.fancytree("getTree");
        var node = tree.getActiveNode();
        if(!node) return;
        
        
        
        if(node.folder){
            // remove from tree
            // all children moves to parent
            var children = node.getChildren();
            var parent = node.getParent();
            parent.addChildren(children, node);
            node.remove();
            
            //remove from recset
            var recID = node.key;
            this._cachedRecordset.removeRecord( recID );
            this._afterDeleteEvenHandler( recID );
            
        }else{
            that._onActionListener(null, 'delete');
        }
     
    },
    
    _afterDeleteEvenHandler: function( recID ){
        this._super(recID);
        that._saveRtStructureTree();
        //this._showRecordEditorPreview();
    },
    
    
});