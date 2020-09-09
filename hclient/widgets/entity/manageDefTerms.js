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
        if(this.options.auxilary=='vocabulary'){
            this.options.edit_height = 440;
        }else{
            this.options.edit_height = 660;

        }
        
        this._super();
        
        var that = this;
        
        
        window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
        function(data) { 
            
                if(!data || 
                   (data.source != that.uuid && data.type == this.options.auxilary))
                {
                    that._loadData();
                }else
                if(data && data.type == 'vocabulary' && that.options.auxilary=='vocabulary'){

                
                }else
                if(data && data.type == 'vcg' && that.options.auxilary=='vocabulary'){
                                     
                    //update vocabulary groups
                    if(data.recID>0){
                        var recID = data.recID;
                        var is_delete = ($Db.vcg(recID)==null);
                        var cont = that.recordList.find('.div-result-list-content');
                        if(is_delete){
                            cont.find('div[data-grp='+recID+']').remove();
                            cont.find('div[data-grp-content='+recID+']').remove();
                        }else {
                            var header = cont.find('div[data-grp='+recID+']');
                            var is_insert = (header.length==0);
                            var content = that.rendererVocabularyGroupHeader(recID, false);
                            if(is_insert){
                                header = $(content).appendTo(cont);
                                $('<div data-grp-content="'+recID+'">empty</div>').hide().appendTo(cont);
                            }else{
                                $(header).replaceWith($(content));
                            }
                            that.initVocabularyGroups(recID);
                        }
                        
                    }else{
                        //relaod all
                        that.recordList.resultList({groupByRecordset:$Db.vcg()});    
                        that.recordList.resultList('refreshPage');
                    }
                }
                
            
        });
        
/*        
        $(this.document).on(
            window.hWin.HAPI4.Event.ON_REC_UPDATE
            + ' ' + window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(e, data) { 
            });
*/        
        
    },
    
    _destroy: function() {
console.log( '_destroy' );
       window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);     
