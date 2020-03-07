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
    
    //
    //
    //    
    _init: function() {
        
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
    // get recordset from HEURIST4.detailtypes
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

        rdata.fields = rectypes.typedefs.dtFieldNames;
        rdata.fields.unshift('rst_RecTypeID'); 
        rdata.fields.unshift('rst_DetailTypeID'); 
        rdata.fields.unshift('rst_ID'); //add as first
        
        //
        // note rectypes.typedefs does not have real rst_ID 
        // combination rty_ID(rst_RecTypeID) and dty_ID (rst_DetailTypeID) is unique
        // we keep dty_ID as rst_ID
        //
        
        if(this.options.rty_ID>0 && rectypes.typedefs[this.options.rty_ID]){

            for (var dty_ID in rectypes.typedefs[this.options.rty_ID].dtFields)
            {
                if(dty_ID>0){
                    var field = rectypes.typedefs[this.options.rty_ID].dtFields[dty_ID];
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
        recset.each(function(dty_ID, record){
            
            if(recset.fld(record, 'dty_Type')=='separator'){
                treeData = window.hWin.HEURIST4.util.isJSON(recset.fld(record, 'rst_DisplayExtendedDescription'));
                if(treeData!==false){
                    return false;
                }
            }
        });
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
            
            recset.each(function(dty_ID, record){
                
                var node = {title: recset.fld(record,'rst_DisplayName'), key: dty_ID};
                
                if(recset.fld(record, 'dty_Type')=='separator'){
                    node['folder'] = true;
                    node['children'] = [];
                    groupIdx = treeData.length;
                    treeData.push( node );
                }else{
                    if(groupIdx<0){
                        treeData.push( node );    
                    }else{
                        treeData[groupIdx]['children'].push(node);
                    }
                }
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
            
            expand: function(event, data){
                //show thumbs for expanded node - otherwise for root
                //data.node.children
                if(data.node.children && data.node.children.length>0){
                    /*
                    var rec_ids = [];
                    for (var idx in data.node.children){
                        if(idx>=0){
                            var node = data.node.children[idx];
                            rec_ids.push(node.key);
                        }
                    }
                    */

                   setTimeout(function(){
                    $.each( $('.fancytree-node'), function( idx, item ){
                        that.__defineActionIcons(item);
                    });
                    }, 500);  
                    
                    /*
                    var subset = that._cachedRecordset.getSubSetByIds(rec_ids);
                    that._thumbs.resultList('applyViewMode', 'thumbs3'); 
                    that._thumbs.resultList('updateResultSet', subset);  
                    */
                }
            },

            activate: function(event, data) {
                
                that.selectedRecords([data.node.key]);
                that._onActionListener(event, {action:'edit'}); //default action of selection
                
                if (!that.options.edit_dialog){
                }
                
                //console.log('click on '+data.node.key+' '+data.node.title);
            }
        };

        fancytree_options['extensions'] = ["dnd"]; //, "filter", "edit"
        fancytree_options['dnd'] = {
                preventVoidMoves: true,
                preventRecursiveMoves: true,
                autoExpandMS: 400,
                dragStart: function(node, data) {
                    return true;
                },
                dragEnter: function(node, data) {
                    return node.folder ?true :["before", "after"];
                },
                dragDrop: function(node, data) {
                    
                    data.otherNode.moveTo(node, data.hitMode);    
                    //save treeview in database
                    //that._saveTreeData( groupID );
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
                $.each( that.element.find('.treeView .fancytree-node'), function( idx, item ){
                    that.__defineActionIcons(item);
                });
                }, 500);  


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
                   +'<span class="ui-icon ui-icon-arrowthick-1-w" title="Requirement"></span>'
                   +'<span class="ui-icon ui-icon-arrowthick-1-e" title="Repeatability"></span>'
                   +'</div>').appendTo(item);
                   
                   
               actionspan.find('.ui-icon').click(function(event){
                    var ele = $(event.target);
                    
                    //timeour need to activate current node    
                    setTimeout(function(){                         
                        if(ele.hasClass('ui-icon-plus')){
                            //td _doAddChild(false);
                        }else if(ele.hasClass('ui-icon-folder')){

                        }else if(ele.hasClass('ui-icon-trash')){
                            that._onActionListener(null, 'delete');
                            
                        }else if(ele.hasClass('ui-icon-arrowthick-1-w')){
                            //td_import(false)
                        }else if(ele.hasClass('ui-icon-arrowthick-1-e')){
                            //td_export(false);
                        }else if(ele.hasClass('ui-icon-image')){
                            //td that.showFileUploader();
                        }
                    },500);
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
      
    _getEditDialogButtons: function(){
                                    
            var that = this;        
            
            var btns = [       /*{text:window.hWin.HR('Reload'), id:'btnRecReload',icons:{primary:'ui-icon-refresh'},
                click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form*/
                      
                {showText:true, icons:{primary:'ui-icon-plus'},text:window.hWin.HR('Add Field'),
                      css:{'margin-right':'0.5em','float':'left'}, id:'btnAddButton',
                      click: function() { 
                          //that._onActionListener(null, 'add'); 
                          that._showBaseFieldEditor();
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
                      click: function() { that._saveEditAndClose( null, function(recID){
                        that._showRecordEditorPreview(recID);  
                      }  ); }},
                {text:window.hWin.HR('Delete'), id:'btnRecDelete',
                      css:{'float':'right',display:'none'},
                      click: function() { that._onActionListener(null, 'delete'); }},
                      
                      ];
        
            return btns;
    },    
    
    _showBaseFieldEditor: function( dtyID ){
        
        if(dtyID>0){
            //load field editor 
            
            
        }else{
            //add new field to this record type structure
            
            
        }
        
        
    },
    
    //
    //
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
                    in_popup_dialog: true,
                    onInitFinished:function(){
                            //that.manageRecords('addEditRecord',-1); //call widget method
                    }
                }                
                
                this.previewEditor.manageDefDetailTypes( options ).addClass('ui-widget');
            }else{
                this.previewEditor.manageRecords('reloadEditForm');
            }
        }
    },
    
     
    //-----
    //
    // adding group ID value for new field type
    // and perform some after load modifications (show/hide fields,tabs )
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

        //this._editing.setModified(false);

    },    
    
    _onDetailTypeChange: function()
    {
                       var dt_type = this._editing.getValue('dty_Type')[0]
                       
                       //hide all virtual 
                       var virtual_fields = this._editing.getFieldByValue("dty_Role","virtual");
                       for(var idx in virtual_fields){
                           $(virtual_fields[idx]).hide();
                       }
                       depended_fields = this._editing.getFieldByValue("dty_Depend","1");
                       for(var idx in depended_fields){
                           $(depended_fields[idx]).hide();
                       }
                       
                       if(dt_type=='separator'){

                           this._editing.getFieldByName('rst_SeparatorType').show();    
                           this._editing.getFieldByName('rst_Repeatability').hide();
                           this._editing.getFieldByName('rst_RequirementType').hide();


                       }else{

                           this._editing.getFieldByName('rst_RequirementType').show();
                           this._editing.getFieldByName('rst_Repeatability').show();

                           if(dt_type=='freetext' || dt_type=='blocktext'){
                               this._editing.getFieldByName('rst_DisplayWidth').show();
                               if(dt_type=='blocktext'){
                                   this._editing.getFieldByName('rst_DisplayHeight').show();    
                               }
                           }else if(dt_type=='enum' || dt_type=='relmarker' || dt_type=='relationtype'){
                               this._editing.getFieldByName('rst_TermPreview').show();
                               if(dt_type=='relmarker'){
                                   this._editing.getFieldByName('rst_PtrFilteredIDs').show();    
                               }
                               this._recreateTermsPreviewSelector();

                           }else if(dt_type=='resource'){

                               this._editing.getFieldByName('rst_PtrFilteredIDs').show();
                               this._editing.getFieldByName('rst_PointerMode').show();
                               this._editing.getFieldByName('rst_PointerBrowseFilter').show();
                               this._editing.getFieldByName('rst_CreateChildIfRecPtr').show();
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

    //--------------------------------------------------------------------------
    //
    // update list after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){

        
        this._super( recID, fieldvalues );
        
        //update recordset
        var recset = this.getRecordSet()
        recset.setRecord(recID, fieldvalues);
        
        //update UI
        if(this.options.edit_mode == 'editonly'){
            this.closeDialog(true); //force to avoid warning
        }else{
            //list
            this.recordList.resultList('refreshPage');  
            //refresh treeview
            var tree = this._treeview.fancytree("getTree");
            if(tree){
                var node = tree.getNodeByKey( recID );
                if(node) node.setTitle( recset.fld(recset.getById(recID), 'rst_DisplayName') );
            }
        }
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

});