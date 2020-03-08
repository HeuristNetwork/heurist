/**
* manageDefDetailTypes.js - main widget for field types
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


$.widget( "heurist.manageDefDetailTypes", $.heurist.manageEntity, {

    _entityName:'defDetailTypes',
    
    //
    //
    //    
    _init: function() {
        
        this.options.layout_mode = 'short';
        this.options.use_cache = true;
        this.options.use_structure = true;
        //this.options.edit_mode = 'popup';
        
        //this.options.select_return_mode = 'recordset';
        this.options.edit_need_load_fullrecord = false; //true;
        this.options.height = 640;
        this.options.edit_width = 850;
        this.options.edit_height = 640;

        if(this.options.edit_mode=='editonly'){
            this.options.edit_mode = 'editonly';
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
            this.options.width = 850;
            this.options.height = 680;
        }else
        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<750)?750:this.options.width;                           
            //this.options.edit_mode = 'none';
        }
        if(this.options.edit_mode == 'inline' && this.options.select_mode=='manager'){
            this.options.width = 1290;
        }

        this._super();

        if(this.options.edit_mode == 'inline'){
            
            if(this.options.select_mode!='manager'){
                //hide form 
                this.editForm.parent().hide();
                this.recordList.parent().css('width','100%');
            }else{
                this.recordList.parent().css('width',380);
                this.editForm.parent().css('left',381);
            }
        }
    
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
        
        //update dialog title
        if(this.options.isdialog){ // &&  !this.options.title
            var title = null;
            
            if(this.options.title){
                title = this.options.title;
            }else
            if(this.options.select_mode=='select_single'){
               title = 'Select Field Type'; 
            }else
            if(this.options.select_mode=='select_multi'){
               title = 'Select Field Types'; 
              
              if(this.options.dtg_ID<0){ //select fieldtype from groups except given one
                    title += ' to add to group '+window.hWin.HEURIST4.detailtypes.groups[Math.abs(this.options.dtg_ID)].name;
              }
               
            }else
            if(this.options.dtg_ID>0){
                title = 'Manage Field types of group '+window.hWin.HEURIST4.detailtypes.groups[this.options.dtg_ID].name;
            }else{
                title = 'Manage Field Types';    
            }
            
            this._as_dialog.dialog('option','title', title);    
        }
        
        // init search header
        this.searchForm.searchDefDetailTypes(this.options);
        
        var iheight = 7;
        if(this.options.edit_mode=='inline'){            
            iheight = iheight + 6;
        }else{
            this.searchForm.css({'min-width': '730px'});
            this.recordList.css({'min-width': '730px'});
        }
        //init viewer 
        var that = this;
        
        this.searchForm.css({'height':iheight+'em',padding:'10px'});
        this.recordList.css({'top':iheight+0.5+'em'});
        
        this.recordList.resultList('option', 'show_toolbar', false);
        this.recordList.resultList('option', 'pagesize', 9999);
        this.recordList.resultList('option', 'view_mode', 'list');
        
        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
            
            
            this.recordList.resultList('option','rendererHeader',
                    function(){
                    var s = '<div style="width:10px"></div><div style="width:3em">ID</div>'
                                +'<div style="width:13em">Name</div>'
                                +(that.options.edit_mode!='inline'
                                    ?'<div style="width:22em;">Description</div>':'')
                                +'<div style="width:13em">Data Type</div>';    
                        
                        if (window.hWin.HAPI4.is_admin()){
                            s = s+'<div style="position:absolute;right:4px;width:60px">Edit</div>';
                        }
                        
                        return s;
                    }
                );
            //this.recordList.resultList('applyViewMode');
        }

        
        if(this.options.use_cache){

            if(this.options.use_structure){
                //take recordset from HEURIST.detailtypes format     
                this._cachedRecordset = this._getRecordsetFromStructure();
                this.recordList.resultList('updateResultSet', this._cachedRecordset);
            }else{
                //usual way from server
                var that = this;
                window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, false,
                    function(response){
                        that._cachedRecordset = response;
                        that.recordList.resultList('updateResultSet', response);
                    });
            }
                
            this._on( this.searchForm, {
                "searchdefdetailtypesonfilter": this.filterRecordList
                });
                
        }    
            
        
        this._on( this.searchForm, {
                "searchdefdetailtypesonresult": this.updateRecordList,
                "searchdefdetailtypesonadd": function() { this.addEditRecord(-1); }
                });


        
        return true;
    },            
    
    //
    // get recordset from HEURIST4.detailtypes
    //
    _getRecordsetFromStructure: function(){
        
        var rdata = { 
            entityName:'defDetailTypes',
            total_count: 0,
            fields:[],
            records:{},
            order:[] };

        var detailtypes = window.hWin.HEURIST4.detailtypes;

        rdata.fields = detailtypes.typedefs.commonFieldNames;
        rdata.fields.unshift('dty_ID');


        for (var r_id in detailtypes.typedefs)
        {
            if(r_id>0){
                var dtype = detailtypes.typedefs[r_id].commonFields;
                dtype.unshift(r_id);
                rdata.records[r_id] = dtype;
                rdata.order.push( r_id );
            }
        }
        rdata.count = rdata.order.length;

        return new hRecordSet(rdata);
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
        
        var is_narrow = (this.options.edit_mode=='inline');
        
        var recID   = fld('dty_ID');
        
        var recTitle = fld2('dty_ID','4em')
                + fld2('dty_Name','14em')
                + (is_narrow?'':('<div class="item inlist" style="width:25em;">'+fld('dty_HelpText')+'</div>'))
                + '<div class="item'+(is_narrow?'':' inlist')+'" style="width:10em;">'+window.hWin.HEURIST4.detailtypes.lookups[fld('dty_Type')]+'</div>';

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID
                    +'" style="height:'+(is_narrow?'1.3':'2.5')+'em">';
                    
        if(this.options.select_mode=='select_multi'){
            html = html + '<div class="recordSelector"><input type="checkbox" /></div><div class="recordTitle" style="left:24px">';
        }else{
             html = html + '<div class="recordTitle" style="left:24px">' 
        }
                
        html = html + recTitle + '</div>';
        
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
            this._selectAndEditFirstInRecordset(data.recordset);
        }
    },
        
    //
    // can remove group with assigned fields
    //     
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            
            var usage = window.hWin.HEURIST4.detailtypes.rectypeUsage[this._currentEditID];
            if(usage && usage.length>0){ 
                window.hWin.HEURIST4.msg.showMsgFlash('Field in use in '+usage.length+' record types. Can\'t remove it');  
                return;                
            }
            
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this field type? Proceed?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
        
    },
    
    //
    // extend dialog button bar
    //    
    _initEditForm_step3: function(recID){
        
        if(this._toolbar){
            var usage = window.hWin.HEURIST4.detailtypes.rectypeUsage[this._currentEditID];
            
            this._toolbar.find('.ui-dialog-buttonset').css({'width':'100%','text-align':'right'});
            this._toolbar.find('#btnRecDelete').css('display', 
                    (recID>0 && !(usage && usage.length>0)) ?'block':'none');
        }
        
        this._super(recID);
    },
    
    onEditFormChange: function( changed_element ){
        this._super(changed_element);
            
        if(this._toolbar){
            var usage = window.hWin.HEURIST4.detailtypes.rectypeUsage[this._currentEditID];
            
            var isChanged = this._editing.isModified();
            this._toolbar.find('#btnRecDelete').css('display', 
                    (isChanged || (usage && usage.length>0))?'none':'block');
        }
            
    },  
      
    _getEditDialogButtons: function(){
                                    
            var that = this;        
            
            var btns = [       /*{text:window.hWin.HR('Reload'), id:'btnRecReload',icons:{primary:'ui-icon-refresh'},
                click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form*/
                      
                {showText:true, icons:{primary:'ui-icon-plus'},text:window.hWin.HR('Define New Field Type'),
                      css:{'margin-right':'0.5em','float':'left'}, id:'btnAddButton',
                      click: function() { that._onActionListener(null, 'add'); }},

                {text:window.hWin.HR('Save Order'),
                      css:{'float':'left',display:'none'}, id:'btnApplyOrder',
                      click: function() { that._onActionListener(null, 'save-order'); }},
                      
                      
                {text:window.hWin.HR('Close'), 
                      css:{'margin-left':'3em','float':'right'},
                      click: function() { 
                          that.closeDialog(); 
                      }},
                {text:window.hWin.HR('Drop Changes'), id:'btnRecCancel', 
                      css:{'margin-left':'0.5em','float':'right'},
                      click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form
                {text:window.hWin.HR('Save'), id:'btnRecSave',
                      accesskey:"S",
                      css:{'font-weight':'bold','float':'right'},
                      click: function() { that._saveEditAndClose( null, 'none' ); }},
                {text:window.hWin.HR('Delete'), id:'btnRecDelete',
                      css:{'float':'right',display:'none'},
                      click: function() { that._onActionListener(null, 'delete'); }},
                      
                      ];
        
            return btns;
    },    
    
     
    //-----
    //
    // adding group ID value for new field type
    // and perform some after load modifications (show/hide fields,tabs )
    //
    _afterInitEditForm: function(){

        this._super();
        
        var dty_DetailTypeGroupID = this.searchForm.find('#input_search_group').val();
        if(dty_DetailTypeGroupID>0 && !this._currentEditRecordset){ //insert       

            var ele = this._editing.getFieldByName('dty_DetailTypeGroupID');
            ele.editing_input('setValue', dty_DetailTypeGroupID);
            //hide save button
            if(this._toolbar){
                this._toolbar.find('#btnRecSave').css('visibility', 'visible');
            }
        }else
        //hide after edit init btnRecRemove for status locked 
        if(this._toolbar){ //@todo
            this._toolbar.find('#btnRecDelete').hide();
        }
        
        //fill init values of virtual fields
        //add lister for dty_Type field to show hide these fields
        var elements = this._editing.getInputs('dty_Type');
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(elements)){
            this._on( $(elements[0]), {    
                'change': function(event){
                       var dt_type = $(event.target).val();
                       
                       var virtual_fields = this._editing.getFieldByValue("dty_Role","virtual");
                       for(var idx in virtual_fields){
                           $(virtual_fields[idx]).hide();
                       }
//this._editing.getFieldByName('dty_TermIDTreeNonSelectableIDs').hide();
//this._editing.getFieldByName('dty_JsonTermIDTree').hide();
                       
                       var ele = this._editing.getFieldByName('dty_PtrTargetRectypeIDs');
                       
                       if(dt_type!=='resource'){
                           ele.hide();
                           ele = this._editing.getFieldByName('dty_Mode_'+dt_type);
                           
                           if(dt_type=='enum'){
//this._editing.getFieldByName('dty_TermIDTreeNonSelectableIDs').show();
//this._editing.getFieldByName('dty_JsonTermIDTree').show();
                                this._activateEnumControls(ele);
                           }
                       }

                       if(ele && ele.length>0) ele.show();
                       
                       
                       
                    
                }
                
            });
            
            $(elements[0]).change(); //trigger
        }

    },    
    
    _activateEnumControls: function( ele ){
        
            var ele = ele.find('.input-div');
            
            if(ele.find('#enumVocabulary').length>0) return; //already inited
            
            this.enum_container = ele;
            
            $('<div style="line-height:2ex;padding-top:4px">'
                    +'<label style="text-align:left;line-height:19px;vertical-align:top">'
                    +'<input type="radio" value="vocabulary" name="enumType" style="vertical-align: top;">&nbsp;Use a vocabulary</label> '
                    +'<div id="enumVocabulary" style="display:inline-block;padding-left:4px;">'
                        +'<select id="selVocab" class="sel_width"></select>'
                        +'<span id="termsPreview1"></span>'
                        +'<div style="font-size:smaller">'
                            +'<a href="#" id="add_vocabulary">add a vocabulary</a>&nbsp;'
                            +'<a href="#" id="add_terms" style="padding-left:10px">add terms to vocabulary</a>&nbsp;'
                            +'<a href="#" id="show_terms_1" style="padding-left:10px">edit terms tree</a>'
                        +'</div>'
                    +'</div>'
            +'</div><div style="padding-top:4px">'
                    +'<label style="text-align:left;line-height: 12px;">'
                    +'<input type="radio" value="individual" name="enumType" style="margin-top:0px">&nbsp;Select terms individually</label> '
                    +'<div  id="enumIndividual" style="display:none;padding-left:4px;">'
                        +'<input type="button" value="Select terms" id="btnSelectTerms" style="margin-right:4px"/>'                    
                        +'<span id="termsPreview2"></span>'
                        +'<a href="#" id="show_terms_2">edit terms tree</a>'
                    +'</div>'
                    +'<div style="font-style:italic;padding: 4px 0px">'
                        +'Warning: Advanced users only - list must be updated manually if relevant new terms added</div>'
            +'</div>').appendTo(this.enum_container);
                
            //create event listeneres
            this._on(this.enum_container.find('input[name="enumType"]'),{change:
                function(event){
                    if($(event.target).val()=='individual'){
                        this.enum_container.find('#enumIndividual').css('display','inline-block');//show();
                        this.enum_container.find('#enumVocabulary').hide();
                    }else{
                        this.enum_container.find('#enumIndividual').hide();
                        this.enum_container.find('#enumVocabulary').css('display','inline-block');//show();
                    }
                }});
            this._on(this.enum_container.find('#add_vocabulary'),{click: this._onAddVocabOrTerms});
            this._on(this.enum_container.find('#add_terms'),{click: this._onAddVocabOrTerms});
            this._on(this.enum_container.find('#show_terms_1'),{click: this._showOtherTerms});
            this._on(this.enum_container.find('#show_terms_2'),{click: this._showOtherTerms});
            
            this.enum_container.find('#btnSelectTerms').button();
            this._on(this.enum_container.find('#btnSelectTerms'),{click: this._onSelectTerms});
            
            this._recreateTermsVocabSelector();
            //this._recreateTermsPreviewSelector();
    },
    
    /**
    * _onAddVocabOrTerms
    *
    * Add new vocabulary or add child to currently selected
    */
    _onAddVocabOrTerms: function(event){
        
        var is_add_vocab = ($(event.target).attr('id')=='add_vocabulary');

        
        var term_type = this._editing.getValue('dty_Type')[0];
        var dt_name = this._editing.getValue('dty_Name')[0];

        if(term_type!="enum"){
            term_type="relation";
        }

        var vocab_id =  this.enum_container.find("#selVocab").val();
        var is_frist_time = true;
        var that = this;

        var sURL = window.hWin.HAPI4.baseURL +
        "admin/structure/terms/editTermForm.php?treetype="+term_type
            +"&parent="+(is_add_vocab?0:vocab_id)
            +"&db="+window.hWin.HAPI4.database;
            
        window.hWin.HEURIST4.msg.showDialog(sURL, {

            "close-on-blur": false,
            "no-resize": true,
            noClose: true, //hide close button
            title: 'Edit Vocabulary',
            height: 340,
            width: 700,
            onpopupload:function(dosframe){
                //define name for new vocabulary as field name + vocab
                var ele = $(dosframe.contentDocument).find('#trmName');
                if(is_add_vocab && is_frist_time){
                    is_frist_time = false;
                    if( !window.hWin.HEURIST4.util.isempty(dt_name)){
                        ele.val( dt_name+' vocab' );    
                    }
                }
                ele.focus();
            },
            callback: function(context) {
                if(context!="") {

                    if(context=="ok"){    //after edit term tree
                        that._recreateTermsVocabSelector();
                        //that._recreateTermsPreviewSelector();
                    }else if(!window.hWin.HEURIST4.util.isempty(context)) { //after add new vocab
                        that._editing.setFieldValueByName('dty_JsonTermIDTree', context);
                        that._editing.setFieldValueByName('dty_TermIDTreeNonSelectableIDs', '');
                        that._recreateTermsVocabSelector();
                        //that._recreateTermsPreviewSelector();
                    }
                }
            }
        });

    },

    /**
    * _onSelectTerms
    *
    * Shows a popup window where user can select terms to create a term tree as wanted
    */
    _onSelectTerms: function(){

        var dt_name = this._editing.getValue('dty_Name')[0];
        var allTerms = this._editing.getValue('dty_JsonTermIDTree')[0];
        var disTerms = this._editing.getValue('dty_TermIDTreeNonSelectableIDs')[0];
        
        var term_type = this._editing.getValue('dty_Type')[0];
        if(term_type!="enum"){
            term_type="relation";
        }

        var sURL = window.hWin.HAPI4.baseURL +
        "admin/structure/terms/selectTerms.html?dtname="+dt_name+"&datatype="+term_type
            +"&all="+allTerms+"&dis="+disTerms+"&db="+window.hWin.HAPI4.database;
            
        var that = this;

        window.hWin.HEURIST4.msg.showDialog(sURL, {
            "close-on-blur": false,
            "no-resize": true,
            noClose: true, //hide close button
            title: 'Select terms',
            height: 500,
            width: 750,
            callback: function(editedTermTree, editedDisabledTerms) {
                if(editedTermTree || editedDisabledTerms) {
                    //update hidden fields
                    that._editing.setFieldValueByName('dty_JsonTermIDTree', editedTermTree);
                    that._editing.setFieldValueByName('dty_TermIDTreeNonSelectableIDs', editedDisabledTerms);
                    that._recreateTermsPreviewSelector();
                }
            }
        });

    },

    _showOtherTerms:function(event){

        var term_type = this._editing.getValue('dty_Type')[0];
        if(term_type!="enum"){
            term_type="relation";
        }

        var sURL = window.hWin.HAPI4.baseURL + "admin/structure/terms/editTerms.php?"+
        "popup=1&treetype="+term_type+"&db="+window.hWin.HAPI4.database;

        var vocab_id = 0;

        var is_vocab = ($(event.target).attr('id')=='show_terms_1');
        if(is_vocab){
            var vocab_id =  this.enum_container.find("#selVocab").val();
            sURL = sURL + '&vocabid='+vocab_id;
        }

        var that = this;
        
        window.hWin.HEURIST4.msg.showDialog(sURL, {
            "close-on-blur": false,
            "no-resize": false,
            title: (term_type=='relation')?'Manage Relationship types':'Manage Terms',
            height: (term_type=='relation')?820:780,
            width: 950,
            afterclose: function() {
                that._recreateTermsVocabSelector();
                //_recreateTermsPreviewSelector();
            }
        });

    },    
    
    //
    //
    //
    _recreateTermsVocabSelector: function(){
        
        var allTerms = this._editing.getValue('dty_JsonTermIDTree')[0];
        
        var term_type = this._editing.getValue('dty_Type')[0];
        if(term_type!="enum"){
            term_type="relation";
        }
        
        var defaultTermID = null;
        if(window.hWin.HEURIST4.util.isNumber(allTerms)){
            defaultTermID = allTerms; //vocabulary
            this.enum_container.find('input[name="enumType"][value="vocabulary"]').prop('checked',true).trigger('change');
                    
            var orig_selector = this.enum_container.find("#selVocab");
                    
            var selnew = window.hWin.HEURIST4.ui.createTermSelectExt2(orig_selector[0], 
            {vocabsOnly:true, datatype:term_type, topOptions:'select...', useHtmlSelect:false, defaultTermID:defaultTermID })

            this._off(orig_selector, 'change');
            this._on(orig_selector, {change: function(event){
                this._editing.setFieldValueByName('dty_JsonTermIDTree', $(event.target).val());
                this._editing.setFieldValueByName('dty_TermIDTreeNonSelectableIDs', '');
                this._recreateTermsPreviewSelector();
            }});
            
        }else{
            this.enum_container.find('input[name="enumType"][value="individual"]').prop('checked',true).trigger('change');
        }
        
        
        //el_sel.onchange =  _changeVocabulary;
        //el_sel.style.maxWidth = '120px';
        this._recreateTermsPreviewSelector();
        
    },
    
    //
    //
    //
    _recreateTermsPreviewSelector: function(){

        var allTerms = this._editing.getValue('dty_JsonTermIDTree')[0];

        //remove old selector
        var preview_sel = this.enum_container.find("#termsPreview1");
        
        preview_sel.empty();
        this.enum_container.find('#termsPreview2').empty();

        if(!window.hWin.HEURIST4.util.isempty(allTerms)) {

            var disTerms = this._editing.getValue('dty_TermIDTreeNonSelectableIDs')[0];
            
            var term_type = this._editing.getValue('dty_Type')[0];
            if(term_type!="enum"){
                term_type="relation";
            }
            
            var new_selector = window.hWin.HEURIST4.ui.createTermSelectExt2(null,
                {datatype:term_type, termIDTree:allTerms, headerTermIDsList:disTerms,
                    defaultTermID:null, topOptions:false, supressTermCode:true, useHtmlSelect:false});
            
            new_selector.css({'backgroundColor':'#cccccc','min-width':'120px','max-width':'120px','margin':'0px 4px'})
                    .change(function(event){event.target.selectedIndex=0;}).show();
            
            //append to first preview
            preview_sel
                .append($('<label style="width:60px;min-width:60px">Preview</label>'))
                .append(new_selector); 
            preview_sel.css({'padding-left':'10px'});
            //append to second preview    
            $('#termsPreview2')
                .append($('<label style="width:60px;min-width:60px">Preview</label>'))
                .append(new_selector.clone());
        }
        
    },

    //--------------------------------------------------------------------------
    //
    // update list after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){

        // close on addition of new record in select_single mode    
        if(this._currentEditID<0 && this.options.select_mode=='select_single'){
            
                this._selection = new hRecordSet();
                //{fields:{}, order:[recID], records:[fieldvalues]});
                this._selection.addRecord(recID, fieldvalues);
                this._selectAndClose();
                return;    
                    
        }
        
        this._super( recID, fieldvalues );
        this.getRecordSet().setRecord(recID, fieldvalues);
        
        if(this.options.edit_mode == 'editonly'){
            this.closeDialog(true); //force to avoid warning
        }else{
            this.recordList.resultList('refreshPage');  
        }
    },

    //  -----------------------------------------------------
    //
    // perform special action for virtual fields 
    //
    _getValidatedValues: function(){
        
        //fieldvalues - is object {'xyz_Field':'value','xyz_Field2':['val1','val2','val3]}
        var fieldvalues = this._super();
        
        if(fieldvalues!=null){
            var data_type =  fieldvalues['dty_Type'];
            if(data_type=='freetext' || data_type=='blocktext' || data_type=='date'){
                var val = fieldvalues['dty_Type_'+data_type];
                
                fieldvalues['dty_JsonTermIDTree'] = val;
                delete fieldvalues['dty_Type_'+data_type];
            }
        } 
        
        return fieldvalues;
        
    },

});