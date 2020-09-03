/**
* manageDefRecTypes.js - main widget to manage defRecTypes users
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
//"use_assets":["admin/setup/iconLibrary/64px/","HEURIST_ICON_DIR/thumb/"],

/*
we may take data from 
1) use_cache = false  from server on every search request (live data) 
2) use_cache = true   from client cache - it loads once per heurist session (actually we force load)
3) use_cache = true + use_structure - use HEURSIT4.rectypes
*/
$.widget( "heurist.manageDefTerms", $.heurist.manageEntity, {
   
    _entityName:'defTerms',
    
    //
    //                                                  
    //    
    _init: function() {
        
        this.options.use_cache = true;
        this.options.use_structure = false
        
        if(!this.options.layout_mode) this.options.layout_mode = 'short';
        if(this.options.auxilary!='vocabulary') this.options.auxilary='term';
        
        if(this.options.edit_mode=='editonly'){
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
        }
        
        this._super();
        
        var that = this;
        
        $(window.hWin.document).on(
            window.hWin.HAPI4.Event.ON_REC_UPDATE
            + ' ' + window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(e, data) { 
                if(!data || 
                   (data.source != that.uuid && data.type == 'trm'))
                {
                    that._loadData();
                }else
                if(data && data.type == 'vcg' && that.options.auxilary=='vocabulary'){
                    //update groups
                    that.recordList.resultList({groupByRecordset:$Db.vcg()});
                    that.recordList.resultList('refreshPage');
                }
                
            });
        
        
    },
    
    _destroy: function() {
        
       $(window.hWin.document).off(
            window.hWin.HAPI4.Event.ON_REC_UPDATE
            + ' ' + window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
            
       if(this.recordTree) this.recordTree.remove();
        
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
            this._initEditorOnly();
            return;
        }
       
        //init viewer 
        var that = this;
        
        if(this.options.select_mode=='manager'){

            if(this.options.auxilary=='vocabulary'){
                //vocabulary groups
                var btn_array = [
                    {showText:true, icons:{primary:'ui-icon-plus'},text:window.hWin.HR('Add Group'),
                          css:{'margin-right':'0.5em','float':'right'}, id:'btnAddButton',
                          click: function() { that._onActionListener(null, 'add-group'); }},

                    {text:window.hWin.HR('Save Order'),
                          css:{'margin-right':'0.5em','float':'right',display:'none'}, id:'btnApplyOrder',
                          click: function() { that._onActionListener(null, 'save-order'); }}];

                this._toolbar = this.searchForm;
                this.searchForm.css({'padding-top': '8px'}).empty();
                this._defineActionButton2(btn_array[0], this.searchForm);
                //this._defineActionButton2(btn_array[1], this.searchForm);
                
                this.options.recordList = {
                    show_toolbar: false,
                    view_mode: 'list',
                    
                    draggable: function(){
                        that.recordList.find('.rt_draggable').draggable({ //  > .item
                                    revert: true,
                                    XXXhelper: function(){ 
                                        return $('<div class="rt_draggable ui-drag-drop" recid="'+
                                            $(this).parent().attr('recid')
                                        +'" style="width:300;padding:4px;text-align:center;font-size:0.8em;background:#EDF5FF"'
                                        +'>Drag and drop to group item to change record type group</div>'); 
                                    },
                                    zIndex:100,
                                    //appendTo:'body',
                                    scope: 'vcg_change'
                                    //containment: that.element,
                                    //delay: 200
                                });
                    },
                    droppable: function(){
                        
                        that.recordList.find('div[data-grp]')  //.recordDiv, ,.recordDiv>.item
                            .droppable({
                                //accept: '.rt_draggable',
                                scope: 'vcg_change',
                                hoverClass: 'ui-drag-drop',
                                drop: function( event, ui ){

                                    var trg = $(event.target);
                                    //.hasClass('recordDiv')?$(event.target):$(event.target).parents('.recordDiv');
                                                
                                    var trm_ID = $(ui.draggable).attr('recid'); //parent().
                                    var vcg_ID = trg.attr('data-grp');
                                    if(trm_ID>0 && vcg_ID>0){
                                        
                                            var ele = that.recordList.find('div[data-grp-content='+vcg_ID+']');
                                            ele.show();
                                            if(ele.text()=='empty') ele.empty();
                                            $(ui.draggable).appendTo(ele);
                                            
                                            that.changeVocabularyGroup({trm_ID:trm_ID, trm_VocabularyGroupID:vcg_ID });
                                    }
                            }});
                            
                        //init buttons in header    
                        var conts = that.recordList.find('div[data-grp]');
                        $.each(conts, function(i, cont){
                            var grp_val = $(cont).attr('data-grp');
                            cont = $(cont).find('.action-button-container')
                            that._defineActionButton({key:'edit-group',label:'Edit group', title:'', icon:'ui-icon-pencil', 
                                    class:'rec_actions_button', recid:grp_val}, cont, 'small');
                            that._defineActionButton({key:'delete-group',label:'Remove group', title:'', icon:'ui-icon-delete', 
                                    class:'rec_actions_button', recid:grp_val}, cont, 'small');
                        });
                        
                        that.recordList.sortable({
                            items: conts,
                            stop:function(event, ui){

                            var recset = $Db.vcg();
                            var rec_order = recset.getOrder();

                            var conts = that.recordList.find('div[data-grp]');
                            conts.each(function(index, rdiv){
                                var rec_id = $(rdiv).attr('data-grp');
                                rec_order[index] = rec_id;
                                
                                that.recordList.find('div[data-grp-content='+rec_id+']')
                                    .insertAfter($(rdiv));
                            });
                            recset.setOrder(rec_order);
                            
console.log(rec_order);                            
                            window.hWin.HEURIST4.dbs.applyOrder(recset,'vcg');

                        }});


                    },
                    groupByField:'trm_VocabularyGroupID',
                    groupOnlyOneVisible: false,
                    groupByRecordset: $Db.vcg(),
                    //groupByCss:'0 1.5em',
                    rendererGroupHeader: function(grp_val, is_expanded){

                        var desc = $Db.vcg(grp_val, 'vcg_Description');
                        if(!desc) desc = '';

                        return '<div class="groupHeader" data-grp="'+grp_val
                            +'" style="font-size:0.9em;padding:4px 0 4px 0px;border-bottom:1px solid lightgray">'
                            +'<span style="display:inline-block;vertical-align:top;padding-top:5px;" '
                                    +'class="expand_button ui-icon ui-icon-triangle-1-'+(is_expanded?'s':'e')+'"></span>'
                            +'<div style="display:inline-block;width:70%">'
                            +'<div style="font-weight:bold;margin:0">'
                            +$Db.vcg(grp_val, 'vcg_Name')+'</div>'
                             //+grp_val+' '
                            +'<div style="padding-top:4px;font-size:smaller;"><i>'+desc+'</i></div></div>'
                            +'<div style="float:right" class="action-button-container"></div>'
                            +'</div>';
                    },
                    
                    
                };

                this.recordList.uniqueId();
                
            }else{
                
        
                //if(this.options.innerTitle && !this.options.auxilary){
            
                //add record type group editor  3px solid red
                this.element.css( {border:'none', 'box-shadow':'none', background:'none'} );
                
                this.element.find('.ent_wrapper:first').addClass('ui-dialog-heurist').css('left',288);
                
                this.vocabulary_groups = $('<div>').addClass('ui-dialog-heurist')
                    .css({position: 'absolute',top: 0, bottom: 0, left: 0, width:280, overflow: 'hidden'})
                    .uniqueId()
                    .appendTo(this.element);
                
                //console.log('vocab cont '+this.vocabulary_groups.attr('id'));               
             
                        
                //show particular terms for vocabulary 
                var btn_array = [
                    {showText:true, icons:{primary:'ui-icon-plus'}, text:window.hWin.HR('Add Term'),
                          css:{'margin-right':'0.5em','float':'right'}, id:'btnAddButton',
                          click: function() { that._onActionListener(null, 'add'); }},

                    {showText:true, icons:{primary:'ui-icon-arrowthick-1-e'}, text:window.hWin.HR('Import Terms'),
                          css:{'margin-right':'0.5em','float':'right'}, id:'btnImportVocab',
                          click: function() { that._onActionListener(null, 'vocab-import'); }},
                    
                    {showText:true, icons:{primary:'ui-icon-arrowthick-1-w'}, text:window.hWin.HR('Export Terms'),
                          css:{'margin-right':'0.5em','float':'right'}, id:'btnExportVocab',
                          click: function() { that._onActionListener(null, 'export-import'); }},
                          
                    {showText:false, icons:{primary:'ui-icon-menu'}, text:window.hWin.HR('Show as plain list'),
                          css:{'margin-right':'0.5em','float':'left'}, id:'btnViewMode_List',
                          click: function() { that._onActionListener(null, 'viewmode-list'); }},
                    {showText:false, icons:{primary:'ui-icon-structure'}, text:window.hWin.HR('Show as tree'),
                          css:{'margin-right':'0.5em','float':'left'}, id:'btnViewMode_Tree',
                          click: function() { that._onActionListener(null, 'viewmode-tree'); }},
                    {showText:false, icons:{primary:'ui-icon-view-icons'}, text:window.hWin.HR('Show as images'),
                          css:{'margin-right':'0.5em','float':'left'}, id:'btnViewMode_List',
                          click: function() { that._onActionListener(null, 'viewmode-thumbs'); }},
                    ];

                this._toolbar = this.searchForm;
                this.searchForm.css({'padding-top': '8px'}).empty();
                for(var idx in btn_array){
                        this._defineActionButton2(btn_array[idx], this.searchForm);
                }
                
                this.options.recordList = {
                    show_toolbar: false,
                    view_mode: 'list',
                };
                
                //group options
                var rg_options = {
                     isdialog: false, 
                     innerTitle: true,
                     container: that.vocabulary_groups,
                     title: 'Vocabulary groups',
                     select_mode: 'manager',
                     reference_trm_manger: that.element,
                     auxilary: 'vocabulary',
                     onSelect:function(res){
                         if(window.hWin.HEURIST4.util.isRecordSet(res)){
                            res = res.getIds();                     
                            if(res && res.length>0){
                                //filter by vocabulary
                                that.options.trm_ParentTermID = res[0];
                                
                                if(that.getRecordSet()!==null){
                                    
                                    that._filterByVocabulary();

                                }
                            }
                         }
                     }
                };
                
                this.recordList.uniqueId();
                
//console.log('init vocabs grp '+this.vocabulary_groups.attr('id'));               
                window.hWin.HEURIST4.ui.showEntityDialog('defTerms', rg_options);
                
            }

        
        }


//console.log('init list '+this.options.auxilary+'  '+this.recordList.attr('id'));                    
        
        this.recordList.resultList( this.options.recordList );
            
            
        that._loadData(true);
        
        return true;
    },            
    
    //
    // invoked after all elements are inited 
    //
    _loadData: function( is_first_call ){
        
        if(this.options.auxilary=='vocabulary'){
            //show vocabs only
            var recset = $Db.trm().getSubSetByRequest({'trm_ParentTermID':'=0', 'sort:trm_Label':1},
                                     this.options.entity.fields);
            this.updateRecordList(null, {recordset:recset});
            
            //initial selection
            //find first vocab 
            if(is_first_call==true){
                var rdiv = this.recordList.find('.recordDiv:first');
                var rec_ID = rdiv.attr('recid');
                rdiv.parent('div[data-grp-content]').show();
console.log(rec_ID);                
                rdiv.click();
                //this.recordList.resultList('setSelected', [rec_ID]);
            }
            
        }else{
            this.updateRecordList(null, {recordset:$Db.trm()});
            if(is_first_call==true) this._filterByVocabulary();
        }
        
    },
    
    _filterByVocabulary: function(){

        if(this.options.trm_ParentTermID>0 && this.options.auxilary!='vocabulary'){ 
            
            this.setTitle('Manage Terms: '+$Db.trm(this.options.trm_ParentTermID,'trm_Label'));
           //this.filterRecordList(null, {'trm_ParentTermID':ids, 'sort:trm_Label':1});
            
            var ids = $Db.trm().getAllChildrenIds('trm_ParentTermID',this.options.trm_ParentTermID);
            
            var subset = this.getRecordSet().getSubSetByIds(ids);
            subset = subset.getSubSetByRequest({'sort:trm_Label':1}, this.options.entity.fields);
            this.recordList.resultList('updateResultSet', subset, null);

            if(this.recordTree && this.recordTree.fancytree('instance')){

                //filtered
                var treedata = subset.getTreeViewData('trm_Label', 'trm_ParentTermID',
                                            this.options.trm_ParentTermID);
                var tree = this.recordTree.fancytree('getTree');
                tree.reload(treedata);
            }
        }
    },
    
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width, value, style){
            
            if(!style) style = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                style += (';max-width: '+col_width+';width:'+col_width);
            }
            if(style!='') style = 'style="'+style+'"'; //padding:0px 4px;
            
            if(!value){
                value = recordset.fld(record, fldname);
            }
            return '<div class="item truncate" '+style+'>'+window.hWin.HEURIST4.util.htmlEscape(value)+'</div>';
        }
        
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        var recID   = fld('trm_ID');
        var recTitle = fld2('trm_Label','15em');