/*        
       $(window.hWin.document).off(
            window.hWin.HAPI4.Event.ON_REC_UPDATE
            + ' ' + window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
*/            
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
            this._initEditorOnly( this.options.edit_recordset );
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
                this.searchForm.css({'padding-top': this.options.innerTitle?'8px':'4px' }).empty();
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
                    droppable: function(){that.initVocabularyGroups();}, //called after page render 
                    groupByField:'trm_VocabularyGroupID',
                    groupOnlyOneVisible: false,
                    groupByRecordset: $Db.vcg(),
                    //groupByCss:'0 1.5em',
                    rendererGroupHeader: this.rendererVocabularyGroupHeader,
                    
                    
                };

                this.recordList.uniqueId();
                
            }else{
                
        
                //if(this.options.innerTitle && !this.options.auxilary){
            
                //add record type group editor  3px solid red
                this.element.css( {border:'none', 'box-shadow':'none', background:'none'} );
                
                this.main_element = this.element.find('.ent_wrapper:first').addClass('ui-dialog-heurist').css({'left':288});
                
                this.vocabulary_groups = $('<div>').addClass('ui-dialog-heurist')
                    .css({position: 'absolute',top: 0, bottom: 0, left: 0, width:280, overflow: 'hidden'})
                    .uniqueId()
                    .appendTo(this.element);
                
                //console.log('vocab cont '+this.vocabulary_groups.attr('id'));               
                
                //min width for editor 480
                this.inline_editor_container = $('<div>').addClass('ui-dialog-heurist')
                    .css({position: 'absolute',top: 0, bottom: 0, right: 0, left:746, overflow: 'hidden'})  //606px
                    .hide()
                    .uniqueId()
                    .appendTo(this.element);
                
                //show particular terms for vocabulary 
                var btn_array = [
                    {showText:false, icons:{primary:'ui-icon-carat-2-w'}, text:window.hWin.HR('Show inline Editor'),
                          css:{'margin-right':'0.5em','float':'right'}, id:'btnExpandEditor',
                          click: function() { that._expandInlineEditor(); }},
                          
                    {showText:true, icons:{primary:'ui-icon-plus'}, text:window.hWin.HR('Add Term'),
                          css:{'margin-right':'0.5em','float':'right'}, id:'btnAddButton',
                          click: function() { that._onActionListener(null, 'add'); }},

                    {showText:true, icons:{primary:'ui-icon-arrowthick-1-e'}, text:window.hWin.HR('Export Terms'),
                          css:{'margin-right':'0.5em','float':'right'}, id:'btnExportVocab',
                          click: function() { that._onActionListener(null, 'export-import'); }},
                          
                    {showText:true, icons:{primary:'ui-icon-arrowthick-1-w'}, text:window.hWin.HR('Import Terms'),
                          css:{'margin-right':'0.5em','float':'right'}, id:'btnImportVocab',
                          click: function() { that._onActionListener(null, 'vocab-import'); }},
                    
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
                
                //padding:'6px'
                this.searchForm.css({'min-width': '315px', 'padding-top':this.options.innerTitle?'8px':'4px', height:80})
                            .empty();                                     
                this.recordList.css({'min-width': '315px', top:80});
                this.searchForm.parent().css({'overflow-x':'auto'});
                $('<div>').attr('id','div_group_information').css({'max-height':'40px','max-height':'40px'}).appendTo(this.searchForm);
                
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
                     innerTitle: that.options.innerTitle,
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
                
                
                if(this._innerTitle){
                    this.btn_inine_editor = $('<span class="ui-icon ui-icon-carat-2-w" style="cursor:pointer;float:right;margin:0px 6px">'
                        +'Inline Editor</span>').appendTo(this._innerTitle);
                    
                    this._on(this.btn_inine_editor, {click: this._expandInlineEditor});
                }else{
                    this.btn_inine_editor = this._toolbar.find('#btnExpandEditor'); 
                    if(this.options.isdialog){
                        this._as_dialog.parents('.ui-dialog').find('.ui-dialog-buttonpane').hide();    
                    }
                }
                    
                    
            }

        
        }


