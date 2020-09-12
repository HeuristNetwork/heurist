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
    fields_list_div: null,  //term search result
    
    //
    //                                                  
    //    
    _init: function() {
        
        this.options.innerTitle = false;
        
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
console.log('RELOAD TERMS '+this.options.auxilary);                    
                    that.refreshRecordList();
                    //that._loadData();
                }else
                if(data && data.type == 'vcg' && that.options.auxilary=='vocabulary'){
console.log('RELOAD VOCS');                    
                    that._filterByVocabulary();
                    //that._loadData();
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

        if(this.fields_list_div){
            this.fields_list_div.remove();
        }
        
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
                
                this.main_element = this.element.find('.ent_wrapper:first').addClass('ui-dialog-heurist').css({'left':288});
                
                this.vocabulary_groups = $('<div>').addClass('ui-dialog-heurist')
                    .css({position: 'absolute',top: 0, bottom: 0, left: 0, width:280, overflow: 'hidden'})
                    .uniqueId()
                    .appendTo(this.element);
                
                
                var btn_array = [
                    ];

                this._toolbar = this.searchForm;
                
                $('<div>'
                    +'<h3 style="display:inline-block;margin: 0 10px 0 0; vertical-align: middle;">Vocabularies</h3>'
                    //+'<div id="btn_add_record" style="display:inline-block"></div>'
                  +'</div>'
                  +'<div id="div_group_information" style="padding-top: 13px;width:100%;min-height:3em;clear:both"></div>')
                .appendTo(this.searchForm);
                
                this._defineActionButton2({showText:true, icons:{primary:'ui-icon-plus'},text:window.hWin.HR('Add'),
                          css:{'margin-right':'0.5em','display':'inline-block'}, id:'btnAddButton',
                          click: function() { that._onActionListener(null, 'add'); }}, 
                            this.searchForm.find('div:first'));
                
                this.searchForm.css({'padding-top':this.options.isFrontUI?'8px':'4px', height:80});
                this.recordList.css({ top:80});
                
                
                this.options.recordList = {
                    empty_remark: 'No vocabularies in this groups. Add new one',
                    show_toolbar: false,
                    pagesize:99999,
                    view_mode: 'list',
                    
                    draggable: function(){
                        
                        that.recordList.find('.rt_draggable > .item').draggable({ // 
                                    revert: true,
                                    helper: function(){ 
                                        return $('<div class="rt_draggable ui-drag-drop" recid="'+
                                            $(this).parent().attr('recid')
                                        +'" style="width:300;padding:4px;text-align:center;font-size:0.8em;background:#EDF5FF"'
                                        +'>Drag and drop to group item to change vocabulary group</div>'); 
                                    },
                                    zIndex:100,
                                    appendTo:'body',
                                    scope: 'vcg_change'
                                });   
                    },
                    
                    droppable: function(){
                    
                    that.recordList.find('.recordDiv')  //.recordDiv, ,.recordDiv>.item
                        .droppable({
                            scope: 'vocab_change',
                            hoverClass: 'ui-drag-drop',
                            drop: function( event, ui ){

                                var trg = $(event.target).hasClass('recordDiv')
                                            ?$(event.target)
                                            :$(event.target).parents('.recordDiv');
                                            
                                var trm_ID = $(ui.draggable).parent().attr('recid');
                                var trm_ParentTermID = trg.attr('recid');
                                if(trm_ID>0 && trm_ParentTermID>0 && that.options.reference_trm_manger){
                                    that.options.reference_trm_manger
                                        .manageDefTerms('changeVocabularyGroup',{trm_ID:trm_ID, trm_ParentTermID:trm_ParentTermID });
                                }
                        }});
                }

                };

                this.recordList.uniqueId();
                
                var rg_options = {
                     isdialog: false, 
                     isFrontUI: this.options.isFrontUI,
                     container: that.vocabulary_groups,
                     title: 'Vocabulary groups',
                     layout_mode: 'short',
                     select_mode: 'manager',
                     reference_vocab_manger: that.element,
                     onSelect:function(res){

                         if(window.hWin.HEURIST4.util.isRecordSet(res)){
                            res = res.getIds();                     
                            if(res && res.length>0){
                                //filter by vocabulary
                                that.options.trm_VocabularyGroupID = res[0];
                            }
                         }else if(res>0){
                            that.options.trm_VocabularyGroupID = res; 
                         }else{
                            that.options.trm_VocabularyGroupID = -1  
                         }
                         
                         if(that.getRecordSet()!==null){
                            that._filterByVocabulary();
                         }
                         
                     }
                };
                window.hWin.HEURIST4.ui.showEntityDialog('defVocabularyGroups', rg_options);
                
            }else{
                
        
                //add vocab group and vocabs panels
                this.element.addClass('ui-suppress-border-and-shadow');
                
                this.main_element = this.element.find('.ent_wrapper:first').addClass('ui-dialog-heurist').css({'left':576}); //
                
                this.vocabularies_div = $('<div>').addClass('ui-dialog-heurist')
                    .css({position: 'absolute',top: 0, bottom: 0, left: 0, width:568, overflow: 'hidden'}) //280
                    .uniqueId()
                    .appendTo(this.element);
                
                //show particular terms for vocabulary 
                var btn_array = [
                          
                    {showText:true, icons:{primary:'ui-icon-plus'}, text:window.hWin.HR('Add'),
                          css:{'margin-right':'0.5em','display':'inline-block'}, id:'btnAddButton',
                          click: function() { that._onActionListener(null, 'add'); }},

                    {showText:false, icons:{primary:'ui-icon-arrowthickstop-1-n'}, text:window.hWin.HR('Export Terms'), //ui-icon-arrowthick-1-e
                          css:{'margin-right':'0.5em','display':'inline-block'}, id:'btnExportVocab',
                          click: function() { that._onActionListener(null, 'export-import'); }},
                          
                    {showText:false, icons:{primary:'ui-icon-arrowthickstop-1-s'}, text:window.hWin.HR('Import Terms'), //ui-icon-arrowthick-1-w
                          css:{'margin-right':'0.5em','display':'inline-block'}, id:'btnImportVocab',
                          click: function() { that._onActionListener(null, 'vocab-import'); }}
                    ];
                    
                var btn_array2 = [
                    {showText:false, icons:{primary:'ui-icon-menu'}, text:window.hWin.HR('Show as plain list'),
                          css:{'margin-right':'0.5em','display':'inline-block'}, id:'btnViewMode_List',
                          click: function() { that._onActionListener(null, 'viewmode-list'); }},
                    {showText:false, icons:{primary:'ui-icon-structure'}, text:window.hWin.HR('Show as tree'),
                          css:{'margin-right':'0.5em','display':'inline-block'}, id:'btnViewMode_Tree',
                          click: function() { that._onActionListener(null, 'viewmode-tree'); }},
                    {showText:false, icons:{primary:'ui-icon-view-icons'}, text:window.hWin.HR('Show as images'),
                          css:{'margin-right':'0.5em','display':'inline-block'}, id:'btnViewMode_List',
                          click: function() { that._onActionListener(null, 'viewmode-thumbs'); }}
                    ];

                this._toolbar = this.searchForm;
                
                //padding:'6px'
                this.searchForm.css({'min-width': '315px', 'padding-top':this.options.isFrontUI?'8px':'4px', height:80})
                            .empty();                                     
                this.recordList.css({'min-width': '315px', top:80});
                this.searchForm.parent().css({'overflow-x':'auto'});
                
                $('<div>'
                    +'<h3 style="display:inline-block;margin: 0 10px 0 0; vertical-align: middle;">Terms</h3>'
                  +'</div>'
                  +'<div style="display:table;width:100%;">'
                  +'<div id="div_group_information" style="padding-top:13px;min-height:3em;max-width:350px;display:table-cell;"></div>'
                  +'<div style="min-width:70px;text-align:right;display:table-cell;" id="btn_container"></div></div>')
                .appendTo(this.searchForm);
                
                var c1 = this.searchForm.find('div:first');
                for(var idx in btn_array){
                        this._defineActionButton2(btn_array[idx], c1);
                }
                //add input search
                c1 = $('<div style="float:right"><label>Find: </label>'
                +'<input type="text" style="width:10em" class="text ui-widget-content ui-corner-all"/></div>')
                .appendTo(c1);
                
                this._on(c1.find('input'), {
                    //keypress: window.hWin.HEURIST4.ui.preventChars,
                    keyup: this._onFindTerms }); //keyup
                
                c1 = this.searchForm.find('#btn_container');
                for(var idx in btn_array2){
                        this._defineActionButton2(btn_array2[idx], c1);
                }
                
                this.options.recordList = {
                    empty_remark: 'No terms in selected vocabulary. Add or import new ones',
                    show_toolbar: false,
                    view_mode: 'list',
                    pagesize: 999999,
                    recordview_onselect:'inline',
                    rendererExpandDetails: function(recordset, trm_id){
                        if(this.options.view_mode=='list'){
                            var $rdiv = $(this.element).find('div[recid='+trm_id+']');
                            var ele = $('<div>')
                                .attr('data-recid', $rdiv.attr('recid'))
                                .css({'width':'100%','max-height':'445px','overflow':'hidden','padding-top':'5px','height':'445px'})
                                .addClass('record-expand-info');
                            ele.appendTo($rdiv);
                            
                            that._showEditorInline(ele, trm_id);
                            
                            ele.find('.ent_wrapper:first').css('top',25);
                        }else{
                            that.addEditRecord(trm_id);
                        }
                    },
                    draggable: function(){
                        
                        that.recordList.find('.rt_draggable > .item').draggable({ // 
                                    revert: true,
                                    helper: function(){ 
                                        return $('<div class="rt_draggable ui-drag-drop" recid="'+
                                            $(this).parent().attr('recid')
                                        +'" style="width:300;padding:4px;text-align:center;font-size:0.8em;background:#EDF5FF"'
                                        +'>Drag and drop to vocabulary item to change it</div>'); 
                                    },
                                    zIndex:100,
                                    appendTo:'body',
                                    scope: 'vocab_change'
                                });   
                    }                    
                    
                };
                
                //group options
                var rg_options = {
                     isdialog: false, 
                     isFrontUI: this.options.isFrontUI,
                     container: that.vocabularies_div,
                     title: 'Vocabularies',
                     select_mode: 'manager',
                     reference_trm_manger: that.element,
                     auxilary: 'vocabulary',
                     onSelect:function(res){
                         if(window.hWin.HEURIST4.util.isRecordSet(res)){
                            res = res.getIds();                     
                            if(res && res.length>0){
                                //filter by vocabulary
                                that.options.trm_ParentTermID = res[0];
                            }
                         }else if(res>0){
                             that.options.trm_ParentTermID = res;
                         }else{
                             that.options.trm_ParentTermID = -1;
                         }
                         if(that.getRecordSet()!==null){
                                that._filterByVocabulary();
                         }
                     }
                };
                
                this.recordList.uniqueId();
                
//console.log('init vocabs grp '+this.vocabularies_div.attr('id'));               
                window.hWin.HEURIST4.ui.showEntityDialog('defTerms', rg_options);
                
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
    selectVocabulary: function(vocab_id){
        
        var vcg_ID = $Db.trm(vocab_id, 'trm_VocabularyGroupID');

        if(vcg_ID>0){
            this.vocabulary_groups.manageDefVocabularyGroups('selectRecordInRecordset', vcg_ID);    
            var that = this;
            setTimeout(function(){that.selectRecordInRecordset(vocab_id);},300);
        }
        
    },         
            
    
    //
    //
    /*
    selectedRecords: function(selected_recs){
        
        var res = this._super( selected_recs );            

        if(selected_recs && this.options.auxilary=='vocabulary' && this.options.reference_trm_manger &&
            this.options.reference_trm_manger.manageDefTerms('option','edit_mode')=='inline'){
                      
            //inline editor for vocabularies          
            this._onActionListener(event, {action:'edit'});
        }
        return res;
    },*/
    
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

                    var that = this;
                    var rg_options = {
                         isdialog: false, 
                         isFrontUI: true,
                         container: container,
                         title: 'Edit '+this.options.auxilary,
                         select_mode: 'manager',
                         edit_mode: 'editonly',
                         edit_recordset: this.recordList.resultList('getRecordSet'), //filterd one
                         auxilary: this.options.auxilary,
                         rec_ID: recID,
                         trm_ParentTermID: this.options.trm_ParentTermID,
                         trm_VocabularyGroupID: this.options.trm_VocabularyGroupID,
                         onClose: function(){
                                that.recordList.resultList('closeExpandedDivs');
                         }
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
            
        }else{
            this.updateRecordList(null, {recordset:$Db.trm()});
            //if(is_first_call==true) 
        }
        this._filterByVocabulary();
        
    },
    
    //
    //
    //
    _filterByVocabulary: function(){

        var sGroupTitle = '<h4 style="margin:0">';
        if(this.options.auxilary=='vocabulary'){
            
            if(this.options.trm_VocabularyGroupID>0){

                var vcg_id = this.options.trm_VocabularyGroupID;
                this.filterRecordList(null, {'trm_VocabularyGroupID':vcg_id, 'sort:trm_Label':1});
                
                sGroupTitle += (window.hWin.HEURIST4.util.htmlEscape($Db.vcg(vcg_id,'vcg_Name'))
                                        +'</h4><div class="heurist-helper3 truncate" style="font-size:0.7em">'
                                        + window.hWin.HEURIST4.util.htmlEscape($Db.vcg(vcg_id,'vcg_Description'))
                                        +'</div>');
                
                //initial selection
                var rdiv = this.recordList.find('.recordDiv:first');
                if(rdiv.length){
                    var rec_ID = rdiv.attr('recid');
                    rdiv.click();
                }else if($.isFunction(this.options.onSelect)){
                        this.options.onSelect.call( this, null );
                }
                    
                
            }else{
                sGroupTitle += '</h4>';
            }
            
            
        }else{

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
                    +'</h4><div class="heurist-helper3 truncate" style="font-size:0.7em;">'
                    +$Db.trm(vocab_id,'trm_Description')+'&nbsp;</div>');

            }else{
                this.recordList.resultList('clearAllRecordDivs',null,
                             '<div style="padding: 8px;">Select vocabulary on the left</div>');

                sGroupTitle += '</h4>';
            }
            
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

            html = '<div class="recordDiv rt_draggable" recid="'+recID+'">'
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
            
            ele = this._editing.getFieldByName('trm_VocabularyGroupID');
            if(this.options.trm_VocabularyGroupID>0){
                ele.editing_input('setValue', this.options.trm_VocabularyGroupID, true);
            }

            ele.show();
            ele.editing_input('fset', 'rst_RequirementType', 'required');
            
        }else{
            
            var isRelation = (false);
            if(!isRelation){
                this._editing.getFieldByName('trm_InverseTermId').hide();
            }
            
            ele = this._editing.getFieldByName('trm_ParentTermID');
            ele.editing_input('setValue', this.options.trm_ParentTermID, true);
            
            this._editing.getFieldByName('trm_VocabularyGroupID').hide();
            
        }
        ele = this._editing.getFieldByName('trm_Domain')
        ele.editing_input('setValue', 'enum', true);
        
    },   


    //
    //
    //
    _getEditDialogButtons: function(){
        var btns = this._super();
        
        if(this.options.edit_mode=='editonly'){
        
            var that = this;
          
            btns[0].text = 'Close';
            btns[0].css['visibility'] = 'visible';
            btns[0].class = 'alwaysvisible';
            
            btns[0].click = function(){
                if(that.defaultBeforeClose()){
                    if($.isFunction(that.options.onClose)){
                      that.options.onClose.call();
                    } 
                }
            };
      
        }
        return btns;  
    },
  