/*            + ' : <div class="item" style="font-style:italic;width:45em">'
            + window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, 'trm_Description'))+'</div>'*/


        var recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb');
        
        var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&quot;);">'
        +'</div>';

        var html = '<div class="recordDiv rt_draggable" recid="'+recID+'">'
                    + '<div class="recordSelector item"><input type="checkbox" /></div>'
                    + html_thumb + recTitle 
                    + '<div class="rec_actions">'
                    
                    + this._defineActionButton({key:'edit',label:'Edit', 
                        title:'', icon:'ui-icon-pencil', class:'rec_actions_button'}, //+this.options.auxilary
                        null,'icon_text') 
                    + this._defineActionButton({key:'delete',label:'Remove', 
                        title:'', icon:'ui-icon-delete', class:'rec_actions_button'},
                        null,'icon_text')
                    +'</div></div>';
        
/*        
        var has_buttons = (this.options.select_mode=='manager' && this.options.edit_mode=='popup');

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + html_icon
        + '<div class="recordTitle recordTitle2" title="'+fld('rty_Description')
                        +'" style="right:'+(has_buttons?'60px':'10px')
                        + (this.options.import_structure?';left:30px':'')+'">'
        +     recTitle
        + '</div>';
        
        // add edit/remove action buttons in record lisr
        if(has_buttons){
        
                
               html = html 
                + '<div class="rec_actions" style="top:4px;width:120px;">'
                    + '<div title="Click to edit record type" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;'
                    + '<div title="Click to edit structure" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="structure" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-list"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;'
                    + '<div title="Duplicate record type" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="duplicate" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-copy"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;'
                    + '<div title="List of fields" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="fields" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-b-info"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;'
                    + '<div title="Click to delete record type" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                    + '</div></div>';
        }
        

        html = html + '</div>';
*/
        return html;
        
    },
    
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this term? Proceed?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },
    
    
    //-----
    //
    // adding group ID value for new rectype
    // open select icon dialog for new record
    //
    _afterInitEditForm: function(){
        this._super();
    },   
        
    //
    //
    //
    _triggerRefresh: function( type ){
        window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            { source:this.uuid, type:type });    
    },
    
    //
    // update list after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){

        // close on addition of new record in select_single mode    
        //this._currentEditID<0 && 
        if(this.options.select_mode=='select_single'){
            
                this._selection = new hRecordSet();
                //{fields:{}, order:[recID], records:[fieldvalues]});
                this._selection.addRecord(recID, fieldvalues);
                this._selectAndClose();
                
                
                return;    
                    
        }
        
        this._super( recID, fieldvalues );
        
        if(this.it_was_insert){
            this._loadData()
        }
        this._triggerRefresh('rty');
        
