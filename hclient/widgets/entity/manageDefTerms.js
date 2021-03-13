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

    /*
    options
    initial_filter - initial vocabulary ID
    filter_groups -  restrict domain: null-any, enum or relation

    */

    //options for special mode 
    // select_mode_reference - reference | inverse 
    // select_mode_target  - name of target term or vocabulary (for header at the top of search form)


    _entityName: 'defTerms',
    fields_list_div: null,  //term search result
    scrollInterval: 0,
    
    vocabulary_groups:null, //reference from vocabs tp group widget
    vocabularies_div:null, //reference from terms to vocabulary widget
    
    last_added_vocabulary: 0,

    //
    //                                                  
    //    
    _init: function() {

        this.options.default_palette_class = 'ui-heurist-design';

        if(!window.hWin.HEURIST4.ui.collapsed_terms){
            window.hWin.HEURIST4.ui.collapsed_terms = [];
        }

        this.options.innerTitle = false;

        this.options.use_cache = true;
        this.options.use_structure = false

        if(!this.options.layout_mode) this.options.layout_mode = 'short';
        if(this.options.auxilary!='vocabulary') this.options.auxilary='term';

        if(this.options.edit_mode=='editonly'){
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
        }else{

            if(this.options.isFrontUI &&
                this.options.select_mode=='manager' && this.options.auxilary!='vocabulary'){
                //window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, {});                   
                $('<div>').addClass('coverall-div').css({'zIndex':60000, 
                    'background-color':'#fff', opacity:1}).appendTo(this.element);
            }

        }
        /*
        if(this.options.auxilary=='vocabulary'){
        this.options.edit_height = 440;
        }else{
        this.options.edit_height = 660;
        }*/
        this.options.edit_height = 440;
        this.options.edit_width = (this.options.auxilary=='term')?550:610;

        this._super();

        var that = this;

        this.editForm.css({'top':0, 'padding-top':'20px'});

        window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(data) { 

                if(!data || 
                    (data.source != that.uuid && data.type == this.options.auxilary))
                {
                    that.refreshRecordList();
                }else
                    if(data && (((data.type == 'vcg'||data.type == 'vocabulary') && that.options.auxilary=='vocabulary') 
                        || ((data.type == 'vocabulary'||data.type == 'term') && that.options.auxilary=='term')
                    )){
                        that._filterByVocabulary();
                    }


        });

    },

    _destroy: function() {

        if(this.fields_list_div){
            this.fields_list_div.remove();
        }

        window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);     

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

        this.usrPreferences = this.getUiPreferences();

        if(this.options.edit_mode=='editonly'){
            this._initEditorOnly( this.options.edit_recordset );
            return;
        }

        //init viewer 
        var that = this;

        if(this.options.select_mode=='manager'){

            this.searchForm.css({padding:0});

            if(this.options.auxilary=='vocabulary'){
                //vocabulary groups

                this.main_element = this.element.find('.ent_wrapper:first').addClass('ui-dialog-heurist').css({'left':248});

                this.vocabulary_groups = $('<div>').addClass('ui-dialog-heurist')
                .css({position: 'absolute',top: 0, bottom: 0, left: 0, width:240, overflow: 'hidden'})
                .uniqueId()
                .appendTo(this.element);


                var btn_array = [
                ];

                this._toolbar = this.searchForm;

                $('<div id="div_group_information" '
                    +'style="vertical-align: middle;width: 100%;min-height: 32px; border-bottom: 1px solid gray; clear: both;"></div>'
                    +'<div class="action-buttons" style="height:40px;background:white;padding:10px 8px;">'
                    +'<h4 style="display:inline-block;margin: 0 10px 0 0; vertical-align: middle;">Vocabularies</h4></div>')
                .appendTo(this.searchForm);

                var btn_array = [

                    {showText:true, icons:{primary:'ui-icon-plus'}, text:window.hWin.HR('Add'), //Add Vocab
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, id:'btnAddButton',
                        click: function() { that._onActionListener(null, 'add'); }},
                    {showText:false, icons:{primary:'ui-icon-download'}, text:window.hWin.HR('Export Vocabularies'),
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, id:'btnExportVocab',
                        click: function() { that._onActionListener(null, 'term-export'); }},
                    {showText:false, icons:{primary:'ui-icon-upload',padding:'2px'}, text:window.hWin.HR('Import Vocabularies'),
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, id:'btnImportVocab',
                        click: function() { that._onActionListener(null, 'term-import'); }}
                ];

                //add, import buttons
                var c1 = this.searchForm.find('div.action-buttons');
                for(var idx in btn_array){
                    this._defineActionButton2(btn_array[idx], c1);
                }



                this.searchForm.css({'padding-top':this.options.isFrontUI?'6px':'4px', height:80});
                this.recordList.css({ top:80});


                this.options.recordList = {
                    empty_remark: 'No vocabularies in this group',
                    show_toolbar: false,
                    pagesize:99999,
                    recordDivEvenClass:'', //suppress highlight for even lines
                    view_mode: 'list',

                    draggable: function(){ //function is called after finish render and assign draggable widget for all record divs

                        that.recordList.find('.rt_draggable > .item').draggable({ // 
                            revert: 'invalid',
                            helper: function(){ 
                                return $('<div class="rt_draggable ui-drag-drop" recid="'+
                                    $(this).parent().attr('recid')
                                    +'" style="width:300;padding:4px;text-align:center;font-size:0.8em;background:#EDF5FF"'
                                    +'>Drag and drop to group item to change vocabulary group</div>'); 
                            },
                            zIndex:100,
                            appendTo:'body',
                            containment: 'window',
                            scope: 'vcg_change'
                        });   
                    },

                    droppable: function(){

                        that.recordList.find('.recordDiv') // change vocabualry for term
                        .droppable({                  
                            scope: 'vocab_change',
                            hoverClass: 'ui-drag-drop',
                            drop: function( event, ui ){

                                var trg = $(event.target).hasClass('recordDiv')
                                ?$(event.target)
                                :$(event.target).parents('.recordDiv');

                                var trm_ID = $(ui.draggable).parent().attr('recid');
                                var vocab_id = trg.attr('recid');
                                if(trm_ID>0 && vocab_id>0 && that.options.reference_trm_manger){

                                    if(that.options.reference_trm_manger.find('#rbDnD_merge').is(':checked')){
                                        window.hWin.HEURIST4.msg.showMsgFlash('Merge is allowed within vocabulary only');
                                        return false;  
                                    } 

                                    that.options.reference_trm_manger
                                    .manageDefTerms('changeVocabularyGroup',{trm_ID:trm_ID, trm_ParentTermID:vocab_id });
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
                                //filter by vocabulary group
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

                    },
                    add_to_begin: true
                };

                
                //initially selected vocabulary
                if(this.options.selection_on_init=='add_new'){
                    this.options.selection_on_init = null;
                    this.addEditRecord(-1);
                }else  if(this.options.selection_on_init>0){
                    //is vocabulary
                    var vcg_ID = $Db.trm(this.options.selection_on_init, 'trm_VocabularyGroupID');
                    rg_options['selection_on_init'] = vcg_ID; //select group
                }

                window.hWin.HEURIST4.ui.showEntityDialog('defVocabularyGroups', rg_options);

            }
            else{ //terms ------------------------------------------

                //add vocab group and vocabs panels
                this.element.addClass('ui-suppress-border-and-shadow');

                this.main_element = this.element.find('.ent_wrapper:first').addClass('ui-dialog-heurist').css({'left':544}); //

                this.vocabularies_div = $('<div>').addClass('ui-dialog-heurist')
                .css({position: 'absolute',top: 0, bottom: 0, left: 0, width:536, overflow: 'hidden'}) //280
                .uniqueId()
                .appendTo(this.element);

                if(this.options.innerCommonHeader){
                    $(this.options.innerCommonHeader).css({position:'absolute',height:'38px',left:0,right:0,top:0,
                        'text-align': 'center',background: 'white','line-height': '38px'}).appendTo(this.element);
                    this.main_element.css('top','38px');
                    this.vocabularies_div.css('top','38px');
                }
                //show particular terms for vocabulary 
                var btn_array = [

                    {showText:true, icons:{primary:'ui-icon-plus'}, text:window.hWin.HR('Add'), //Add Term
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, id:'btnAddButton',
                        click: function() { that._onActionListener(null, 'add'); }},

                    {showText:true, icons:{primary:'ui-icon-link'}, text:window.hWin.HR('Ref'), //Add Term
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, id:'btnAddButton2',
                        click: function() { that._onActionListener(null, 'add-reference'); }},

                    {showText:false, icons:{primary:'ui-icon-download'}, text:window.hWin.HR('Export Terms'), //ui-icon-arrowthick-1-e
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, id:'btnExportVocab',
                        click: function() { that._onActionListener(null, 'term-export'); }},

                    {showText:false, icons:{primary:'ui-icon-upload',padding:'2px'}, text:window.hWin.HR('Import Terms'), //ui-icon-arrowthick-1-w
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, id:'btnImportVocab',
                        click: function() { that._onActionListener(null, 'term-import'); }}
                ];

                var btn_array2 = [
                    {showText:false, icons:{primary:'ui-icon-menu'}, text:window.hWin.HR('Show as plain list'),
                        css:{'margin-right':'0.5em','display':'inline-block'}, id:'btnViewMode_List',
                        click: function() { that._onActionListener(null, 'viewmode-list'); }},
                    /*{showText:false, icons:{primary:'ui-icon-structure'}, text:window.hWin.HR('Show as tree'),
                    css:{'margin-right':'0.5em','display':'inline-block'}, id:'btnViewMode_Tree',
                    click: function() { that._onActionListener(null, 'viewmode-tree'); }},*/
                    {showText:false, icons:{primary:'ui-icon-view-icons'}, text:window.hWin.HR('Show as images'),
                        css:{'margin-right':'0.5em','display':'inline-block'}, id:'btnViewMode_List',
                        click: function() { that._onActionListener(null, 'viewmode-thumbs'); }}
                ];

                this._toolbar = this.searchForm;

                //padding:'6px'
                this.searchForm.css({'min-width': '200px', 'padding-top':this.options.isFrontUI?'6px':'4px', height:80})
                .empty();                                     
                this.recordList.css({'min-width': '200px', top:80});
                this.searchForm.parent().css({'overflow-x':'auto'});

                $('<div style="vertical-align: middle;width: 100%;min-height: 32px; border-bottom: 1px solid gray; clear: both;">'
                    +'<div id="div_group_information" style="margin-right:150px;">A</div>'
                    +'<div style="position:absolute;right:10px;top:8px;"><label>Find: </label>'
                    +'<input type="text" style="width:6em" class="find-term text ui-widget-content ui-corner-all"/></div>'
                    +'</div>'
                    +'<div class="action-buttons" style="height:40px;background:white;padding:10px 8px;">'
                    +'<h4 style="display:inline-block;margin: 0 10px 0 0; vertical-align: middle;">Terms</h4>'
                    +'<div style="min-width:70px;text-align:right;float:right" id="btn_container"></div></div>')
                .appendTo(this.searchForm);


                /*$('<div>'
                +'<h3 style="display:inline-block;margin: 0 10px 0 0; vertical-align: middle;">Terms</h3>'
                +'</div>'
                +'<div style="display:table;width:100%;">'
                +'<div id="div_group_information" style="padding-top:13px;min-height:3em;max-width:350px;display:table-cell;"></div>'
                +'<div style="min-width:70px;text-align:right;display:table-cell;" id="btn_container"></div>'
                +'</div>')
                .appendTo(this.searchForm);*/

                //add, import buttons
                var c1 = this.searchForm.find('div.action-buttons');
                for(var idx in btn_array){
                    this._defineActionButton2(btn_array[idx], c1);
                }

                $('<span style="font-size:10px">drag to <label><input type="radio" name="rbDnD" id="rbDnD_move" checked/>move<label> '
                    +'<label><input type="radio" name="rbDnD" id="rbDnD_merge"/>merge<label></span>')
                .appendTo(c1);

                //add input search
                this._on(this.searchForm.find('.find-term'), {
                    //keypress: window.hWin.HEURIST4.ui.preventChars,
                    keyup: this._onFindTerms }); //keyup

                //view mode
                c1 = this.searchForm.find('#btn_container');
                for(var idx in btn_array2){
                    this._defineActionButton2(btn_array2[idx], c1);
                }

                this.rbMergeOnDnD = this.searchForm.find('#rbDnD_merge');

                this._dropped = false;

                this.options.recordList = {
                    empty_remark: 'No terms in selected vocabulary. Add or import new ones',
                    show_toolbar: false,
                    view_mode: 'list',
                    pagesize: 999999,
                    recordDivEvenClass:'', //suppress highlight for even lines
                    /*  INLINE EDITOR disabled 2020-12-01            
                    recordview_onselect:'inline',
                    expandDetailsOnClick: false,
                    rendererExpandDetails: function(recordset, trm_id){
                    if(true || this.options.view_mode=='list'){
                    var $rdiv = $(this.element).find('div[recid='+trm_id+']');
                    var ele = $('<div>')
                    .attr('data-recid', $rdiv.attr('recid'))
                    .css({'max-height':'255px','overflow':'hidden','height':'255px', })
                    .addClass('record-expand-info');
                    ele.appendTo($rdiv);

                    $rdiv.addClass('selected expanded');

                    if(this.options.view_mode!='list'){
                    $rdiv.css({width:'97%', height:'266px', outline:'red'});
                    ele.css('position','initial');
                    }

                    that._showEditorInline(ele, trm_id);

                    if(this.options.view_mode=='list'){
                    ele.css('width','100%');
                    ele.find('.ent_wrapper:first').css({'top':25,margin:'4px 5%',border:'1px gray solid'});
                    }else{
                    ele.find('.ent_wrapper:first').css({'top':25});
                    }

                    }else{
                    that.addEditRecord(trm_id);
                    }

                    },*/
                    draggable: function(){

                        that.recordList.find('.rt_draggable > .item').draggable({ //    
                            revert: 'invalid',
                            helper: function(){ 
                                that._dropped = false;
                                return $('<div class="rt_draggable ui-drag-drop" recid="'+
                                    $(this).parent().attr('recid')
                                    +'" style="width:300;padding:4px;text-align:center;font-size:0.8em;background:#EDF5FF"'
                                    +'>Drag and drop to vocabulary item to change it</div>'); 
                            },
                            zIndex:99999,
                            appendTo:'body',
                            containment: 'window',
                            scope: 'vocab_change',

                            drag: function(event,ui){
                                //var trg = $(event.target);trg.hasClass('ui-droppable')
                                if($('.ui-droppable.ui-drag-drop').is(':visible')){
                                    $(ui.helper).css("cursor", 'grab');
                                }else{
                                    $(ui.helper).css("cursor", 'not-allowed');
                                }

                                var ele = that.recordList.find('.div-result-list-content');
                                var bot = ele.offset().top+ele.height();
                                if(that.scrollInterval>0) clearInterval(that.scrollInterval);
                                that.scrollInterval = 0;

                                if(ui.offset.top>bot-20){
                                    //    console.log(bot);
                                    //    console.log(ui.position.top+'  '+ui.position.left);
                                    that.scrollInterval = setInterval(function(){ ele[0].scrollTop += 20}, 50); 
                                }else if(ui.offset.top<ele.offset().top+20){
                                    that.scrollInterval = setInterval(function(){ ele[0].scrollTop -= 20}, 50); 
                                }

                            }
                        });   
                    },
                    droppable: function(){

                        that.recordList.find('.recordDiv')  //change parent for term (within the same vocab tree) OR merge
                        .droppable({
                            scope: 'vocab_change',
                            hoverClass: 'ui-drag-drop', //highlight
                            drop: function( event, ui ){

                                var trg = $(event.target).hasClass('recordDiv')
                                ?$(event.target)
                                :$(event.target).parents('.recordDiv');

                                that._dropped = true;
                                window.hWin.HEURIST4.util.stopEvent(event);

                                var trm_ID = $(ui.draggable).parent().attr('recid');
                                var trm_ParentTermID = trg.attr('recid');
                                if(trm_ID!=trm_ParentTermID && trm_ID>0 && trm_ParentTermID>0){

                                    if(that.rbMergeOnDnD.is(':checked')){
                                        that.mergeTerms({trm_ID:trm_ID, trm_ParentTermID:trm_ParentTermID });    
                                    }else{
                                        that.changeVocabularyGroup({trm_ID:trm_ID, trm_ParentTermID:trm_ParentTermID });        
                                    }
                                }
                            }
                        });

                        // move to root of vocabulary                                
                        that.recordList.find('.div-result-list-content')
                        .droppable({
                            scope: 'vocab_change',
                            hoverClass: 'ui-drag-drop',
                            drop: function( event, ui ){

                                if (that._dropped) return;

                                var trm_ID = $(ui.draggable).parent().attr('recid');
                                var trm_ParentTermID = that.options.trm_VocabularyID;
                                if(trm_ID>0){
                                    if(!that.rbMergeOnDnD.is(':checked')){
                                        that.changeVocabularyGroup({ trm_ID:trm_ID, trm_ParentTermID:trm_ParentTermID }, true);
                                    }
                                }
                        }});


                        //collapses 
                        if(window.hWin.HEURIST4.ui.collapsed_terms.length>0){
                            $.each(that.recordList.find('.recordDiv'),
                                function(i,pitem){
                                    pitem = $(pitem);
                                    var recID =pitem.attr('recID');
                                    if(window.hWin.HEURIST4.ui.collapsed_terms.indexOf(recID)>=0){

                                        pitem.find('span.ui-icon-triangle-1-s') 
                                        .removeClass('ui-icon-triangle-1-s')
                                        .addClass('ui-icon-triangle-1-e');

                                        $.each(that.recordList.find('div.recordDiv[data-parent]'),
                                            function(i,item){
                                                item = $(item);
                                                if(item.attr('data-parent').indexOf(';'+recID+';')>=0){
                                                    item.hide();
                                                }
                                        });
                                    }
                                }
                            );    

                        }

                    }

                };

                //vocabularies options
                var rg_options = {
                    isdialog: false, 
                    isFrontUI: this.options.isFrontUI,
                    container: that.vocabularies_div,
                    title: 'Vocabularies',
                    select_mode: 'manager',
                    reference_trm_manger: that.element,
                    auxilary: 'vocabulary',
                    onSelect:function(res){

                        /*
                        if(that.options.selection_on_init>0){

                        that.options.trm_VocabularyID = that.options.selection_on_init;
                        that.options.selection_on_init = null;

                        }else */
                        if(window.hWin.HEURIST4.util.isRecordSet(res)){
                            res = res.getIds();                     
                            if(res && res.length>0){
                                //filter by vocabulary
                                that.options.trm_VocabularyID = res[0];
                            }
                        }else if(res>0){
                            that.options.trm_VocabularyID = res;
                        }else{
                            that.options.trm_VocabularyID = -1;
                        }

                        if(that.getRecordSet()!==null){
                            that._filterByVocabulary();
                        }
                    }
                };

                if(this.options.selection_on_init=='add_new' || this.options.selection_on_init>0){
                    //initially selected vocabulary
                    rg_options['selection_on_init'] = this.options.selection_on_init;
                    this.options.selection_on_init = null;
                }

                this.recordList.uniqueId();

                window.hWin.HEURIST4.ui.showEntityDialog('defTerms', rg_options);

                this._on( this.recordList, {        
                    "resultlistondblclick": function(event, selected_recs){
                        this.selectedRecords(selected_recs); //assign

                        if(window.hWin.HEURIST4.util.isRecordSet(selected_recs)){
                            var recs = selected_recs.getOrder();
                            if(recs && recs.length>0){
                                var recID = recs[recs.length-1];
                                this._onActionListener(event, {action:'edit',recID:recID}); 
                                //edit-inline disabled 2020-12-01            
                            }
                        }
                    }
                });
                
            }//aux=term


        }
        else { //if(this.options.select_mode=='select_single' || this.options.select_mode=='select_multi'){
           //SELECT MODE

            var c1 = this.searchForm;//.find('div:first');

            if(this.options.select_mode_target){

                if(this.options.select_mode_reference=='inverse'){
                    $('<h3 style="margin:10px 0">Select inverse term for: <i>'
                        +this.options.select_mode_target+'</i></h3>').appendTo(c1);
                }else{
                    $('<h3 style="margin:10px 0">Adding to vocabulary: <i>'+this.options.select_mode_target+'</i></h3>').appendTo(c1);
                }

            }

            //add vocabulary
            var sel = $('<div style="float:left"><label>From vocabulary: </label>'
                +'<select type="text" style="min-width:15em;" class="text ui-widget-content ui-corner-all"></select></div>')
            .appendTo(c1);

            //add input search
            /*
            c1 = $('<div style="float:left"><label>Find: </label>'
            +'<input type="text" style="width:10em" class="text ui-widget-content ui-corner-all"/></div>')
            .appendTo(c1);

            this._on(c1.find('input'), {
            //keypress: window.hWin.HEURIST4.ui.preventChars,
            keyup: this._onFindTerms }); //keyup
            */    

            if(!this.options.title){
                this.setTitle('Select '+(this.options.filter_groups=='enum'?'term':'relation'));    
            }
            //this.setTitle(this.options.title);

            sel = sel.find('select');
            this.vocabularies_sel = 
            window.hWin.HEURIST4.ui.createVocabularySelect(sel[0], {useGroups:true, 
                domain: this.options.filter_groups,
                defaultTermID: this.options.initial_filter });
            this._on(this.vocabularies_sel, {
                change:function(event){
                    this.options.trm_VocabularyID = $(event.target).val();
                    this._filterByVocabulary();
            } }); 

            this.options.trm_VocabularyID = this.options.initial_filter;

            this.options.recordList = {
                empty_remark: 'Select vocabulary',
                show_toolbar: false,
                view_mode: 'list',
                pagesize: 999999};

            this.options.trm_VocabularyID = this.options.initial_filter;
            //this._onActionListener(null, 'viewmode-tree');

            if( !window.hWin.HEURIST4.util.isempty(this.options.select_mode_reference) ){
                this.searchForm.css('height','5.5em');
                this.recordList.css('top','5.5em');
                this.options.recordList.transparent_background = true;
                this.options.recordList.recordDivEvenClass = null;
                //this.recordList.removeClass('ui-heurist-bg-light').css({'background':'none','border':'none'});
            }

        }

        this.recordList.resultList( this.options.recordList );

        that._loadData(true);

        return true;
    },//_initControls   


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
    // DISABLE 2020-12-01
    //
    _showEditorInline: function(container, recID){


        if(container.manageDefTerms('instance') 
            && container.manageDefTerms('option','auxilary')==this.options.auxilary)
        {

            container.manageDefTerms('option','edit_recordset', this.recordList.resultList('getRecordSet'));
            container.manageDefTerms('option','trm_ParentTermID', this.options.trm_VocabularyID);
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
                reference_trm_manger: this.element,
                rec_ID: recID,
                trm_VocabularyID: this.options.trm_VocabularyID,
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
            var recset = $Db.trm()
                    .getSubSetByRequest({'trm_ParentTermID':'=0', 'sort:trm_Label':1},
                                            this.options.entity.fields);
            this.updateRecordList(null, {recordset:recset});

        }else{
            //AAA this.updateRecordList(null, {recordset:$Db.trm()});
            this._cachedRecordset = $Db.trm(); //updateRecordList is not used to avoid delay    
        }
        this._filterByVocabulary();
        
    },

    //
    //
    //
    _filterByVocabulary: function(){

        if(!this.getRecordSet()) return;

        var sGroupTitle = '<h3 style="margin:0;padding:0 8px" class="truncate">';
        if(this.options.auxilary=='vocabulary'){
            //filter by group

            if(this.options.trm_VocabularyGroupID>0){

                var vcg_id = this.options.trm_VocabularyGroupID;
                this.filterRecordList(null, {'trm_VocabularyGroupID':vcg_id, 'sort:trm_Label':1});

                sGroupTitle += (window.hWin.HEURIST4.util.htmlEscape($Db.vcg(vcg_id,'vcg_Name'))
                    +'</h3><div class="heurist-helper3 truncate" style="font-size:0.7em;padding:0 8px;">'
                    + window.hWin.HEURIST4.util.htmlEscape($Db.vcg(vcg_id,'vcg_Description'))
                    +'</div>');

                    
                //initial selection
                if(this.options.selection_on_init>0){
                    this.selectRecordInRecordset( this.options.selection_on_init );    
                    this.options.selection_on_init = null;
                }else{
                    var rdiv = this.recordList.find('.recordDiv:first');
                    if(rdiv.length){
                        var rec_ID = rdiv.attr('recid');
                        rdiv.click();
                    }else if($.isFunction(this.options.onSelect)){
                        this.options.onSelect.call( this, null );
                    }
                }                    

            }else{
                sGroupTitle += '</h3>';
            }


        }else{

            if(this.options.trm_VocabularyID>0){ //filter by vocabulary

                var vocab_id = this.options.trm_VocabularyID;

                //this.setTitle('Manage Terms: '+$Db.trm(vocab_id,'trm_Label'));
                //this.filterRecordList(null, {'trm_ParentTermID':ids, 'sort:trm_Label':1});

                var subset = $Db.trm_TreeData(vocab_id, 'flat'); //returns recordset

                if(!this.recordList.resultList('instance')) return;
                
                this.recordList.resultList('updateResultSet', subset, null);

                if(this.recordTree && this.recordTree.fancytree('instance')){

                    //filtered
                    var treedata = $Db.trm_TreeData(vocab_id, 'tree'); //tree data
                    // subset.getTreeViewData('trm_Label', 'trm_ParentTermID',vocab_id);
                    var tree = this.recordTree.fancytree('getTree');
                    tree.reload(treedata);
                }

                sGroupTitle += ($Db.trm(vocab_id,'trm_Label')
                    +'</h3><div class="heurist-helper3 truncate" style="font-size:0.7em;padding:0 8px">'
                    +$Db.trm(vocab_id,'trm_Description')+'&nbsp;</div>');

            }else{
                this.recordList.resultList('clearAllRecordDivs',null,
                    '<div style="padding: 8px;">Select vocabulary'+
                    ((this.options.select_mode!='manager')?'':' on the left')+'</div>');

                sGroupTitle += '</h4>';
            }

            if(this.options.isFrontUI){
                var ele = this.element.find('.coverall-div');
                if(ele.length>0) ele.remove();
                //var that = this;
                //setTimeout(function(){that.element.find('.coverall-div').remove();},1000);
            }
        }
        this.searchForm.find('#div_group_information').html(sGroupTitle);

    },

    //
    //
    //
    _recordListItemRenderer:function(recordset, record){

        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        var recID = recordset.fld(record, 'trm_ID');

        var sBold = '';
        var sWidth = '212';
        var sPad = '';
        var sRef = '';
        var sHint = '';
        var sLabel = window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, 'trm_Label'));
        var sDesc = window.hWin.HEURIST4.util.htmlEscape($Db.trm(recID, 'trm_Description'));
        var recTitle = '', sFontSize='';
        var html;


        if(this.options.auxilary=='vocabulary'){
            sBold = 'font-weight:bold;';
            if(recordset.fld(record, 'trm_Domain')=='relation'){
                sWidth = '167';
            }
            sWidth = 'max-width:'+sWidth+'px;min-width:'+sWidth+'px;';

            recTitle = '<div class="item truncate" style="'+sWidth+sBold+'">'
            + sLabel+'</div>';

            html = '<div class="recordDiv rt_draggable white-borderless" style="padding-right:0" recid="'+recID+'">'
            + '<div class="recordSelector item"><input type="checkbox" /></div>'
            + recTitle 

            +((recordset.fld(record, 'trm_Domain')=='enum')
                ?'':'<div style="display: table-cell;font-size:smaller" class="item">(relation)</div>')

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
            var parents = recordset.fld(record, 'trm_Parents').split(',');
            var lvl = (parents.length);

            if(lvl>0){
                parents = ' data-parent=";'+parents.join(';')+';"';
            }else{
                parents = '';
            }

            var ref_lvl = $Db.isTermByReference(this.options.trm_VocabularyID, recID);
            if(ref_lvl!==false){
                sRef = '<span style="color:blue;font-size:smaller;vertical-align:text-top;">&nbsp;(ref)</span>';
            }

            var sLabelHint = sLabel;
            sLabel = sLabel + sRef;

            var inv_id = $Db.trm(recID, 'trm_InverseTermId');
            if ( inv_id>0 ){
                var sInvLabel = window.hWin.HEURIST4.util.htmlEscape($Db.trm(inv_id, 'trm_Label'));
                if(sInvLabel) {
                    sLabel = sLabel + 
                    '&nbsp;&nbsp;&nbsp;<span style="font-size:smaller;font-style:italic;">inv: '+sInvLabel+'</span>';    
                    sLabelHint = sLabelHint + '    [inv: '+sInvLabel+' ]';
                }
            }

            sWidth = 'display:inline-block;padding-top:4px;max-width:320px;'; //+((lvl>2)?'25':'30')+'%;';
            //sPad = 'padding-left:'+(lvl*20);

            sPad = '<span style="font-size:'+(1+lvl * 0.1)+'em">'+('&nbsp;'.repeat(lvl*4))+'</span>';
            sFontSize = 'font-size:'+(1.1 - lvl * 0.1)+'em;';

            var sCode = $Db.trm(recID, 'trm_Code');
            var sURI = $Db.trm(recID, 'trm_SemanticReferenceURL');

            sHint = 'title="'+sLabelHint+'<br>'
            + (sDesc?('<p><i>'+sDesc+'</i></p>'):'')
            + (sCode?('<p>Code: '+sCode+'</p>'):'')
            + (sURI?('<p>URI: '+sURI+'</p>'):'')
            + '<p>ID: '+recID+' ('+$Db.getConceptID('trm',recID)+')</p>';

            if(ref_lvl!==false)
            {
                var vocab_id = $Db.getTermVocab(recID); //real vocab

                sHint = sHint +     
                '<p>Reference to: <i>'
                +window.hWin.HEURIST4.util.htmlEscape($Db.vcg($Db.trm(vocab_id,'trm_VocabularyGroupID'), 'vcg_Name'))+'.'
                +window.hWin.HEURIST4.util.htmlEscape($Db.trm(vocab_id, 'trm_Label'))+'</i> vocabulary.</p>'
                +'<p style=&quot;color:orange&quot;>The term can only be edited in that vocabulary.</p>';

                //sWidth = sWidth + '45%;';
            }else{
                //sWidth = sWidth + '49%;';
            }
            sHint = sHint + '"';

            recTitle = '<div class="item truncate label_term rolloverTooltip"'
            +' style="'+sFontSize+sWidth+sBold+'" '+sHint+'>'
            +sLabel+'</div>'; // $Db.trm(recID, 'trm_Parents')+'  '+recID+' '+
            //+sPad            

            var recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb');

            var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'
            +recThumb+'&quot;);opacity:1"></div>';

            sPad = lvl==1?0:(lvl==2?(lvl-0.5):lvl);                                                                         
            var exp_btn_style = 'width:20px;display:inline-block;vertical-align:bottom;margin-left:'+sPad+'em;';

            var sclass = 'white-borderless', sstyle = '';
            if( !window.hWin.HEURIST4.util.isempty(this.options.select_mode_reference) ){
                sclass = '';
                sstyle = 'style="background:transparent;border:none;"';
            }

            html = '<div class="recordDiv densed '+sclass+(!(ref_lvl>0)?' rt_draggable':'')
            +'" '+sstyle+' recid="'+recID+'"'+parents+'>'
            + ($Db.trm_HasChildren(recID)
                ?this._defineActionButton(
                    {key:'expand',label:'Show/hide children',            
                        title:'', icon:'ui-icon-triangle-1-s',class:null},
                    null,'icon_text',exp_btn_style)
                :'<div style="'+exp_btn_style+'">&nbsp;</div>') 
            + '<div class="recordSelector" style="display:inline-block;"><input type="checkbox" /></div>'
            + html_thumb + recTitle;

            /* 2020-12-10                      
            html = html + '<div class="truncate description_term">'  //item truncate 
            + sDesc
            + '</div>';
            */
            if(this.options.select_mode=='manager' && !(ref_lvl>0)){ //

                html = html + '<div class="rec_action_link2" style="margin-left:1em;'
                +'width:'+(sRef?20:62)+'px;height:20px;background:lightgray;display:inline-block;vertical-align:middle;">';

                if(ref_lvl===0){
                    html = html 
                    + this._defineActionButton({key:'delete',label:'Remove Reference', 
                        title:'', icon:'ui-icon-delete',class:'rec_actions_button'},
                        null,'icon_text')

                }else if(ref_lvl===false){
                    html = html 
                    + this._defineActionButton({key:'edit',label:'Edit Term',  //was  edit-inline disabled 2020-12-01            
                        title:'', icon:'ui-icon-pencil',class:'rec_actions_button'},
                        null,'icon_text') 
                    + this._defineActionButton({key:'delete',label:'Remove Term', 
                        title:'', icon:'ui-icon-delete',class:'rec_actions_button'},
                        null,'icon_text')
                    + this._defineActionButton({key:'add-child',label:'Add child', 
                        title:'', icon:'ui-icon-plus',class:'rec_actions_button'},
                        null,'icon_text');
                }else{
                    html = html + ref_lvl;
                }
                html = html + '</div>';
            }
            html = html 
            + '</div>';
        }

        return html;

    },

    _deleteAndClose: function(unconditionally){

        if(unconditionally===true){

            //this._super(); 

            if(this._currentEditID==null || this._currentEditID<1) return;

            var request = {
                'a'          : 'delete',
                'entity'     : this.options.entity.entityName,
                'request_id' : window.hWin.HEURIST4.util.random(),
                'recID'      : this._currentEditID                     
            };

            var that = this;                        

            var it_was_vocab = !($Db.trm(this._currentEditID, 'trm_ParentTermID')>0);

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    var recID = that._currentEditID;
                    if(response.status == window.hWin.ResponseStatus.OK){

                        if(that.options.use_cache){
                            that._cachedRecordset.removeRecord( recID );
                        }
                        that._afterDeleteEvenHandler( recID, it_was_vocab );

                    }else{
                        if(response.sysmsg && response.sysmsg.children){

                            //children detailtypes reccount records
                            var res = response.sysmsg;    

                            if(window.hWin.HEURIST4.util.isArrayNotEmpty(res.detailtypes)){
                                this._showWarningAboutUsage( recID, res.detailtypes );                                  
                                return;
                            }

                            var is_vocab = !($Db.trm(recID, 'trm_ParentTermID')>0);


                            var sMsg = '<p>'+(res.children==0?'Term':('Terms in '+is_vocab?'Vocabulary':'Branch')) 
                            + ' <b>'+$Db.trm(recID, 'trm_Label') + '</b> ' 
                            + (res.children==0?'is':'are') +  ' in use by '+res.reccount+' record'+(res.reccount>1?'s':'')+' in the database.</p>'

                            +'<p>Before you can delete the '
                            +(res.children==0?'term':(is_vocab?'vocabulary':'branch')+' and its child terms')
                            +', you will need to delete the records which use '+(res.children==0?'this term':'these terms')
                            +', or delete the values from the records.</p>';

                            if(window.hWin.HEURIST4.util.isArrayNotEmpty(res.records)){
                                sMsg += '<p><a href="#" class="records-list"'
                                +'>List of records which '+(res.children==0?'this term':'these terms')+'</a></p>';
                            }
                            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg, null, {title:'Terms in use'},
                                {default_palette_class:that.options.default_palette_class});        

                            that._on($dlg.find('a.records-list'),{click:function(e){
                                var request = {q:'ids:'+res.records.join(',')};
                                window.hWin.HAPI4.SearchMgr.doSearch(window.hWin.document, request);
                                $dlg.dialog('close');
                            }});

                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);    
                        }

                    }
            });            

        }else{
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete '
                +this.options.auxilary+' <b>'+$Db.trm(this._currentEditID, 'trm_Label')
                +'</b>?', 
                function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'},
                {default_palette_class:this.options.default_palette_class});        
        }
    },

    //
    //
    //
    _afterDeleteEvenHandler: function(recID, it_was_vocab){

        this._super(recID); 

        //get all children
        var all_ids = $Db.trm_TreeData(recID, 'set', true);

        //remove defs on client side
        $Db.trm_RemoveLinks(recID);
        $Db.trm().removeRecord(recID);  //from record set

        $.each(all_ids, function(i,id){
            $Db.trm_RemoveLinks(id);
            $Db.trm().removeRecord(id);  //from record set
        });

        if(it_was_vocab){ 
            this.options.trm_VocabularyID = -1;
            this._filterByVocabulary();
        }
    },


    //-----
    //
    // show hide some elements on edit form according to type: 
    //    vocab/term and enum/relation
    //
    _afterInitEditForm: function(){
        this._super();

        this.inverse_termid_old = 0;

        var ele, currentDomain = null;
        var isVocab = !(this.options.trm_VocabularyID>0);
        if(isVocab){

            if(this._edit_dialog && this._edit_dialog.dialog('instance')){
                this._edit_dialog.dialog('option', 'title', (this._currentEditID>0?'Edit':'Add')+' Vocabulary');
            }

            //hide fields for vocab    
            this._editing.getFieldByName('trm_InverseTermId').hide();
            this._editing.getFieldByName('trm_InverseSymmetrical').hide();
            this._editing.getFieldByName('trm_Code').hide();
            this._editing.getFieldByName('trm_Thumb').hide();

            //change label            
            this._editing.getFieldByName('trm_Label').find('.header').text('Vocabulary Name');

            //assign devault values
            ele = this._editing.getFieldByName('trm_VocabularyGroupID');
            if(this._currentEditID<0 && this.options.trm_VocabularyGroupID>0){
                ele.editing_input('setValue', this.options.trm_VocabularyGroupID, true);
                currentDomain = $Db.vcg( this.options.trm_VocabularyGroupID, 'vcg_Domain' );
            }

            ele.show();
            ele.editing_input('fset', 'rst_RequirementType', 'required');
            ele.find('.header').removeClass('recommended').addClass('required');


            if(this.options.suggested_name){
                this._editing.setFieldValueByName('trm_Label', this.options.suggested_name, false);
                this.options.suggested_name = null;
            }

        }else
        {

            var dlg = this._getEditDialog(true);
            if(dlg && dlg.dialog('instance')){
                var s = (this._currentEditID>0?'Edit':'Add')+' Term';
                if(this.options.trm_VocabularyID>0){
                    s = s + ' to vocabulary "'
                    +window.hWin.HEURIST4.util.htmlEscape($Db.trm(this.options.trm_VocabularyID,'trm_Label'))+'"';
                    
                    if(!(this._currentEditID>0)){
                    $('<div class="heurist-helper1" style="padding:0 0 10px 20px;max-width:410px">'
+'If the terms you need already exist in other vocabularies, '
+'for example a subset of countries or languages, close this popup,'
+'click on the gearwheel icon or the vocabularies editor link, '
+'then use the Ref(erence) button to add references in the vocabulary'
+'which point to existing terms</div>').insertBefore($(this.editForm.find('fieldset')[0]));
                    }
                    
                }
                dlg.dialog('option', 'title', s);
            }

            ele = this._editing.getFieldByName('trm_ParentTermID');
            ele.editing_input('setValue', this.options.trm_ParentTermID>0
                ?this.options.trm_ParentTermID
                :this.options.trm_VocabularyID, true);
            this.options.trm_ParentTermID = -1;

            this._editing.getFieldByName('trm_VocabularyGroupID').hide();


            var vocab_ID = $Db.getTermVocab(this.options.trm_VocabularyID);
            currentDomain = $Db.trm(vocab_ID, 'trm_Domain');

            if( currentDomain=='enum' ){
                this._editing.getFieldByName('trm_InverseTermId').hide();
                this._editing.getFieldByName('trm_InverseSymmetrical').hide();
            }else{
                ele = this._editing.getFieldByName('trm_InverseTermId');
                ele.show();
                var cfg = ele.editing_input('getConfigMode');
                cfg.initial_filter = vocab_ID;
                cfg.popup_options = {};
                cfg.popup_options.width = 400;
                cfg.popup_options.select_mode_reference = 'inverse';
                cfg.popup_options.select_mode_target = this._currentEditID>0?$Db.trm(this._currentEditID,'trm_Label'):'new term';
                ele.editing_input('setConfigMode', cfg);

                ele = this._editing.getFieldByName('trm_InverseSymmetrical');
                ele.show();

                if(this._currentEditID>0){
                    //detect: is it symmetrical?
                    var val = '1';
                    var trm_InverseTermId = $Db.trm(this._currentEditID, 'trm_InverseTermId');
                    if(trm_InverseTermId>0){
                        this.inverse_termid_old = $Db.trm(trm_InverseTermId, 'trm_InverseTermId');
                        val = (this._currentEditID == this.inverse_termid_old)?'1':'0';
                    }
                    ele.editing_input('setValue', val, true);
                }
                window.hWin.HEURIST4.util.setDisabled(ele, (this._currentEditID>0));

            }

        }

        ele = this._editing.getFieldByName('trm_Domain')
        if(currentDomain!==null) ele.editing_input('setValue', currentDomain, true);
        if(isVocab) ele.show();

        ele = this._editing.getFieldByName('trm_ID');
        if(this._currentEditID>0){
            ele.find('div.input-div').html(this._currentEditID+'&nbsp;&nbsp;( '
                +$Db.getConceptID('trm',this._currentEditID)+' )');
            //$('<span>&nbsp;&nbsp;('+this._getField('trm_OriginatingDBID')+'-'+this._getField('trm_IDInOriginatingDB')+')</span>') 
        }else{
            ele.hide();   
        }

        //on ENTER save
        this._on( this.editForm.find('input.text,textarea.text'), { keypress: function(e){        
            var code = (e.keyCode ? e.keyCode : e.which);
            if (code == 13) {
                window.hWin.HEURIST4.util.stopEvent(e);
                e.preventDefault();
                this._saveEditAndClose();
            }
        }});                        

        //btnRecSave
        //defaultBeforeClose


        var ishelp_on = (this.usrPreferences['help_on']==true || this.usrPreferences['help_on']=='true');
        ele = $('<div style="position:absolute;right:6px;top:4px;"><label><input type="checkbox" '
            +(ishelp_on?'checked':'')+'/>explanations</label></div>').prependTo(this.editForm);
        this._on( ele.find('input'), {change: function( event){
            var ishelp_on = $(event.target).is(':checked');
            this.usrPreferences['help_on'] = ishelp_on;
            window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper1');
        }});

        if(!isVocab){
            this.editForm.find('.header').css({'min-width':80,width:80});
            this.editForm.css({'overflow-x': 'hidden'});

            this._toolbar.addClass('ui-heurist-bg-light');
        }

        //this.editForm.find('.heurist-helper1').removeClass('heurist-helper1').addClass('heurist-helper3');
        window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper1');

        this._editing.getFieldByName('trm_Label').find('.input-cell').css({'padding-bottom': '20px'});

        this._adjustEditDialogHeight();

    },   
    
    //
    //
    //
    getLastAddedVocabulary: function(){
        return this.last_added_vocabulary;    
    },
    
    //
    //
    //
    contextOnClose: function(){
        
        var last_vocab_id = this.vocabularies_div.manageDefTerms('getLastAddedVocabulary');
        
        return last_vocab_id>0?last_vocab_id:this.options.trm_VocabularyID;
    },


    //
    //
    //
    _getEditDialogButtons: function(){
        var btns = this._super();

        if(this.options.edit_mode=='editonly'){ //for popup

            var that = this;

            if(this.options.container){
                //inlin/inside resultList - edit terms

                btns[0].text = 'Close';
                btns[0].css['visibility'] = 'visible';
                btns[0].class = 'alwaysvisible';

                btns[0].click = function(){
                    if(that.defaultBeforeClose()){
                        if($.isFunction(that.options.onClose)){
                            that.options.onClose.call(that, that.contextOnClose() );
                        } 
                    }
                };

                btns.push(
                    {text:window.hWin.HR('Add Child'),
                        icon:'ui-icon-plus',
                        css:{'float':'left',margin:'.5em .4em .5em 0'},  
                        click: function() { 
                            if(that.options.reference_trm_manger && that.options.reference_trm_manger.manageDefTerms('instance')){
                                that.options.reference_trm_manger.manageDefTerms('option','trm_ParentTermID',that._currentEditID);
                                that.options.reference_trm_manger.manageDefTerms('addEditRecord',-1);
                            }
                }});
                btns.push(    
                    {text:window.hWin.HR('Import terms'),
                        icon:'ui-icon-download',
                        showLabel:false,
                        css:{'float':'left',margin:'.5em .4em .5em 0'},  
                        click: function() { 
                            if(that.options.reference_trm_manger && that.options.reference_trm_manger.manageDefTerms('instance')){
                                that.options.reference_trm_manger.manageDefTerms('importTerms',that._currentEditID,false);
                            }
                    }}
                );



            }else{

                btns[0].text = 'Close';
                btns[0].css['visibility'] = 'visible';
                btns[0].class = 'alwaysvisible';

                btns[0].click = function(){
                    if(that.defaultBeforeClose()){
                        /*
                        if($.isFunction(that.options.onClose)){
                            //currently selected vocavulary
                            that.options.onClose.call( this, that.options.trm_VocabularyID );
                        }
                        */ 
                        that._currentEditID = null; 
                        that.closeDialog(true);
                        that.options.onClose = null;
                    }
                };

                if( window.hWin.HAPI4.is_admin() && false ){ //2020-11-28 hide this button at all
                    /*                
                    btns.push(
                    {text:window.hWin.HR('Edit All'),
                    id:'btnEditAll',
                    css:{'float':'left',margin:'.5em .4em .5em 0'},  
                    click: function() { 
                    var rg_options = {
                    height:800, width:1300,
                    onInitFinished: function(){
                    var that2 = this;
                    setTimeout(function(){
                    that2.vocabularies_div.manageDefTerms('selectVocabulary', that.options.trm_VocabularyID);
                    },500);
                    },
                    onClose: that.options.onClose
                    };
                    that.options.onClose = null;
                    that._currentEditID=null; 
                    that.closeDialog(true);
                    // $dlg.dialog('close');
                    //that._saveEditAndClose(); 
                    window.hWin.HEURIST4.ui.showEntityDialog('defTerms', rg_options);
                    }}                
                    );
                    */                    
                }
            }

        }
        return btns;  
    },

    onEditFormChange: function(changed_element){

        this._super(changed_element);

        if(this._toolbar && this.options.edit_mode=='editonly'){
            var isChanged = this._editing.isModified();
            this._toolbar.find('#btnEditAll').css('visibility', isChanged?'hidden':'visible');
        }

    },

    //
    // update list after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){

        if(this.options.edit_mode=='editonly'){
            if(!this.options.container){ //for popup
            
                var keepParent = $Db.trm(recID,'trm_ParentTermID');
                var isVocab = !(keepParent>0); //new term is added
                var sName = (isVocab)?'Vocabulary':'Term';
                if(isVocab){
                    this.options.trm_VocabularyGroupID = $Db.trm(recID,'trm_VocabularyGroupID');
                    this.options.trm_VocabularyID = recID;    
                }else{
                    this.options.trm_ParentTermID = keepParent; //it was reset to -1 in afterInitEditForm
                }

                window.hWin.HEURIST4.msg.showMsgFlash(sName+' '+window.hWin.HR('has been saved'),300);
                this._currentEditID = -1;
                this._initEditForm_step3(this._currentEditID); //reload edit form 

                var that = this;
                setTimeout(function(){that._editing.setFocus();},1000);
            }
            return;
        }else if(this.it_was_insert && this.options.auxilary=='vocabulary' && this.options.edit_mode=='popup'){
            

            
        }else if(this.it_was_insert && this.options.auxilary=='term' && this.options.edit_mode=='popup'){

            this.options.trm_ParentTermID = $Db.trm(recID,'trm_ParentTermID'); //it was reset to -1 in afterInitEditForm

            //reload edit page
            window.hWin.HEURIST4.msg.showMsgFlash('Added',300);
            //this.options.trm_ParentTermID = recID;
            this._currentEditID = -1;
            this._initEditForm_step3(this._currentEditID); //reload 
            var that = this;
            setTimeout(function(){that._editing.setFocus();},1000);
            this.refreshRecordList();
            return;
        }

        // close on addition of new record in select_single mode    
        //this._currentEditID<0 && 
        if(this.options.select_mode=='select_single'){

            this._selection = new hRecordSet();
            //{fields:{}, order:[recID], records:[fieldvalues]});
            this._selection.addRecord(recID, fieldvalues);
            this._selectAndClose();

            return;    
        }else if(this.it_was_insert && this.options.auxilary=='vocabulary'){
            
            this.last_added_vocabulary = recID;
            this.selectVocabulary(recID, true);
            
            //this.selectRecordInRecordset([recID]);
        }

        this._super( recID, fieldvalues );
    },

    // 
    // this event handler is always called
    // 
    _afterSaveEventHandler2: function( recID, fieldvalues ){  

        if(this.it_was_insert){
            if(recID>0){ 
                if($Db.trm(recID)==null)
                    $Db.trm().addRecord(recID, fieldvalues);                

                var parent_id = $Db.trm(recID, 'trm_ParentTermID');                
                if(parent_id>0){
                    var t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
                    if(!t_idx[parent_id]) t_idx[parent_id] = []; 

                    //to avoid duplication                    
                    if(window.hWin.HEURIST4.util.findArrayIndex(recID, t_idx[parent_id])<0){
                        t_idx[parent_id].push(recID);    
                    }
                }else{
                    //this.options.trm_VocabularyGroupID = $Db.trm(recID,'trm_VocabularyGroupID');
                }
            }
            //this._filterByVocabulary();
            //this._loadData();
            /* INLINE EDITOR disabled 2020-12-01            
            //expand formlet after addition
            if(this.options.auxilary=='term'){
            var that = this;
            setTimeout(function(){
            that.recordList.resultList('expandDetailsInline',recID); 
            },1000);
            }
            */            
            /*}else if(this.options.auxilary=='vocabulary' && recID>0){
            //highlight in list 
            this.recordList.resultList('setSelected', [recID]);
            }else{*/
        }

        if(this.options.auxilary=='term' && fieldvalues &&
            !window.hWin.HEURIST4.util.isempty(fieldvalues['trm_InverseSymmetrical']))
        {

            //update inverse terms
            var inverse_termid = fieldvalues['trm_InverseTermId'];
            //var inverse_termid_old = @$record['old_inverse_id'];
            var is_symmetrical = (fieldvalues['trm_InverseSymmetrical']!=0);

            if(this.inverse_termid_old!=inverse_termid && is_symmetrical)
            {
                if(inverse_termid>0){
                    //set mutual inversion for inverse term
                    $Db.trm(inverse_termid, 'trm_InverseTermId', recID);
                }
                if (this.inverse_termid_old>0){
                    //clear mutual inversion for previous inversion
                    var invid = $Db.trm(this.inverse_termid_old, 'trm_InverseTermId');
                    if(invid==recID){
                        $Db.trm(this.inverse_termid_old, 'trm_InverseTermId', null);
                    }
                }
            }

        }

        this._triggerRefresh(this.options.auxilary);    

    },

    //
    // params: {trm_ID:trm_ID, trm_ParentTermID:trm_ParentTermID }
    //
    mergeTerms: function(params){

        var that = this;   


        var trm_ID = params['trm_ID'];
        var target_id = params['trm_ParentTermID'];

        var vocab_id = $Db.getTermVocab(target_id);
        var vocab_id2 = $Db.getTermVocab(trm_ID);
        if((this.options.trm_VocabularyID!=vocab_id) || (this.options.trm_VocabularyID!=vocab_id2))
        {
            window.hWin.HEURIST4.msg.showMsgFlash( 'Merge with reference is not allowed' ); 
            return;                
        }


        var parents = $Db.trm(target_id, 'trm_Parents');
        if(parents){
            parents = parents.split(',');
            if(window.hWin.HEURIST4.util.findArrayIndex(trm_ID, parents)>=0){
                window.hWin.HEURIST4.msg.showMsgFlash( 'Recursion is not allowed' ); 
                return;
            }
        }else{
            return;
        }    

        var $dlg, buttons = [
            {text:window.hWin.HR('Cancel'),
                //id:'btnRecCancel',
                css:{'float':'right',margin:'.5em .4em .5em 0'},  
                click: function() { $dlg.dialog( "close" ); }},
            {text:window.hWin.HR('Merge'),
                //id:'btnRecSave',
                css:{'float':'right',margin:'.5em .4em .5em 0'},  
                class: 'ui-button-action',
                click: function() { 

                    var request = {
                        'a'          : 'action',
                        'entity'     : that.options.entity.entityName,
                        'request_id' : window.hWin.HEURIST4.util.random(),
                        'merge_id'   : trm_ID,
                        'retain_id'  : target_id                 
                    };

                    var fieldvalues = {};

                    if($dlg.find('#term1_code_cb').is(':checked')){ 
                        request['trm_Code'] = $dlg.find('#term1_code').text();
                    }else if($dlg.find('#term2_code_cb').is(':checked')){ 
                        request['trm_Code'] = $dlg.find('#term2_code').text();
                    }
                    if($dlg.find('#term1_desc_cb').is(':checked')){ 
                        request['trm_Description'] = $dlg.find('#term1_desc').text();
                    }else if($dlg.find('#term2_desc_cb').is(':checked')){ 
                        request['trm_Description'] = $dlg.find('#term2_desc').text();
                    }     

                    if(request['trm_Code']) fieldvalues['trm_Code'] = request['trm_Code'];
                    if(request['trm_Description']) fieldvalues['trm_Description'] = request['trm_Description'];

                    window.hWin.HEURIST4.msg.bringCoverallToFront($dlg.parents('.ui-dialog'));                                             

                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                        function(response){
                            window.hWin.HEURIST4.msg.sendCoverallToBack();

                            if(response.status == window.hWin.ResponseStatus.OK){

                                //change parent for all children
                                $Db.trm_ChangeChildren(trm_ID, target_id);

                                $Db.trm_RemoveLinks(trm_ID);
                                $Db.trm().removeRecord(trm_ID);  //from record set

                                if(!$.isEmptyObject(fieldvalues))
                                    $Db.trm().addRecord(target_id, fieldvalues);                
                                that._filterByVocabulary();
                                that._triggerRefresh(that.options.auxilary);


                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);                        
                            }
                    });   


                    $dlg.dialog( "close" ); }}
        ];                


        $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
            +"hclient/widgets/entity/manageDefTermsMerge.html?t="+(new Date().getTime()), 
            buttons, 'Merge Terms', 
            {  container:'terms-merge-popup',
                width:500,
                height:400,
                close: function(){
                    //is_edit_widget_open = false;
                    $dlg.dialog('destroy');       
                    $dlg.remove();
                },
                open: function(){
                    //is_edit_widget_open = true;
                    //$('#terms-merge-popup')
                    $dlg.css({padding:0});

                    //init elements on dialog open
                    var val1 = $Db.trm(target_id,'trm_OriginatingDBID');
                    if(val1>0){
                        val1 = ' ['+val1+'-'+$Db.trm(target_id,'trm_IDInOriginatingDB')+']';
                    }else val1 = '';
                    var val2 = $Db.trm(trm_ID,'trm_OriginatingDBID');
                    if(val2>0){
                        val2 = ' ['+val2+'-'+$Db.trm(trm_ID,'trm_IDInOriginatingDB')+']';
                    }else val2 = '';

                    $dlg.find('#term1_id').text($Db.trm(target_id,'trm_Label')+val1);
                    $dlg.find('#term2_id').text($Db.trm(trm_ID,'trm_Label')+val2);

                    val1 = $Db.trm(target_id,'trm_Code');
                    $dlg.find('#term1_code').text(val1?val1:'<none>');
                    if(window.hWin.HEURIST4.util.isempty(val1)) window.hWin.HEURIST4.util.setDisabled($dlg.find('#term1_code_cb'),true);
                    $dlg.find('#term1_code_cb').prop('checked',!window.hWin.HEURIST4.util.isempty(val1));

                    val2 = $Db.trm(trm_ID,'trm_Code');
                    $dlg.find('#term2_code').text(val2?val2:'<none>');
                    if(window.hWin.HEURIST4.util.isempty(val2)) window.hWin.HEURIST4.util.setDisabled($dlg.find('#term2_code_cb'),true);
                    $dlg.find('#term2_code_cb').prop('checked',val2 && !val1);


                    val1 = $Db.trm(target_id,'trm_Description');
                    $dlg.find('#term1_desc').text(val1?val1:'<none>');
                    if(window.hWin.HEURIST4.util.isempty(val1)) window.hWin.HEURIST4.util.setDisabled($dlg.find('#term1_desc_cb'),true);
                    $dlg.find('#term1_desc_cb').prop('checked',!window.hWin.HEURIST4.util.isempty(val1));

                    val2 = $Db.trm(trm_ID,'trm_Description');
                    $dlg.find('#term2_desc').text(val2?val2:'<none>');
                    if(window.hWin.HEURIST4.util.isempty(val2)) window.hWin.HEURIST4.util.setDisabled($dlg.find('#term2_desc_cb'),true);
                    $dlg.find('#term2_desc_cb').prop('checked',val2 && !val1);

                    $dlg.find('input[type="checkbox"]').on({change:function(e){
                        var id = $(e.target).attr('id');
                        var id2 = id.indexOf('1')>0 ?id.replace('1','2') :id.replace('2','1');
                        if(!$dlg.find('#'+id2).is(':disabled')){
                            $dlg.find('#'+id2).prop('checked', !$(e.target).is(':checked'));
                        }                   
                    }});




                }  //end open event
        });


    },

    //
    // Change vocab group (for vocabularies) or parent for term
    //                                
    changeVocabularyGroup: function(params, no_check){                                    

        if(params['trm_ParentTermID']>0){

            var trm_ID = params['trm_ID'];
            var new_parent_id = params['trm_ParentTermID'];
            var old_parent_ids = [];

            var vocab_id = $Db.getTermVocab(trm_ID);
            var isRef = (this.options.trm_VocabularyID!=vocab_id);
            if (isRef) {
                var parents = $Db.trm(trm_ID, 'trm_Parents');
                if(parents){
                    old_parent_ids = parents.split(',');
                }
            }else{
                old_parent_ids = [$Db.trm(trm_ID, 'trm_ParentTermID')];    
            }

            if(old_parent_ids.length<1){
                console.log('Error !!! Parent not found for '+trm_ID);
                return;
            }

            //change parent within vocab
            if(window.hWin.HEURIST4.util.findArrayIndex(new_parent_id, old_parent_ids)>=0){ //the same
                //console.log('same parent nothing to change');            
                return;
            }

            //if new parent is vocabulary
            if( !($Db.trm(new_parent_id, 'trm_ParentTermID')>0) ){

                if(no_check!==true){    
                    //1. check that selected terms are already in this vocabulary
                    var trm_ids = $Db.trm_TreeData(new_parent_id, 'set'); //ids
                    if(window.hWin.HEURIST4.util.findArrayIndex(trm_ID, trm_ids)>=0){
                        window.hWin.HEURIST4.msg.showMsgDlg( (isRef?'Term':'Reference')
                            + ' "'+$Db.trm(trm_ID, 'trm_Label')
                            +'" is already in vocabulary "'+$Db.trm(new_parent_id,'trm_Label')+'"',null,'Duplication',
                            {default_palette_class:this.options.default_palette_class}); 
                        return;
                    }
                    //2. check there is not term with the same name
                    var trm_labels = $Db.trm_TreeData(new_parent_id, 'labels'); //labels in lowcase
                    var lbl = $Db.trm(trm_ID, 'trm_Label');
                    if(trm_labels.indexOf(lbl.toLowerCase())>=0){
                        window.hWin.HEURIST4.msg.showMsgDlg( (isRef?'Term':'Reference')
                            + ' with name "'+lbl
                            +'" is already in vocabulary "'+$Db.trm(new_parent_id,'trm_Label')+'"'
                            +'<p>To make this move, edit the term so that it is different from any in the top level '
                            +'of the vocabulary to which you wish to move it. Once moved, you can merge within '
                            +'the vocabulary or reposition the term and edit it appropriately.</p>'
                            ,null,'Duplication',
                            {default_palette_class:this.options.default_palette_class}); 
                        return;
                    }
                }

            }else{

                var vocab_id = $Db.getTermVocab(new_parent_id); //get real vocab
                if(this.options.trm_VocabularyID!=vocab_id){
                    window.hWin.HEURIST4.msg.showMsgDlg( 'Reference can\'t have children<br><br>'
                        + 'Please EDIT/move the term <i>'
                        + $Db.trm(new_parent_id, 'trm_Label') 
                        + '</i> within its original vocabulary <i>'
                        + $Db.trm(vocab_id, 'trm_Label') +'</i>'); 
                    return;
                }

                var parents = $Db.trm(new_parent_id, 'trm_Parents');
                if(parents){
                    parents = parents.split(',');
                    if(window.hWin.HEURIST4.util.findArrayIndex(trm_ID, parents)>=0){
                        window.hWin.HEURIST4.msg.showMsgFlash( 'Recursion is not allowed' ); 
                        return;
                    }
                }else{
                    return;
                }
            }


            var that = this;

            if(isRef){
                //change parent for reference   @todo - take correct old_parent_ids
                $Db.setTermReferences(new_parent_id, trm_ID, old_parent_ids[0],
                            function(){
                                that.it_was_insert = true;
                                that._afterSaveEventHandler2();//to reset filter and trigger global refresh
                            });
            }else{

                this._saveEditAndClose( params ,  //change in defTerms
                    function(){  
                        if(params.trm_ParentTermID>0){
                            //console.log('!!!!!! '+old_parent_ids[0]+' -> '+new_parent_id);                            
                            $Db.changeParentInIndex(new_parent_id, trm_ID, old_parent_ids[0]);
                            that._filterByVocabulary();
                        }
                        that._triggerRefresh('term');
                });

            }
        }else{
            var that = this;
            this._saveEditAndClose( params ,
                function(){
                    that._triggerRefresh('vcg');
            });

        }

    },

    //
    // extend for group actions
    //
    _onActionListener: function(event, action){

        var that = this;

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
            }else if(this.options.trm_VocabularyID<0){
                window.hWin.HEURIST4.msg.showMsgFlash('Vocabulary is not defined');                
                return true;        
            }
            //this.options.trm_VocabularyGroupID = recID;
            this.addEditRecord(-1);        
            return true;
        }else if(action=='add-child'){

            this.options.trm_ParentTermID = recID;
            this.addEditRecord(-1);        
            return true;

        }else if(action=='add-reference'){

            if(this.options.trm_VocabularyID<0){
                window.hWin.HEURIST4.msg.showMsgFlash('Vocabulary is not defined');                
                return true;        
            }            
            //open multi selector
            var popup_options = {
                select_mode:'select_multi',
                select_mode_reference: 'reference', 
                select_mode_target: $Db.trm(this.options.trm_VocabularyID,'trm_Label'),
                selectbutton_label: 'Add Reference',
                initial_filter: this.options.trm_VocabularyID,
                width: 350,
                height: Math.min(600,(window.hWin?window.hWin.innerHeight:window.innerHeight)*0.75),
                title: 'Add terms by reference',
                onselect:function(event, data){

                    var sels;
                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                        sels = data.selection.getIds();
                    }else{
                        sels = data.selection;
                    }
                    //add new term to vocabulary by reference
                    $Db.setTermReferences(that.options.trm_VocabularyID, sels, null,
                            function(){
                                that.it_was_insert = true;
                                that._afterSaveEventHandler2();//to reset filter and trigger global refresh
                            });
                }
            }

            // to vocabulary "'+ $Db.trm(this.options.trm_VocabularyID,'trm_Label')+ '"'

            window.hWin.HEURIST4.ui.showEntityDialog('defTerms', popup_options);

        }else if(action=='edit-inline'){

            this.recordList.resultList('expandDetailsInline', recID);

            return true;

        }else if(action=='delete'){

            if(this.options.auxilary=='vocabulary'){


                //find usage of this vocab in enum base fields
                var refs = [];               
                $Db.dty().each2(function(dty_ID, record){ 

                    var dty_Type = record['dty_Type'];
                    if((dty_Type=='enum' || dty_Type=='relmarker') && 
                        (recID == record['dty_JsonTermIDTree'])) 
                    {
                        refs.push( dty_ID );        
                    }
                });

                if(refs.length>0){

                    this._showWarningAboutUsage(recID, refs);
                    return;
                }

                //check for children 
                if($Db.trm_HasChildren(recID)){

                    window.hWin.HEURIST4.msg.showMsgDlg(
                        'Delete vocabulary <b>'+$Db.trm(recID, 'trm_Label')+'</b> and all its child terms?',
                        function(){ that._currentEditID = recID; that._deleteAndClose(true) }, 
                        {title:'Deletion of vocabulary',yes:'Proceed',no:'Cancel'},
                        {default_palette_class:this.options.default_palette_class});        
                    return false;                    
                }               


            }else{

                //check for reference    
                var ref_lvl = $Db.isTermByReference(this.options.trm_VocabularyID, recID);

                if(ref_lvl===false){//this term is not a reference

                    var sMsg = '';

                    //check usage as reference
                    var v_ids = $Db.trm_getAllVocabs(recID);
                    if(v_ids.length>1){
                        var names = [];
                        for(var i=0; i<v_ids.length; i++){
                            names.push($Db.trm(v_ids[i],'trm_Label'));    
                        }

                        sMsg = '<p>The term you are deleting is referenced by the '+names.join(', ')
                        +' vocabularies. If you delete it it will be removed from those vocabularies.</p>';
                    }

                    //check for children 
                    if($Db.trm_HasChildren(recID)){
                        sMsg += '<p>Delete branch <b>'+$Db.trm(recID, 'trm_Label')+'</b> and all its child terms?</p>'
                    }               

                    if(sMsg){
                        window.hWin.HEURIST4.msg.showMsgDlg(
                            sMsg,
                            function(){ that._currentEditID = recID; that._deleteAndClose(true) }, 
                            {title:'Deletion of branch',yes:'Proceed',no:'Cancel'},
                            {default_palette_class:this.options.default_palette_class});        
                        return false;                    
                    }

                }else if(ref_lvl>0){

                    window.hWin.HEURIST4.msg.showMsgDlg('This term is added as reference via its parent. '
                        +'You can remove this reference along with it parents only',null,null,
                        {default_palette_class:this.options.default_palette_class});
                    return;

                }else{

                    window.hWin.HEURIST4.msg.showMsgDlg(
                        'You are going to remove the term reference. Actual term is retained.', 
                        function(){ 
                            //find parent 
                            var parents = $Db.trm(recID, 'trm_Parents');
                            if(parents){
                                parents = parents.split(',');
                                var parent_id = parents[parents.length-1]; 
                                if(parent_id>0){
                                    //removing entry in trm links
                                    $Db.setTermReferences(null, recID, parent_id,
                                        function(){
                                            that.it_was_insert = true;
                                            that._afterSaveEventHandler2();//to reset filter and trigger global refresh
                                        });
                                }
                            }
                        }, 
                        {title:'Info',yes:'Proceed',no:'Cancel'},
                        {default_palette_class:this.options.default_palette_class});        
                    return true;

                }

            }

            //remove unconditionally
            this._currentEditID = recID; 
            this._deleteAndClose();
            return true;
        }
        //this.options.trm_VocabularyGroupID = -1;

        else if(action=='expand'){

            var is_collapsed = window.hWin.HEURIST4.ui.collapsed_terms.indexOf(recID)>=0;

            $.each(this.recordList.find('div.recordDiv[data-parent]'),
                function(i,item){
                    item = $(item);
                    if(item.attr('data-parent').indexOf(';'+recID+';')>=0){
                        //if(item.is(':visible')){
                        if(is_collapsed){
                            item.show();
                        }else{
                            item.hide();
                        }
                    }
            });

            is_collapsed =!is_collapsed;

            var ele = this.recordList.find('div.recordDiv[recID='+recID+']')
            .find('span.ui-icon-triangle-1-'+(is_collapsed?'s':'e'));
            if(is_collapsed){
                window.hWin.HEURIST4.ui.collapsed_terms.push(recID);
                ele.removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-e');
            }else{
                window.hWin.HEURIST4.ui.collapsed_terms.splice(window.hWin.HEURIST4.ui.collapsed_terms.indexOf(recID), 1);
                ele.removeClass('ui-icon-triangle-1-e').addClass('ui-icon-triangle-1-s');
            }
        }

        var is_resolved = this._super(event, keep_action);

        if(!is_resolved){
            /*
            if(action && action.action){
            recID =  action.recID;
            action = action.action;
            }
            */            
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
            'Are you sure you wish to delete this vocabulary type?', function(){

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
            if(action=='term-import'){

                if(this.options.auxilary=='vocabulary'){
                    this.importTerms(this.options.trm_VocabularyGroupID, true);
                }else{
                    this.importTerms(this.options.trm_VocabularyID, false);    
                }

            }else if(action=='term-export'){

                if(this.options.auxilary=='vocabulary'){
                    this.exportTerms(this.options.trm_VocabularyGroupID, true);
                }else{
                    this.exportTerms(this.options.trm_VocabularyID, false);    
                }


            }else if(action=='viewmode-list'){

                if(this.recordTree) this.recordTree.hide();      
                this.recordList.show();
                this.recordList.resultList('applyViewMode','list');

            }else if(action=='viewmode-thumbs'){

                if(this.recordTree) this.recordTree.hide();      
                this.recordList.show();
                this.recordList.resultList('applyViewMode','thumbs');

            }else if(action=='viewmode-tree'){ //NOT USED - @todo rempove all recordTree mentions

                if(!this.recordTree){
                    this.recordTree = $('<div class="ent_content_full" style="display:none;"/>')
                    .addClass('ui-heurist-bg-light')
                    .css({'font-size':'0.8em'})
                    .insertAfter(this.recordList);

                    //this.getRecordSet()
                    /*                                   
                    var treedata = this.recordList.resultList('getRecordSet')
                    .getTreeViewData('trm_Label', 'trm_ParentTermID',
                    this.options.trm_VocabularyID);
                    */                                                 
                    var treedata = $Db.trm_TreeData(this.options.trm_VocabularyID, 'tree');

                    this.recordTree.fancytree(
                        {
                            activeVisible:true,
                            checkbox: false,
                            source:treedata,
                            activate: function(event, data){
                                // A node was activated: display its details
                                //_onNodeClick(data);
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

                    var is_valid_domain = true;
                    //get vocabulary group
                    if(that.options.filter_groups){
                        is_valid_domain = ($Db.trm(ids[ids.length-1], 'trm_Domain')==that.options.filter_groups);
                    }

                    if(is_valid_domain){

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

                                if(that.options.select_mode!='manager'){
                                    that.vocabularies_sel.val(vocab_id);//.change();
                                }else{
                                    that.vocabularies_div.manageDefTerms('selectVocabulary', vocab_id);
                                }

                                if(trm_IDs.length>1){
                                    setTimeout(function(){that.selectRecordInRecordset(trm_IDs[0]);},500);    
                                }



                                input_name.val('').focus();
                            }
                        });
                    }

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

    _getValidatedValues: function(){

        var fields = this._super();  
        if(fields!==null){
            var is_already_exists = false;
            var vocab_id = fields['trm_ParentTermID'];
            if(vocab_id>0){
                vocab_id = $Db.getTermVocab(vocab_id);   
                var trm_id = fields['trm_ID'];
                var lbl = fields['trm_Label'].toLowerCase();

                if(trm_id<0){ //new one
                    var all_labels = $Db.trm_TreeData(vocab_id, 'labels');
                    is_already_exists = (all_labels.indexOf(lbl)>=0);
                }else{ //existed one
                    var all_labels = $Db.trm_TreeData(vocab_id, 'select');
                    for(var i=0; i<all_labels.length; i++){
                        if(all_labels[i].title.toLowerCase()==lbl && all_labels[i].key!=trm_id){
                            is_already_exists = true;
                            break;
                        }
                    }
                }

                if(is_already_exists){    
                    window.hWin.HEURIST4.msg.showMsgFlash('Term with label "'
                        +fields['trm_Label']+'" already exists in vocabulary',1500);
                    return null;
                }
            }
        }

        return fields;

    },


    //
    //
    getUiPreferences:function(){
        this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, {
            help_on: true
        });

        return this.usrPreferences;
    },

    //    
    saveUiPreferences:function(){
        //console.log('save prefs '+'prefs_'+this._entityName);        
        window.hWin.HAPI4.save_pref('prefs_'+this._entityName, this.usrPreferences);

        return true;
    },

    //
    // invokes popup to import list of terms from file
    //
    importTerms: function(parent_ID, isVocab) {


        if(isVocab){

            sTitle = 'Import vocabularies (excluding terms) for vocabulary group: '+
            window.hWin.HEURIST4.util.htmlEscape($Db.vcg(parent_ID,'vcg_Name'));                
        }else{
            var isTerm = ($Db.trm(parent_ID,'trm_ParentTermID')>0);

            sTitle = 'Import terms '+(isTerm?'as children of term: ' :'of vocabulary: ')+
            window.hWin.HEURIST4.util.htmlEscape($Db.trm(parent_ID,'trm_Label'));                
        }

        var that = this;

        var sURL = window.hWin.HAPI4.baseURL + "import/delimited/importDefTerms.php?db="
        + window.hWin.HAPI4.database +
        (isVocab?'&vcg_ID=':'&trm_ID=')+parent_ID;


        window.hWin.HEURIST4.msg.showDialog(sURL, {
            default_palette_class: 'ui-heurist-design',
            "close-on-blur": false,
            "no-resize": false,                  
            title: sTitle,
            height: 600,
            width: 900,
            'context_help':window.hWin.HAPI4.baseURL+'context_help/defTerms.html #import',
            callback: function(context){ 

                if(context && context.result)
                {
                    if(that.options.auxilary=='vocabulary'){
                        that._loadData(); //reload
                    }else{
                        that._filterByVocabulary();    
                    }


                    window.hWin.HEURIST4.msg.showMsgDlg(context.result.length
                        + ' term'
                        + (context.result.length>1?'s were':' was')
                        + ' added.', null, 'Terms imported',
                        {default_palette_class:that.options.default_palette_class});

                }


            }
        });

    },

    //
    // invokes popup to import list of terms from file
    //
    exportTerms: function(parent_ID, isVocab) {

        var trm_Children = [];
        if(isVocab){
            var vocabs = $Db.trm_getVocabs();
            $.each(vocabs, function(i,trm_ID){  
                if($Db.trm(trm_ID, 'trm_VocabularyGroupID')==parent_ID){
                    trm_Children.push(trm_ID);
                }
            });
            /*
            $Db.trm().each(function(trm_ID,rec){
            if($Db.trm(trm_ID, 'trm_VocabularyGroupID')==parent_ID && !($Db.trm(trm_ID, 'trm_ParentTermID')>0)){
            trm_Children.push(trm_ID);        
            }
            });     
            */
            s = 'Vocabulary,Internal code,Vocabulary group,Group internal code,Standard Code,Description\n';
        }else{
            trm_Children = $Db.trm_TreeData(parent_ID, 'set');
            s = 'Term,Internal code,Parent term,Parent internal code,Standard Code,Description\n';
        }   


        for(var i=0; i<trm_Children.length; i++){
            var trm_ID = trm_Children[i];
            var term = $Db.trm(trm_ID);

            var aline = ['"'+term['trm_Label']+'"',trm_ID,'',0,'"'+term['trm_Code']+'"','"'+term['trm_Description']+'"'];

            if(isVocab){
                var parent_ID = term['trm_VocabularyGroupID'];
                aline[2] = '"'+$Db.vcg(parent_ID,'vcg_Name')+'"';
                aline[3] = parent_ID;
            }else{
                var parent_ID = term['trm_ParentTermID'];
                aline[2] = '"'+$Db.trm(parent_ID,'trm_Label')+'"';
                aline[3] = parent_ID;
            }

            s = s + aline.join(',') + '\n';
        }


        window.hWin.HEURIST4.util.downloadData('heurist_vocabulary.csv', s, 'text/csv');
    },


    //
    //
    //
    _showWarningAboutUsage: function(recID, refs){
        var sList = '';
        for(var i=0; i<refs.length; i++) if(refs[i]>0){
            sList += ('<a href="#" data-dty_ID="'+refs[i]+'">'+$Db.dty(refs[i],'dty_Name')+'</a><br>');
        }

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
            '<p>Vocabulary <b>'+$Db.trm(recID,'trm_Label')+'</b> is referenced by the following fields:</p>'
            + sList
            +'<p>Please remove these fields altogether, or click the links above <br>to modify base field (will affect all record types which use it).</p>'
            , null, {title:'Warning'},
            {default_palette_class:this.options.default_palette_class});        

        this._on($dlg.find('a[data-dty_ID]'),{click:function(e){

            var rg_options = {
                isdialog: true, 
                edit_mode: 'editonly',
                select_mode: 'manager',
                rec_ID: $(e.target).attr('data-dty_ID'),
                onSelect:function(res){
                }
            };
            window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypes', rg_options);
            return false;                    
        }});
    },



});