/*        
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
*/    
    //
    // update list after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){

        if(this.options.edit_mode=='editonly') return;
        
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
    
    // 
    // this event handler os always called
    // 
    _afterSaveEventHandler2: function( recID, fieldvalues ){  
        
        //if(this.options.edit_mode=='editonly') return;
        
        if(this.it_was_insert){
            if($Db.trm(recID)==null){
                $Db.trm().addRecord(recID, fieldvalues);                
            }
            this._filterByVocabulary();
            //this._loadData();
        }
        this._triggerRefresh(this.options.auxilary);
        
    },
    
    //
    //
    //                                
    changeVocabularyGroup: function(params){                                    
        
        var that = this;
        this._saveEditAndClose( params ,
            function(){
                if(params.trm_ParentTermID>0){
                    that._filterByVocabulary();
                }
                that._triggerRefresh((params.trm_ParentTermID)?'term':'vcg');
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
        
        if(action=='add'){
            
            if(this.options.auxilary=='vocabulary'){
                if(this.options.trm_VocabularyGroupID<0){
                    window.hWin.HEURIST4.msg.showMsgFlash('Vocabulary group is not defined');
                    return true;        
                }
            }else if(this.options.trm_ParentTermID<0){
                    window.hWin.HEURIST4.msg.showMsgFlash('Vocabulary is not defined');                
                    return true;        
            }
            ///this.options.trm_VocabularyGroupID = recID;
            this.addEditRecord(-1);        
            return true;
        }
        //this.options.trm_VocabularyGroupID = -1;

        var is_resolved = this._super(event, keep_action);

        if(!is_resolved){
/*
            if(action && action.action){
                recID =  action.recID;
                action = action.action;
            }
*/            
            var that = this;
/*
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
                
                
            }else 
*/            
            if(action=='vocab-import'){
                
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


    //
    // show dropdown for field suggestions to be added
    //
    _onFindTerms: function(event){
        
        var input_name = $(event.target);
        
        if(this.fields_list_div == null){
            //init for the first time
            this.fields_list_div = $('<div class="list_div" '
                +'style="z-index:999999999;height:auto;max-height:200px;padding:4px;cursor:pointer;overflow-y:auto"></div>')
                .css({border: '1px solid rgba(0, 0, 0, 0.2)', margin: '2px 0px', background:'#F4F2F4'})
                .appendTo(this.element);
            this.fields_list_div.hide();
            
            this._on( $(document), {click: function(event){
               if($(event.target).parents('.list_div').length==0) { 
                    this.fields_list_div.hide(); 
               };
            }});
        }
        
        var setdis = input_name.val().length<3;

        //find terms
        if(input_name.val().length>1){
           
            var term_name, term_code, is_added = false;
                
            this.fields_list_div.empty();  
            
            var that = this;
            
            var entered = input_name.val().toLowerCase();
                            
            //find among fields that are not in current record type
            $Db.trm().each(function(trm_ID, rec){

                term_name = $Db.trm(trm_ID, 'trm_Label');
                term_code  = $Db.trm(trm_ID, 'trm_Code');

                if(term_name.toLowerCase().indexOf( entered )>=0 || 
                   (term_code && term_code.toLowerCase().indexOf( entered )>=0))
                {
                    var ele = $('<div class="truncate">').appendTo(that.fields_list_div);
                    
                    //find parents
                    var s = '', ids = [trm_ID];
                    do{
                        var term_parent = $Db.trm(trm_ID, 'trm_ParentTermID');
                        if(term_parent>0){
                            ids.push(term_parent);
                            s = $Db.trm(term_parent, 'trm_Label') + ' - ' + s;
                        }
                        trm_ID = term_parent;
                    }while(term_parent>0);
                    
                    //get vocabulary group
                    //var vcg_ID = $Db.trm(trm_ID, 'trm_VocabularyGroupID');
                    //ids.push(vcg_ID);
                    

                    is_added = true;
                    ele.attr('trm_IDs',ids.join(','))
                    .text( s + term_name + (term_code?(' ('+term_code+')'):'') )
                    .click( function(event){
                        window.hWin.HEURIST4.util.stopEvent(event);

                        var ele = $(event.target).hide();
                        var trm_IDs = ele.attr('trm_IDs').split(',');
                        
                        if(trm_IDs){
                            that.fields_list_div.hide();
                            
                            //select vocabulary group, select vocab
                            var vocab_id  = trm_IDs[trm_IDs.length-1];
                            that.vocabularies_div.manageDefTerms('selectVocabulary', vocab_id);
                            if(trm_IDs.length>1){
                                setTimeout(function(){that.selectRecordInRecordset(trm_IDs[0]);},500);    
                            }
                            

        
                            input_name.val('').focus();
                        }
                    });

                }                
            });


            if(is_added){
                this.fields_list_div.position({my:'right top', at:'right bottom', of:input_name})
                    //.css({'max-width':(maxw+'px')});
                    .css({'max-width':input_name.width()+120});
                this.fields_list_div.show();    
            }else{
                this.fields_list_div.hide();
            }

      }else{
            this.fields_list_div.hide();  
      }

    },
    
    
});