/*        
        this.getRecordSet().setRecord(recID, fieldvalues);
        
        if(this.options.edit_mode == 'editonly'){
            this.closeDialog(true); //force to avoid warning
        }else if(this._currentEditID<0){
            this.searchForm.searchDefRecTypes('startSearch');
        }else{
            this.recordList.resultList('refreshPage');  
        }
*/        
    },
    
    //
    //
    //                                
    changeVocabularyGroup: function(params){                                    
        
        var that = this;
        this._saveEditAndClose( params ,
            function(){
                /*window.hWin.HAPI4.EntityMgr.refreshEntityData('rtg',
                    function(){
                        that._triggerRefresh('rtg');
                    }
                )*/
        });
        
    },
    
    //
    // extend for add-group
    //
    _onActionListener: function(event, action){

        var is_resolved = this._super(event, action);

        if(!is_resolved){

            if(action && action.action){
                recID =  action.recID;
                action = action.action;
            }
            
            if(action=='add-group' || action=='edit-group'){

                if(action=='add-group') recID = -1;

                var that = this;

                var entity_dialog_options = {
                    select_mode: 'manager',
                    edit_mode: 'editonly', //only edit form is visible, list is hidden
                    //select_return_mode:'recordset',
                    rec_ID: recID,
                    selectOnSave:true,
                    onselect:function(res){
                        if(res && window.hWin.HEURIST4.util.isArrayNotEmpty(res.selection)){
                            that._triggerRefresh('vcg');
                            //var vcb_ID = res.selection[0];
                        }
                    }
                };            
                window.hWin.HEURIST4.ui.showEntityDialog('defVocabularyGroups', entity_dialog_options);
            }else if(action=='delete-group' && recID>0){
                
                var request = {
                    'a'          : 'delete',
                    'entity'     : 'defVocabularyGroups',
                    'recID'      : recID                     
                    };
                
                var that = this;                                                
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            $Db.vcg().removeRecord( recID );
                            that._triggerRefresh('vcg');
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                    
            }else if(action=='vocab-import'){
                
            }else if(action=='vocab-export'){
                
            }else if(action=='viewmode-list'){

                if(this.recordTree) this.recordTree.hide();      
                this.recordList.show();
                this.recordList.resultList('applyViewMode','list');
                
            }else if(action=='viewmode-thumbs'){

                if(this.recordTree) this.recordTree.hide();      
                this.recordList.show();
                this.recordList.resultList('applyViewMode','thumbs');
                
            }else if(action=='viewmode-tree'){

                if(!this.recordTree){
                    this.recordTree = $('<div class="ent_content_full" style="display:none;"/>')
                        .addClass('ui-heurist-bg-light')
                        .css({'font-size':'0.8em'})
                        .insertAfter(this.recordList);

                                   //this.getRecordSet()
                    var treedata = this.recordList.resultList('getRecordSet')
                            .getTreeViewData('trm_Label', 'trm_ParentTermID',
                                                 this.options.trm_ParentTermID);
                        
                    this.recordTree.fancytree(
                    {
                        activeVisible:true,
                        checkbox: false,
                        source:treedata,
                        activate: function(event, data){
                            // A node was activated: display its details
                            //_onNodeClick(data);
console.log('activated');
                        },
                    });
                }

                this.recordTree.css({top:this.recordList.position().top}).show();      
                this.recordList.hide();
                
            }

        }
    },
    
    //
    // Import terms into vocabulary
    //
    importTerms: function(vocab_id){
        
        if(!(vocab_id>0)){
            window.hWin.HEURIST4.msg.showMsgFlash('Vocabulary not defined');
            return;
        }
        
        var sURL = window.hWin.HAPI4.baseURL + "import/delimited/importDefTerms.php?db="
            + window.hWin.HAPI4.database +
            "&trm_ID="+vocab_id;

        window.hWin.HEURIST4.msg.showDialog(sURL, {
                "close-on-blur": false,
                "no-resize": false,
                title: 'Import Terms for vocabulary '+$Db.trm(vocab_id,'trm_Label'),
                //height: 200,
                //width: 500,
                height: 600,
                width: 800,
                'context_help':window.hWin.HAPI4.baseURL+'context_help/defTerms.html #import',
                callback: this._importTerms_complete
            });
        
    },
    
    _importTerms_complete: function(vocab_id){
        if(!Hul.isnull(context) && !Hul.isnull(context.terms))
        {
            window.hWin.HEURIST4.msg.showMsgDlg(context.result.length
                + ' term'
                + (context.result.length>1?'s were':' was')
                + ' added.', null, 'Terms imported');
                
            window.hWin.HEURIST4.terms = context.terms;
            var res = context.result;
            //mainParentNode = (context.parent===0)?tree.getRoot():_findNodeById(context.parent);  //_currentNode, //??????
            
            //add records to $Db.trm
            
            //recreate tree view data
            
            //refresh resulList and treeview
    
    
        }
        
    },
    
    //
    //
    /*
    filterRecordList: function( event, request ){
        
        var subset = this._super(event, request);
        

        if(this.options.auxilary!='vocabulary' && this.recordTree && this.recordTree.fancytree('instance')){

            //filtered
            var treedata = subset.getTreeViewData('trm_Label', 'trm_ParentTermID',
                                                 this.options.trm_ParentTermID);
            var tree = this.recordTree.fancytree('getTree');
            tree.reload(treedata);
            
        }
    }*/

    
    
});