//console.log('init list '+this.options.auxilary+'  '+this.recordList.attr('id'));                    
        
        this.recordList.resultList( this.options.recordList );
            
            
        that._loadData(true);
        
        return true;
    },            
    
    //
    //
    //
    rendererVocabularyGroupHeader: function(grp_val, is_expanded){

            var desc = $Db.vcg(grp_val, 'vcg_Description');
            if(!desc) desc = '';

            return '<div class="groupHeader" data-grp="'+grp_val
                +'" style="font-size:0.9em;padding:4px 0 4px 0px;border-bottom:1px solid lightgray">'
                +'<span style="display:table-cell;vertical-align:top;padding:2px;" '
                        +'class="expand_button ui-icon ui-icon-triangle-1-'+(is_expanded?'s':'e')+'"></span>'
                +'<div style="display:table-cell;width:180px">'
                +'<div style="font-weight:bold;margin:0;line-height:19px">'
                +$Db.vcg(grp_val, 'vcg_Name')+'</div>'
                 //+grp_val+' '
                +'<div style="padding-top:4px;font-size:smaller;"><i>'+desc+'</i></div></div>'
                +'<div style="min-width:70px" class="action-button-container"></div>'
                +'</div>';
    },
    
    //
    //
    //
    initVocabularyGroups: function(grp_val){
        
        var that = this;
        
            var conts = that.recordList.find('div[data-grp'+(grp_val>0?('='+grp_val):'')+']');
            $.each(conts, function(i, cont){
                var grp_val = $(cont).attr('data-grp');
                cont = $(cont).find('.action-button-container')
                that._defineActionButton({key:'vocab-add',label:'Add Vocaulary', title:'', icon:'ui-icon-plus', 
                        recid:grp_val}, cont, 'small');
                that._defineActionButton({key:'edit-group',label:'Edit group', title:'', icon:'ui-icon-pencil', 
                        recid:grp_val}, cont, 'small');
                that._defineActionButton({key:'delete-group',label:'Remove group', title:'', icon:'ui-icon-delete', 
                        recid:grp_val}, cont, 'small'); //class:'rec_actions_button', 
            });
            
            var conts = that.recordList.find('div[data-grp]');

            //to accept DnD of vocabularies            
            conts.find('div[data-grp]')  //.recordDiv, ,.recordDiv>.item
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
                
                window.hWin.HEURIST4.dbs.applyOrder(recset,'vcg');

            }});
                        
            
    },
    
    //
    //
    //
    _expandInlineEditor: function(){
        
        var isExpanded = this.btn_inine_editor.button('instance')
                            ? (this.btn_inine_editor.button('option','icon')=='ui-icon-carat-2-e')
                            : this.btn_inine_editor.hasClass('ui-icon-carat-2-e');
        var iWidth = 0;
        
        if(isExpanded){
            ///hide
            if(this.btn_inine_editor.button('instance')){
                this.btn_inine_editor.button({icon:'ui-icon-carat-2-w'});
            }else{
                this.btn_inine_editor.removeClass('ui-icon-carat-2-e')
                                 .addClass('ui-icon-carat-2-w');
            }
            this.main_element.css('width', 'auto');
            this.inline_editor_container.hide();                 
            
            this.options.edit_mode = "popup";

            //this.editForm.appendTo(this.element.find('.editFormContainer')); 
            
        }else{
            //show
            if(this.btn_inine_editor.button('instance')){
                this.btn_inine_editor.button({icon:'ui-icon-carat-2-e'});
            }else{
                this.btn_inine_editor.removeClass('ui-icon-carat-2-w')
                                 .addClass('ui-icon-carat-2-e');
            }
                                 
            iWidth = 450; //310
            this.inline_editor_container.css('left', iWidth+296);  //746 or 626
            
            if(this.inline_editor_container.width()<600){
                iWidth = 330
                this.inline_editor_container.css('left', iWidth+296);  //746 or 626
            }
            
            this.main_element.css('width', iWidth);
            this.inline_editor_container.show();     
            
            this.options.edit_mode = "inline";
            //this.editForm.appendTo(this.inline_editor_container);
            this._onActionListener(null, 'edit');
        }
        
        if(this.main_element.width()<450){
            this.main_element.find('#btnImportVocab').button({showLabel:false});
            this.main_element.find('#btnExportVocab').button({showLabel:false});
        }else{
            this.main_element.find('#btnImportVocab').button({showLabel:true});
            this.main_element.find('#btnExportVocab').button({showLabel:true});
        }
    },

    //
    //
    //
    selectedRecords: function(selected_recs){
        
        var res = this._super( selected_recs );            

        if(selected_recs && this.options.auxilary=='vocabulary' && this.options.reference_trm_manger &&
            this.options.reference_trm_manger.manageDefTerms('option','edit_mode')=='inline'){
                      
            //inline editor for vocabularies          
            this._onActionListener(event, {action:'edit'});
            /*
            var container  = this.options.reference_trm_manger.manageDefTerms('getEditorInline');
            this._showEditorInline(container, recID);
            */
        }

        
        return res;
    },
    
    //
    //
    //
    addEditRecord: function(recID)
    {   
        if(this.options.auxilary=='vocabulary' && this.options.reference_trm_manger &&
            this.options.reference_trm_manger.manageDefTerms('option','edit_mode')=='inline'){
                      
            //inline editor for vocabularies          
                        
            var container  = this.options.reference_trm_manger.manageDefTerms('getEditorInline');
            this._showEditorInline(container, recID);
                        
        }else if(this.options.edit_mode == "inline"){

            //inline editor for terms
            
            this._showEditorInline(this.inline_editor_container, recID);
            
        }else{
            this._super( recID );            
        }
    },
    
    //
    //
    //
    getEditorInline: function(){
        return this.inline_editor_container;
    },
    
    //
    //
    //
    _showEditorInline: function(container, recID){
        
                
                if(container.manageDefTerms('instance') 
                    && container.manageDefTerms('option','auxilary')==this.options.auxilary)
                {
                        
                    container.manageDefTerms('option','edit_recordset', this.recordList.resultList('getRecordSet'));
                    container.manageDefTerms('option','trm_ParentTermID', this.options.trm_ParentTermID);
                    container.manageDefTerms('option','trm_VocabularyGroupID', this.options.trm_VocabularyGroupID);
                    container.manageDefTerms( 'addEditRecord', recID );
                    
                }else{
                    var rg_options = {
                         isdialog: false, 
                         innerTitle: this.options.innerTitle,
                         container: container,
                         title: 'Edit '+this.options.auxilary,
                         select_mode: 'manager',
                         edit_mode: 'editonly',
                         edit_recordset: this.recordList.resultList('getRecordSet'), //filterd one
                         auxilary: this.options.auxilary,
                         rec_ID: recID,
                         trm_ParentTermID: this.options.trm_ParentTermID,
                         trm_VocabularyGroupID: this.options.trm_VocabularyGroupID
                    };
                    window.hWin.HEURIST4.ui.showEntityDialog('defTerms', rg_options); // it recreates  
                }
                
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
                rdiv.parent().prev().find('.expand_button')
                    .removeClass('ui-icon-triangle-1-e').addClass('ui-icon-triangle-1-s');
                rdiv.click();
                //this.recordList.resultList('setSelected', [rec_ID]);
            }
            
        }else{
            this.updateRecordList(null, {recordset:$Db.trm()});
            //if(is_first_call==true) 
            this._filterByVocabulary();
        }
        
    },
    
    _filterByVocabulary: function(){

        if(this.options.auxilary=='vocabulary') return;

        var sGroupTitle = '<h4 style="margin:0">';
        if(this.options.trm_ParentTermID>0){ 

            var vocab_id = this.options.trm_ParentTermID;

            this.setTitle('Manage Terms: '+$Db.trm(vocab_id,'trm_Label'));
            //this.filterRecordList(null, {'trm_ParentTermID':ids, 'sort:trm_Label':1});

            var ids = $Db.trm().getAllChildrenIds('trm_ParentTermID',vocab_id);

            var subset = this.getRecordSet().getSubSetByIds(ids);
            subset = subset.getSubSetByRequest({'sort:trm_Label':1}, this.options.entity.fields);
            this.recordList.resultList('updateResultSet', subset, null);

            if(this.recordTree && this.recordTree.fancytree('instance')){

                //filtered
                var treedata = subset.getTreeViewData('trm_Label', 'trm_ParentTermID',
                    vocab_id);
                var tree = this.recordTree.fancytree('getTree');
                tree.reload(treedata);
            }

            sGroupTitle += ($Db.trm(vocab_id,'trm_Label')
                +'</h5><div class="heurist-helper3" style="font-size:0.7em;height:23px;overflow:hidden;">'
                +$Db.trm(vocab_id,'trm_Description')+'&nbsp;</div>');

        }else{
            sGroupTitle += 'Terms and Relation types</h4>';
        }
        this.searchForm.find('#div_group_information').html(sGroupTitle);

    },
    
    _recordListItemRenderer:function(recordset, record){
        
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        var recID = recordset.fld(record, 'trm_ID');
        var recTitle = '<div class="item truncate" style="max-width:220px;width:220px">'
            +window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, 'trm_Label'))+'</div>';

        var html;
        
        if(this.options.auxilary=='vocabulary'){
            
            html = '<div class="recordDiv rt_draggable" style="padding-right:0" recid="'+recID+'">'
                    + '<div class="recordSelector item"><input type="checkbox" /></div>'
                    + recTitle 
                    + this._defineActionButton({key:'edit',label:'Edit Vocabulary', 
                        title:'', icon:'ui-icon-pencil',class:'rec_actions_button'}, 
                        null,'icon_text') 
                    + this._defineActionButton({key:'delete',label:'Remove Vocabulary', 
                        title:'', icon:'ui-icon-delete',class:'rec_actions_button'},
                        null,'icon_text')
                    + '<div class="selection_pointer" style="display:table-cell">'
                        +'<span class="ui-icon ui-icon-carat-r"></span></div>'
                    +'</div>';
            
        }else{

            var recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb');
            
            var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'
                                    +recThumb+'&quot;);"></div>';

            html = '<div class="recordDiv" recid="'+recID+'">'
                    + '<div class="recordSelector item"><input type="checkbox" /></div>'
                    + html_thumb + recTitle 
                    + '<div class="rec_actions">'
                    + this._defineActionButton({key:'edit',label:'Edit Term', 
                        title:'', icon:'ui-icon-pencil',class:'rec_actions_button'},
                        null,'icon_text') 
                    + this._defineActionButton({key:'delete',label:'Remove Term', 
                        title:'', icon:'ui-icon-delete',class:'rec_actions_button'},
                        null,'icon_text')
                    +'</div></div>';
        }
        
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
    
    //
    //
    //
    _afterDeleteEvenHandler: function(recID){
        this._super(recID); 
        
        $Db.trm().removeRecord(recID);  
    },
    
    
    //-----
    //
    // show hide some elements on edit form according to type: 
    //    vocab/term and enum/relation
    //
    _afterInitEditForm: function(){
        this._super();
        
        var ele;
        var isVocab = !(this.options.trm_ParentTermID>0);
        if(isVocab){
            
            if(this._edit_dialog && this._edit_dialog.dialog('instance')){
                this._edit_dialog.dialog('option', 'title', 'Edit Vocabulary');
            }
        
            //hide fields for vocab    
            this._editing.getFieldByName('trm_InverseTermId').hide();
            this._editing.getFieldByName('trm_Code').hide();
            this._editing.getFieldByName('trm_Thumb').hide();

            //change label            
            this._editing.getFieldByName('trm_Label').find('.header').text('Vocabulary Name');
            
            //assign devault values
            
            if(this.options.trm_VocabularyGroupID>0){
                ele = this._editing.getFieldByName('trm_VocabularyGroupID');
                ele.editing_input('setValue', this.options.trm_VocabularyGroupID, true);
            }
            
        }else{
            
            var isRelation = (false);
            if(!isRelation){
                this._editing.getFieldByName('trm_InverseTermId').hide();
            }
            
            ele = this._editing.getFieldByName('trm_ParentTermID');
            ele.editing_input('setValue', this.options.trm_ParentTermID, true);
            
            ele = this._editing.getFieldByName('trm_VocabularyGroupID').hide();
            ele.editing_input('fset', 'rst_RequirementType', 'optional');
            
            
        }
        ele = this._editing.getFieldByName('trm_Domain')
        ele.editing_input('setValue', 'enum', true);
        
    },   
        
    //
    //  Add PREV/NEXT buttons
    //    
    _getEditDialogButtons: function(){
        var btns = this._super();
        
        if(this.options.auxilary!=='vocabulary'){
        
            var recset = this.recordList.resultList('getRecordSet');
            var isNavVisible = (recset && recset.length()>1);
            
    //console.log( isNavVisible + recset.length());        

            var that = this;
            
            btns.push({showText:false, icons:{primary:'ui-icon-circle-triangle-w'},text:window.hWin.HR('Previous'),
                                  css:{'display':isNavVisible?'inline-block':'none','margin':'0.5em 0.4em 0.5em 0px'}, id:'btnPrev',
                                  click: function( recID, fields ) { 
                                        //that._afterSaveEventHandler( recID, fields ); 
                                        that._navigateToRec(-1); 
                                  }});
                                  
            btns.push({showText:false, icons:{secondary:'ui-icon-circle-triangle-e'},text:window.hWin.HR('Next'),
                                  css:{'display':isNavVisible?'inline-block':'none','margin':'0.5em 0.4em 0.5em 0px','margin-right':'1.5em'}, id:'btnNext',
                                  click: function( recID, fields ) { 
                                        //that._afterSaveEventHandler( recID, fields ); 
                                        that._navigateToRec(1); 
                                  }});
        }
        return btns;
        
    },
    
    //
    //
    //
    _navigateToRec: function(dest){
        if(this._currentEditID>0){
                var recset = this.recordList.resultList('getRecordSet');
                var order  = recset.getOrder();
                var idx = window.hWin.HEURIST4.util.findArrayIndex(this._currentEditID, order);//order.indexOf(Number(this._currentEditID));
                idx = idx + dest;
                if(idx>=0 && idx<order.length){
                    
                    var newRecID = order[idx];
                    var that = this;
                    
                    if(this._editing.isModified()){
  
                        that.addEditRecord(newRecID);
                        
                    }else if(this._toolbar) {
                        //this._toolbar.find('#divNav').html( (idx+1)+'/'+order.length);
                        
                        window.hWin.HEURIST4.util.setDisabled(this._toolbar.find('#btnPrev'), (idx==0));
                        window.hWin.HEURIST4.util.setDisabled(this._toolbar.find('#btnNext'), (idx+1==order.length));
                        
                        if(dest!=0){
                            this.addEditRecord(newRecID);
                        }
                    }
                }
        }
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
    },
    
    _afterSaveEventHandler2: function( recID, fieldvalues ){  
        if(this.it_was_insert){
            if($Db.trm(recID)==null){
                $Db.trm().addRecord(recID, fieldvalues);                
            }
            this._loadData();
        }
        this._triggerRefresh(this.options.auxilary);
        
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
    // extend for group actions
    //
    _onActionListener: function(event, action){
        
        var keep_action = action;
        if(action && action.action){
            recID =  action.recID;
            action = action.action;
        }
        
        if(action=='vocab-add' && recID>0){
            this.options.trm_VocabularyGroupID = recID;
            this.addEditRecord(-1);        
            return true;
        }
        this.options.trm_VocabularyGroupID = -1;
        

        var is_resolved = this._super(event, keep_action);

        if(!is_resolved){
/*
            if(action && action.action){
                recID =  action.recID;
                action = action.action;
            }
*/            
            var that = this;

            if(action=='add-group' || action=='edit-group'){

                if(action=='add-group') recID = -1;

                var entity_dialog_options = {
                    select_mode: 'manager',
                    edit_mode: 'editonly', //only edit form is visible, list is hidden
                    //select_return_mode:'recordset',
                    title: (action=='add-group'?'Add':'Edit')+' Vocabulary Group',
                    rec_ID: recID,
                    selectOnSave:true,
                    onselect:function(res){
                        if(res && window.hWin.HEURIST4.util.isArrayNotEmpty(res.selection)){
                            //that._triggerRefresh('vcg', recID);
                            //var vcb_ID = res.selection[0];
                        }
                    }
                };            
                window.hWin.HEURIST4.ui.showEntityDialog('defVocabularyGroups', entity_dialog_options);
            }else if(action=='delete-group' && recID>0){
                
                window.hWin.HEURIST4.msg.showMsgDlg(
                        'Are you sure you wish to delete this vocabulary type? Proceed?', function(){
                            
                            var request = {
                                'a'          : 'delete',
                                'entity'     : 'defVocabularyGroups',
                                'recID'      : recID                     
                                };
                            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                function(response){
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        $Db.vcg().removeRecord( recID );
                                        that._triggerRefresh('vcg', recID);
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                    }
                                });
                            
                        }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
                
                
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
console.log('NODE activated');
                        },
                    })
                    .css({'font-weight':'normal !important'});
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
