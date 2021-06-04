/**
* manageDefDetailTypes.js - main widget for field types
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


$.widget( "heurist.manageDefDetailTypes", $.heurist.manageEntity, {

    _entityName:'defDetailTypes',
    fields_list_div: null,  //suggestion list
    set_detail_type_btn: null,

    fieldSelectorLast:null,
    fieldSelectorOrig: null,
    fieldSelector: null,
    
    //
    //
    //    
    _init: function() {

//this._time_debug = new Date().getTime() / 1000;
        this.options.default_palette_class = 'ui-heurist-design';
        
        //allow select existing fieldtype by typing
        //or add new field both to defDetailTypes and defRecStructure
        //this.options.newFieldForRtyID = 0; 
        
        this.options.innerTitle = false;
        
        this.options.layout_mode = 'short';
        this.options.use_cache = true;
        this.options.use_structure = false;
        //this.options.edit_mode = 'popup';
        
        //this.options.select_return_mode = 'recordset';
        this.options.edit_need_load_fullrecord = false;
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
            this.options.edit_mode = 'popup';    
            //this.options.edit_mode = 'none';
        }

        this._super();

        if(this.options.isFrontUI){
            
            this.searchForm.css({padding:'10px 5px 0 10px'});
            
            window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, {'background-color':'#fff', opacity:1});   
        
            //add fields group editor
            this.element.addClass('ui-suppress-border-and-shadow');
            
            this.element.find('.ent_wrapper:first').addClass('ui-dialog-heurist').css('left',228);
            
            this.fieldtype_groups = $('<div data-container="ABBBBBB">').addClass('ui-dialog-heurist')
                .css({position: 'absolute',top: 0, bottom: 0, left: 0, width:220, overflow: 'hidden'})
                .appendTo(this.element);
                
                
            if(this.options.select_mode=='manager'){ //adjust table widths
                var that = this;
                window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_WINDOW_RESIZE, 
                    function(){
                        if(that.recordList && that.recordList.resultList('instance')){
                            that.recordList.resultList('applyViewMode','list', true);
                            that.recordList.resultList('refreshPage');
                        }
                    });
            }    
                

        }        
    
    },
        
    _destroy: function() {

        window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_WINDOW_RESIZE);        
        
        if(this.fields_list_div){
            this.fields_list_div.remove();
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
            this._initEditorOnly();
            return;
        }
        
        this.searchForm.css({'min-width': '780px', padding:'6px', height:80});
        this.recordList.css({'min-width': '780px', top:80});
        this.searchForm.parent().css({'overflow-x':'auto'});

        var that = this;
        
        if(this.options.select_mode=='manager'){
            
            this.recordList.resultList({ 
                    show_toolbar: false,
                    pagesize: 99999,
                    list_mode_is_table: true,
                    rendererHeader:function(){ return that._recordListHeaderRenderer(); },
            
                    draggable: function(){
                        
                        that.recordList.find('.rt_draggable > .item').draggable({ // 
                                    revert: 'invalid',
                                    helper: function(){ 
                                        return $('<div class="rt_draggable ui-drag-drop" recid="'+
                                            $(this).parent().attr('recid')
                                        +'" style="width:300;padding:4px;text-align:center;font-size:0.8em;background:#EDF5FF"'
                                        +'>Drag and drop to group item to change base field group</div>'); 
                                    },
                                    zIndex:100,
                                    appendTo:'body',
                                    containment: 'window',
                                    scope: 'dtg_change'
                                    //delay: 200
                                });   
                    }
            });
            
            
            if(this.options.isFrontUI) {
                this._on( this.recordList, {        
                        "resultlistondblclick": function(event, selected_recs){
                                    this.selectedRecords(selected_recs); //assign
                                    
                                    if(window.hWin.HEURIST4.util.isRecordSet(selected_recs)){
                                        var recs = selected_recs.getOrder();
                                        if(recs && recs.length>0){
                                            var recID = recs[recs.length-1];
                                            this._onActionListener(event, {action:'edit',recID:recID}); 
                                        }
                                    }
                                }
                        });

            }

            this.coverMessage();			
        }else if(this.options.select_mode=='select_multi'){
            this.recordList.resultList('fullResultSet', $Db.dty());   
        }
        
//console.log( 'DT initControls  ' + (new Date().getTime() / 1000 - this._time_debug));
//this._time_debug = new Date().getTime() / 1000;
        
        this.options.onInitCompleted =  function(){
            
            if(that.options.isFrontUI){
                var rg_options = {
                     isdialog: false, 
                     isFrontUI: true,
                     container: that.fieldtype_groups,
                     title: 'Base field groups',
                     layout_mode: 'short',
                     select_mode: 'manager',
                     reference_dt_manger: that.element,
                     onSelect:function(res){
                         
//console.log( 'DT onSELECT!!!!  ' + (new Date().getTime() / 1000 - that._time_debug));
//that._time_debug = new Date().getTime() / 1000;
                         
                         if(window.hWin.HEURIST4.util.isRecordSet(res)){
                             
                            if(!that.getRecordSet()){
                                that._loadData( true );
                            }
                             
                            res = res.getIds();                     
                            if(res && res.length>0){
                                that.options.dtg_ID = res[0];
                                that.searchForm.searchDefDetailTypes('option','dtg_ID', res[0])
                            }
                         }
                     },
                     add_to_begin: true
                };
                                                                             
                window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypeGroups', rg_options);        
            }else{
                that._loadData(true);    
            }
        }
        
        // init search header
        this.searchForm.searchDefDetailTypes(this.options);
        
        
        if(this.options.use_cache){
            this._on( this.searchForm, {
                "searchdefdetailtypesonfilter": this.filterRecordList,
                "searchdefdetailtypesonadd": function() {
                        this._onActionListener(null, 'add'); //this.addEditRecord(-1);
                }
                });
                
        }else{
            window.hWin.HEURIST4.msg.sendCoverallToBack();
            
            this._on( this.searchForm, {
                    "searchdefdetailtypesonresult": this.updateRecordList,
                    "searchdefdetailtypesonadd": function() { this.addEditRecord(-1); }
                    });
            
        }
            
        
        
        return true;
    },            
    
    //
    // invoked after all elements are inited 
    //
    _loadData: function(is_first){
        
        var that = this;
      
        if(this.options.use_cache){
                this.updateRecordList(null, {recordset:$Db.dty()});
                if(is_first!==true)this.searchForm.searchDefDetailTypes('startSearch');
        }    
        
    },
    
    
    // 
    // get recordset from HEURIST4.detailtypes - NOT USED
    //
/*    
    _getRecordsetFromStructure: function(){
        
        var rdata = { 
            entityName:'defDetailTypes',
            total_count: 0,
            fields:[],
            records:{},
            order:[] };

        var detailtypes = window.hWin.HEURIST4.detailtypes;//get recordset from HEURIST4.detailtypes - NOT USED

        rdata.fields = window.hWin.HEURIST4.util.cloneJSON(detailtypes.typedefs.commonFieldNames);
        rdata.fields.unshift('dty_ID');


        for (var r_id in detailtypes.typedefs)
        {
            if(r_id>0){
                var dtype = window.hWin.HEURIST4.util.cloneJSON(detailtypes.typedefs[r_id].commonFields);
                dtype.unshift(r_id);
                rdata.records[r_id] = dtype;
                rdata.order.push( r_id );
            }
        }
        rdata.count = rdata.order.length;

        return new hRecordSet(rdata);
    },
*/    


    visible_fields: ['dtyid','ccode','edit','name','type','usedin','status','description'], //'usedin','show','ccode','group',       
    //----------------------
    //
    //
    //
    _recordListHeaderRenderer: function(){

        var max_width = this.recordList.find('.div-result-list-content').width() - 23;
        var used_width = 0;
        
        function fld2(col_width, value, style){
            
            if(!style) style = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                
                col_width = col_width - 1;
                used_width = used_width + col_width + 4;
                style += (';width:'+col_width+'px'); 
                
            }
            if(style!='') style = 'style=";'+style+'"'; //border-left:1px solid gray;
            
            if(!value){
                value = '';
            }
            return '<div class="item truncate" '+style+'>'+window.hWin.HEURIST4.util.htmlEscape(value)+'</div>';
        }
        
        var html = '';
        //if (!(this.usrPreferences && this.usrPreferences.fields)) return '';
        var fields = this.visible_fields; //this.usrPreferences.fields;
        
        var i = 0;
        for (;i<fields.length;i++){
           switch ( fields[i] ) {
                case 'dtyid': html += fld2(30,'ID','text-align:center'); break;
                case 'ccode': 
                    html += fld2(80,'ConceptID','text-align:center');     
                    break;
                case 'group': 
                    html += fld2(30,'Group','text-align:center');
                    break;
                case 'edit':  
                    html += fld2(30,'Edit','text-align:center');
                    break;
                case 'name':  
                    html += '$$NAME$$';  //html += fld2('150px','Name','text-align:left');
                    break;
                case 'type': 
                    html += fld2(80,'Type','text-align:center');
                    break;
                case 'description':  
                    html += '$$DESC$$'; //html += fld2(null,'Description',''); 
                    break;
                case 'show': 
                    html += fld2(30,'Show','text-align:center');
                    break;
                case 'usedin': 
                    html += fld2(30,'RecTypes','text-align:center');
                    break;
                case 'status': 
                    html += fld2(30,'Del','text-align:center');
                    break;
            }   
        }
        
        var w_desc = max_width-used_width-330;
        if(w_desc<30) w_desc = 30;
//console.log(max_width+'  '+'  '+used_width+'  '+w_desc);            
        html = html.replace('$$DESC$$',fld2(w_desc, 'Description', 'text-align:left'));

        var name_width = 330; //max_width - used_width;
//console.log('  =>'+name_width);        
        html = html.replace('$$NAME$$',fld2(name_width, 'Name', 'text-align:left'))
        
        return html;
    },
    
    //----------------------
    //
    //
    //
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width, value, style){
            
            if(!style) style = '';
            if(col_width>0){
                style += (';max-width:'+col_width+'px;min-width:'+col_width+'px');
            }

            if(style!='') style = 'style=";'+style+'"';  //padding:0px 4px
            
            if(!value){
                value = recordset.fld(record, fldname);
            }
            return '<div class="item truncate" '+style+'>'+window.hWin.HEURIST4.util.htmlEscape(value)+'</div>';
        }
        
        var recID   = fld('dty_ID');

        var html = '';
        
        var fields = this.visible_fields; //this.usrPreferences.fields;
        
        function __action_btn(action,icon,title){
            return '<div class="item" style="min-width:30px;max-width:30px;text-align:center;"><div title="'+title+'" '
                    +'class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" '
                    +'role="button" aria-disabled="false" data-key="'+action+'" style="height:18px;">'
                    +     '<span class="ui-button-icon-primary ui-icon '+icon+'"></span>'
                    + '</div></div>'            
        }
        
        
        var max_width = this.recordList.find('.div-result-list-content').width() 
                            - ((this.options.select_mode=='select_multi') ?40:33);
        var used_width = 330;//244;

        var w_desc = max_width - used_width - 330;
        if(w_desc<30) w_desc = 30;
        var name_width = 330; //max_width - used_width - w_desc;
        
        

        var grayed = '';
        var i = 0;
        for (;i<fields.length;i++){
            
            switch ( fields[i] ) {
                case 'dtyid': html += fld2('dty_ID',30,null,'text-align:right'); break;
                case 'ccode': 
                    html += ('<div class="item truncate" style="min-width:80px;max-width:80px;text-align:center">'
                            +$Db.getConceptID('dty',recID,true)+'</div>');
                    break;
                case 'group': 
                    html += __action_btn('group','ui-icon-carat-d','Change group');
                    break;
                case 'edit':  
                    html += __action_btn('edit','ui-icon-pencil','Click to edit base field');
                    break;
                case 'name':  html += fld2('dty_Name',name_width); break;
                case 'type':  
                
                    html += fld2('', 80, $Db.baseFieldType[recordset.fld(record,'dty_Type')], 'font-size:smaller'); 
                    break;
                case 'description':  
                    html += fld2('dty_HelpText',null,null,
                        'min-width:'+w_desc+'px;max-width:'+w_desc+'px;font-style:italic;font-size:smaller'); break;
                case 'show': 
                
                    if(recordset.fld(record, 'dty_ShowInLists')==1){
                        html += __action_btn('hide_in_list','ui-icon-check-on','Click to hide in dropdowwn lists and trees');    
                    }else{
                        html += __action_btn('show_in_list','ui-icon-check-off','Click to show in dropdown lists and trees');
                        grayed = 'background:lightgray';
                    }
                    break;
                case 'usedin': 
                    html += __action_btn('usedin','ui-icon-circle-b-info','Click for the record types in which this base field is used');
                    break;
                case 'status': 
                    
                    if(recordset.fld(record, 'dty_Status')=='reserved'){
                        html += __action_btn('','ui-icon-lock','Status: Reserved');
                    }else{
                        html += __action_btn('delete','ui-icon-delete','Status: Open. Click to delete this base field');
                    }    
                
                    break;
            }    
        }

        html = '<div class="recordDiv rt_draggable white-borderless" recid="'+recID+'" style="display:table-row;height:28px;'+grayed+'">'
                    + '<div class="recordSelector item"><input type="checkbox" /></div>'
                    + html+'</div>';

        return html;
        
    },
    
    //
    //
    //
    _onActionListener:function(event, action){
        
        
        if(action && action.action=='delete'){
            
            var usage = $Db.rst_usage(action.recID);
            if(usage && usage.length>0)
            {            

                var sList = '';
                for(var i=0; i<usage.length; i++){
                    sList += ('<a href="#" data-rty_ID="'+usage[i]+'">'+$Db.rty(usage[i],'rty_Name')+'</a><br>');
                }
                
                var sUsage = '<div><b>Warning</b><br/><br/><b>'+$Db.dty(action.recID,'dty_Name')
                        +'</b> is used in the following record types:<br/><br/>'
                        +sList
                        +'<br/><br/>'
                        +'You have to either delete the field from the record type, '
                        +'or delete the record type<br>(it may not be possible or desirable to delete the record type)</div>';

                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sUsage, null, {title:'Warning'}, 
                            {default_palette_class:this.options.default_palette_class});        
                
                this._on($dlg.find('a[data-rty_ID]'),{click:function(e){
                    //edit structure
                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                        {new_record_params:{RecTypeID: $(e.target).attr('data-rty_ID')}, edit_structure:true});
                    
                    return false;                    
                }});
                
                return;
            }
        }
        
        
        var isResolved = this._super(event, action);

        if(!isResolved){
            
            var recID = 0;
            var target = null;

            if(action && action.action){
                recID =  action.recID;
                target = action.target;
                action = action.action;
            }
            if(recID>0){
                
                var that = this;
 
                if(action=='group'){
                    
                }else if(action=='show_in_list' || action=='hide_in_list'){
                    
                    //window.hWin.HEURIST4.msg.bringCoverallToFront(this.recordList);
                    var newVal = (action=='show_in_list')?1:0;
                    this._saveEditAndClose({dty_ID:recID, dty_ShowInLists:newVal });
                    
                }else if(action=='usedin'){
                    
                    //show selectmenu with list of recordtypes where this field is used
                    if(Math.abs(this.fieldSelectorLast)!=recID){
                        
                        this.fieldSelectorLast   = recID;
                        var usage = $Db.rst_usage(recID);
                        if(usage && usage.length>0){
                            var options = [];
                            for(var i=0; i<usage.length; i++){
                                var rty_ID = usage[i];
                                options.push({key:rty_ID, title:$Db.rty(rty_ID, 'rty_Name')});
                            }   
                        }else{
                            this.fieldSelectorLast = -this.fieldSelectorLast;
                            window.hWin.HEURIST4.msg.showMsgFlash('Field is not in use',1000);
                            return;
                        }

                        if(!this.fieldSelector){
                            this.fieldSelectorOrig = document.createElement("select");    
                            window.hWin.HEURIST4.ui.fillSelector(this.fieldSelectorOrig, options);
                            this.fieldSelector = window.hWin.HEURIST4.ui.initHSelect(this.fieldSelectorOrig, false);
                            
                            var menu = this.fieldSelector.hSelect( "menuWidget" );
                            menu.css({'max-height':'350px'});                        
                                this.fieldSelector.hSelect({change: function(event, data){

                                    //edit structure     
                                    var new_record_params = {RecTypeID: data.item.value};
                                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                                        {new_record_params:new_record_params, edit_structure:true});
    
                            }});
                            
                        }else{
                            $(this.fieldSelectorOrig).empty();
                            window.hWin.HEURIST4.ui.fillSelector(this.fieldSelectorOrig, options);
                            this.fieldSelector.hSelect('refresh');
                        }
                    }
                    if(this.fieldSelectorLast>0){
                        this.fieldSelector.hSelect('open');
                        this.fieldSelector.val(-1);
                        this.fieldSelector.hSelect('menuWidget')
                            .position({my: "left top", at: "left+10 bottom-4", of: $(target)});
                        
                        this.fieldSelector.hSelect('hideOnMouseLeave', $(target));                    
                    }
                    
                }
                
            }
        }

    },
       
    //
    // can remove group with assigned fields
    //     
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this.deleted_from_group_ID = $Db.dty(this._currentEditID,'dty_DetailTypeGroupID');
            
            this._super(); 
        }else{
            this.deleted_from_group_ID = 0;
            
            var usage = $Db.rst_usage(this._currentEditID);
            if(usage && usage.length>0){ 
                window.hWin.HEURIST4.msg.showMsgFlash('Field in use in '+usage.length+' record types. Can\'t remove it');  
                return;                
            }
            
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this field type?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'},
                {default_palette_class:this.options.default_palette_class});        
        }
        
    },
    
    //
    //
    //
    _afterDeleteEvenHandler: function(recID){
        
            this._super();

            this.searchForm.searchDefDetailTypes('startSearch');
            this._triggerRefresh('dty');
            
            //this.updateGroupCount(this.deleted_from_group_ID, -1);
    },
    

    //-----
    //
    // Set group ID value for new field type
    // and perform some after load modifications (show/hide fields,tabs )
    //
    _afterInitEditForm: function(){

        this._super();
        
        if(this._toolbar){
            this._toolbar.find('#btnRecSave').button({label:window.hWin.HR(this._currentEditID>0?'Save':'Create New Field')});
        }
        var dty_DetailTypeGroupID = this.options.dtg_ID; //this.searchForm.find('#input_search_group').val();
        
        if(!(this._currentEditID>0)){ //insert       
            
            if(!(dty_DetailTypeGroupID>0)){ //take first from list of groups
                dty_DetailTypeGroupID = $Db.dtg().getOrder()[0];                
            }

            this._editing.setFieldValueByName('dty_DetailTypeGroupID', dty_DetailTypeGroupID);
            //var ele = this._editing.getFieldByName('dty_DetailTypeGroupID');
            //ele.editing_input('setValue', dty_DetailTypeGroupID, true);
        }else{
            
            var ele = this._editing.getFieldByName('dty_ID');
            ele.find('div.input-div').html(this._currentEditID+'&nbsp;&nbsp;<span style="font-weight:normal">Code: </span>'
                                    +$Db.getConceptID('dty',this._currentEditID, true));
        }

        var name_field = this._editing.getInputs('dty_Name');   // Base Field Name input
        var main_container = $(this._editing.getContainer()[0]).find('fieldset').get(0);

        if(window.hWin.HEURIST4.util.isArrayNotEmpty(name_field)){  

            if(this._currentEditID<=0){ // Check that a new field is being defined

                if(this.options.newFieldForRtyID > 0){ // Ensure that the new field is for a specific rectype
                    var flavour_text = $('<h2 style="margin-block:0;margin-bottom:0.2em">Choose existing base field(s)</h2>'
                        + '<div class="heurist-helper2" style="font-size:0.95em">Rather than defining every field from scratch, you can pick some frequently used pre-defined fields from the existing Base fields.<br/>'
                        + 'However, please read the following notes carefully.</div>'
                        + '<span id="btn-basefields-list" style="margin:1em 0 1em 3em"></span>'
                        + '<div class="heurist-helper2" style="font-size:0.95em">The base fields chosen should have a <span style="text-decoration:underline">similar sense of meaning</span>, '
                        + 'e.g. use <em>Start date</em> for <em>Birth date</em>, <em>Creator</em> for <em>Author</em>, <em>Short description</em><br/>'
                        + 'for <em>Abstract</em>, <em>Extended description</em> for <em>Notes</em>. You can rename the fields to what you actually want once selected - the new name applies<br/>'
                        + 'to the current record type only (the base field retains its name).<br/><br/>'

                        + '<span style="text-decoration:underline">Do not completely redefine a base field</span> for a different purpose than it appears to be intended for, for instance redefining Family name as<br/>'
                        + 'Street, Length as Count, or Format as Condition. Significant change to the meaning of a field may later lead to confusion.<br/>'
                        + 'Fields which use the same base field will reference the same vocabulary (for term-list dropdowns and relationship type) or the same target<br/>'
                        + 'record types (for record pointers and relationships) - you cannot change the vocabulary or target record types for one without changing it<br/>'
                        + 'for all the others.</div><hr style="width:80%;margin:1em 10em 1em 0;"/>'

                        + '<h2 style="margin-block:0;margin-bottom:0.2em">Create a new field</h2>'
                        + '<div class="heurist-helper2" style="font-size:0.95em">If you can\'t find a suitable base field, type a new name. This will create a new base field and use it to create a new field in this record type.<br/>'
                        + 'It is a good idea to use a rather generic name and description so you can re-use the base field in other record types<br/>'
                        + 'and then customise the field appropriately for this record type.</div><br/>').prependTo(main_container);

                    var btnBasefieldsList = $(main_container).find('span#btn-basefields-list').button({label: 'Choose base fields'})
                    var rty_ID = this.options.newFieldForRtyID;
                    var that = this;

                    function multiFieldPopup(){ // load multi-field popup

                        that._editing.setModified(0);

                        var sURL = window.hWin.HAPI4.baseURL + "hclient/widgets/entity/popups/selectMultiFields.html?&rtyID="+rty_ID;
                                
                        window.hWin.HEURIST4.msg.showDialog(sURL, {
                            "close-on-blur": false,
                            title: 'Insert base fields',
                            window: window.hWin,
                            height: 700,
                            width: 1200,
                            default_palette_class: 'ui-heurist-design',
                            callback: function(context) {
                                if(!window.hWin.HEURIST4.util.isempty(context)) {

                                    var rst_fields = {
                                        rst_RequirementType: that._editing.getValue('rst_RequirementType')[0], 
                                        rst_MaxValues: that._editing.getValue('rst_MaxValues')[0], 
                                        rst_DisplayWidth: that._editing.getValue('rst_DisplayWidth')[0] 
                                    };

                                    that._trigger("multiselect", null, {selection:context.reverse(), rst_fields:rst_fields}); // handler in manageDefRecStructure

                                    that.closeDialog( true ); // close base field definition popup
                                }
                            }
                        });
                    }

                    this._on(btnBasefieldsList, 
                        {'click': function(){ // warn the user about the loss of popup data
                            var isChanged = (this._editing.getFieldByName('dty_Type').find('.ui-selectmenu-text').text() != 'Select...'
                                                || !window.hWin.HEURIST4.util.isempty($(this._editing.getInputs('dty_HelpText')[0]).val())
                                                || !window.hWin.HEURIST4.util.isempty($(this._editing.getInputs('dty_Name')[0]).val())
                                            ); // Check if values have been placed/selected in required fields

                            if(isChanged){

                                var $dlg, buttons = {};
                                buttons['Yes'] = function(){ // close message, open multi-field popup
                                    $dlg.dialog('close'); 
                                    multiFieldPopup();
                                }; 
                                buttons['No'] = function(){
                                    $dlg.dialog('close'); 
                                };

                                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                                    'Items entered into this form may be lost upon completing this process, would you still like to proceed?',
                                    buttons,
                                    {title:'Confirm',yes:'Yes',no:'No'}
                                );

                            }else{ // in the odd chance that isChanged is false, I don't think it can be in this case
                                multiFieldPopup();
                            }
                        }
                    });
                }
            }
        }

        //fill init values of virtual fields
        //add lister for dty_Type field to show hide these fields
        var elements = this._editing.getInputs('dty_Type');
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(elements)){
            
                if(this._currentEditID>0)
                {
                    //limit list and disable in case one option
                    var el = $(elements[0])[0];
                    var _dty_Type = $(el).val();
                    
                    $(el).empty();
                    el.disabled = false;
                    
                    window.hWin.HEURIST4.ui.addoption(el, _dty_Type,  $Db.baseFieldType[_dty_Type]);

                    if(_dty_Type=='float' || _dty_Type=='date'){
                        window.hWin.HEURIST4.ui.addoption(el, 'freetext',  $Db.baseFieldType['freetext']);
                    }else if(_dty_Type=='freetext'){
                        window.hWin.HEURIST4.ui.addoption(el, 'blocktext',  $Db.baseFieldType['blocktext']);
                    }else if(_dty_Type=='blocktext'){
                        window.hWin.HEURIST4.ui.addoption(el, 'freetext',  $Db.baseFieldType['freetext']);
                    }else{
                        el.disabled = true;
                    }
                    
                    if($(el).hSelect("instance")!=undefined){
                        $(el).hSelect("refresh"); 
                    }

                    this._on( $(elements[0]), {    
                        'change': function(event){
                               var dt_type = $(event.target).val();
                               this._onDataTypeChange(dt_type);
                               
                               /*
                               var virtual_fields = this._editing.getFieldByValue("dty_Role","virtual");
                               for(var idx in virtual_fields){
                                   $(virtual_fields[idx]).hide();
                               }
                               var ele = this._editing.getFieldByName('dty_PtrTargetRectypeIDs');
                               
                               if(dt_type!=='resource'){
                                   ele.hide();
                                   ele = this._editing.getFieldByName('dty_Mode_'+dt_type);
                                   
                                   if(dt_type=='enum'){
                                        this._activateEnumControls(ele);
                                   }
                               }
                               if(ele && ele.length>0) ele.show();
                               */
                        }
                    });
                }else {  // setup record type button and handler for selector
                    
                    var that = this;

                    var ele = this._editing.getFieldByName('dty_Type');  
                    ele = ele.find('.input-div');

                    this._on(ele.find('select'), {
                        'change':function(event){
                            var new_dty = $(event.target).val();

                            if(!window.hWin.HEURIST4.util.isempty(new_dty)){
                                that._onDataTypeChange(new_dty);
                            }
                        }
                    });
                    
                    this.set_detail_type_btn = $('<button>')
                        .button({icon:'ui-icon-circle-b-help'})
                        .css({'min-width':'25px','margin-left':'10px','padding-left':'5px','padding-right':'5px','background':'#523365','color':'white'});
                    this.set_detail_type_btn.appendTo(ele);

                    $('<span style="margin-left:5px;font-style:italic">guided choice</span>').appendTo(ele);
                    
                    ele.find('.btn_input_clear').appendTo(ele);
                    
                    this._on( this.set_detail_type_btn, {    
                        'click': function(event){

                            var dt_type = this._editing.getValue('dty_Type')[0];
                    
                            var $dlg, buttons = [
                                {text:window.hWin.HR('Cancel'),
                                    //id:'btnRecCancel',
                                    css:{'float':'right',margin:'.5em .4em .5em 0px'},  
                                    click: function() { $dlg.dialog( "close" ); }},
                                {text:window.hWin.HR('Use this field type'),
                                    css:{'float':'right',margin:'.5em .4em .5em 0px'},  
                                    class: 'ui-button-action',
                                    click: function() { 
                                        
                                        var dt_type_new = $dlg.find('input[name="ft_type"]:checked').val();
                                        
                                        if(!window.hWin.HEURIST4.util.isempty(dt_type_new)) {

                                            var changeToNewType = true;
                                            if(((dt_type==="resource") || (dt_type==="relmarker") || 
                                                (dt_type==="enum"))  && dt_type!==dt_type_new)
                                            {

                                                window.hWin.HEURIST4.msg.showMsgDlg("If you change the type to '"
                                                    + $Db.baseFieldType[dt_type_new] 
                                                    + "' you will lose all your settings for type '"   //vocabulary 
                                                    + $Db.baseFieldType[dt_type]+
                                                    "'.\n\nAre you sure?",                                            
                                                    function(){   
                                                        that._onDataTypeChange(dt_type_new);                                                   
                                                    }, {title:'Change type for field',yes:'Continue',no:'Cancel'},
                                                    {default_palette_class:that.options.default_palette_class});                                                
                                            }else{
                                                that._onDataTypeChange(dt_type_new);                                                   
                                            }                            
                                        }
                            
                                        $dlg.dialog( "close" ); 
                                    }}
                            ];                
                            
                            
                            $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
                                +"hclient/widgets/entity/popups/selectFieldType.html?t="+(new Date().getTime()), 
                                buttons, 'Select data type of field', 
                                {  container:'detailtypes-type-popup',
                                    width:800,
                                    height:620,
                                    default_palette_class: this.options.default_palette_class,
                                    close: function(){
                                        $dlg.dialog('destroy');       
                                        $dlg.remove();
                                    },
                                    open: function(){
                                        $dlg.css({padding:0});
                                        
                                        window.hWin.HEURIST4.ui.initHelper( {button:$dlg.find('#hint_more_info1'), 
                                                        title:'Field data type: Record pointer', 
                                                        url:window.hWin.HAPI4.baseURL+'context_help/field_data_types.html #resource',
                                                        position:{ my: "left top", at: "left top", of:$dlg}, no_init:true} ); 
                                        window.hWin.HEURIST4.ui.initHelper( {button:$dlg.find('#hint_more_info2'), 
                                                        title:'Field data type: Relationship marker', 
                                                        url:window.hWin.HAPI4.baseURL+'context_help/field_data_types.html #relmarker',
                                                        position:{ my: "left top", at: "left top", of:$dlg}, no_init:true} ); 
                                        
                                    }  //end open event
                            });
                            

                    }});
                    
                }

            $(elements[0]).change(); //trigger
        }

        elements = this._editing.getInputs('dty_Name');
        this._on( $(elements[0]), {
                keypress: window.hWin.HEURIST4.ui.preventChars} );
        
        if(this.options.newFieldForRtyID>0){

            //disable all fields except field name
            this._on( $(elements[0]), {
                keyup: this._onFieldAddSuggestion });

            var depended_fields = this._editing.getFieldByClass('newFieldForRtyID');
            for(var idx in depended_fields){
                $(depended_fields[idx]).show();
            }

            //add extra click functionalities to save buttons
            this._toolbar.find('#btnSaveExt').show().click(function(){
                window.hWin.HAPI4.save_pref('edit_rts_open_formlet_after_add', 1);
            }); 

            // this is used to determine whether the user will customise the new field after creation
            window.hWin.HAPI4.save_pref('edit_rts_open_formlet_after_add', 0);
        }

        this.getUiPreferences();
        var ishelp_on = (this.usrPreferences['help_on']==true || this.usrPreferences['help_on']=='true');
        ele = $('<div style="position:absolute;right:6px;top:4px;"><label><input type="checkbox" '
                        +(ishelp_on?'checked':'')+'/>explanations</label></div>').prependTo(this.editForm);
        this._on( ele.find('input'), {change: function( event){
            var ishelp_on = $(event.target).is(':checked');
            this.usrPreferences['help_on'] = ishelp_on;
            window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper1');
            window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper2');
        }});
        
        window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper1');
        window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper2');
        
        this._adjustEditDialogHeight();
    },    
    
    //
    //
    //
    _onDataTypeChange: function(dt_type)
    {
           /*
           var ele = this._editing.getFieldByName('dty_Type');
           ele.editing_input('setValue', dt_type);
           */
           if(this.set_detail_type_btn){
               var elements = this._editing.getInputs('dty_Type');
               $(elements[0]).val( dt_type );
               if($(elements[0]).hSelect("instance")!=undefined){
                   $(elements[0]).hSelect("refresh"); 
               }
               
               var ele = this._editing.getFieldByName('dty_Type');  
               ele.editing_input('showErrorMsg',null);
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
           if(dt_type=='enum' || dt_type=='relmarker'){
                var ele = this._editing.getFieldByName('dty_Mode_enum');  
                this._activateEnumControls(ele);
           }else if(dt_type=='relationtype'){
                var ele = this._editing.getFieldByName('dty_Mode_enum');  
                this._activateRelationTypeControls(ele);
           }
           if(this.options.newFieldForRtyID>0){
                depended_fields = this._editing.getFieldByClass('newFieldForRtyID');
               for(var idx in depended_fields){
                   $(depended_fields[idx]).show();
               }
               var ele = this._editing.getFieldByName('rst_DisplayWidth');
               if(dt_type=='freetext' || dt_type=='blocktext' || dt_type=='float'){
                   ele.editing_input('setValue', dt_type=='float'?10:(dt_type=='freetext'?60:100));
                   ele.show();  
               }else{
                   ele.hide();  
               }
           }
    },
    
    //
    // show dropdown for field suggestions to be added
    //
    _onFieldAddSuggestion: function(event){
        
        var input_name = $(event.target);
        
        //!!!! removeErrorClass(input_name);
        
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
        //this._editing.setDisabled( setdis )
        //var ele = this._editing.getFieldByName('dty_Name');
        //ele.editing_input('setDisabled', false);
        //window.hWin.HEURIST4.util.setDisabled($('.initially-dis'), setdis );
      
        //find base field to suggest
        if(input_name.val().length>2){
           
            var rty_ID = this.options.newFieldForRtyID; 
            var dty_ID, field_name, field_type;
                
            this.fields_list_div.empty();  
            var is_added = false;
            
            var that = this;
            //add current value as first
            var first_ele = $('<div class="truncate"><b>'+input_name.val()+' [new]</b></div>').appendTo(this.fields_list_div)
                            .click( function(event){
                                window.hWin.HEURIST4.util.stopEvent(event);
                                that.fields_list_div.hide(); 
                                //!!!! $('#ed_dty_HelpText').focus();
                            });

                            
            //list of fields that are already in record type
            var aUsage = $Db.rst(rty_ID); //return rectype
            if(!window.hWin.HEURIST4.util.isRecordSet(aUsage)){
                aUsage = null;
            }
            
            var entered = input_name.val().toLowerCase();
                            
            //find among fields that are not in current record type
            $Db.dty().each(function(dty_ID, rec){

                field_name = $Db.dty(dty_ID, 'dty_Name');
                field_type  = $Db.dty(dty_ID, 'dty_Type');

                if( $Db.dty(dty_ID, 'dty_ShowInLists')!='0'
                    && field_type!='separator'
                    && (!aUsage || !aUsage.getById(dty_ID))
                    && (field_name.toLowerCase().indexOf( entered )>=0) )
                {

                    var ele;
                    if(field_name.toLowerCase()==entered.toLowerCase()){
                        ele = first_ele;
                    }else{
                        ele = $('<div class="truncate">').appendTo(that.fields_list_div);
                    }

                    is_added = true;
                    ele.attr('dty_ID',dty_ID)
                    .text(field_name+' ['+ $Db.baseFieldType[field_type] +']')
                    .click( function(event){
                        window.hWin.HEURIST4.util.stopEvent(event);

                        var ele = $(event.target).hide();
                        var _dty_ID = ele.attr('dty_ID');


                        if(_dty_ID>0){
                            that.fields_list_div.hide();
                            input_name.val('').focus();

                            window.hWin.HEURIST4.msg.showMsgFlash('Field added to record structure');

                            //that.selectedRecords( [_dty_ID] );
                            //that._selectAndClose();
                            var rst_fields = {rst_RequirementType: that._editing.getValue('rst_RequirementType')[0], 
                                rst_MaxValues: that._editing.getValue('rst_MaxValues')[0], 
                                rst_DisplayWidth: that._editing.getValue('rst_DisplayWidth')[0] };

                            that._trigger( "onselect", null, {selection: [_dty_ID], rst_fields:rst_fields });
                            that.closeDialog( true ); //force without warning

                        }
                    });

                }                
            });


            if(is_added){
                this.fields_list_div.show();    
                this.fields_list_div.position({my:'left top', at:'left bottom', of:input_name})
                    //.css({'max-width':(maxw+'px')});
                    .css({'max-width':input_name.width()+60});
            }else{
                this.fields_list_div.hide();
            }

      }else{
            this.fields_list_div.hide();  
      }

    },

    //
    //
    //
    _activateRelationTypeControls: function( ele ){

            ele.find('.header').text('Relation types:');
            ele.find('.heurist-helper1').text('Relationship Type can be any term within any vocabulary '
            +'marked as a Relationships vocabulary. You can - and should - use Relationship Marker fields '
            +'to constrain the creation of relationships between particular record types');
        
            var ele = ele.find('.input-div');
            //remove old content
            ele.empty();
            
            if(ele.find('#enumVocabulary').length>0) return; //already inited
    
            this.enum_container = ele;
            
            $('<div style="line-height:2ex;padding-top:4px">'
                        +'<div id="enumVocabulary" style="display:inline-block;padding-bottom:10px">' //padding-left:4px;
                            +'<select id="selPreview"></select>'
                            +'<span style="padding:5px 3px">'
                                +'<a href="#" id="show_terms_1" style="padding-left:10px">edit terms tree</a>'
                            +'</span>'
                        +'</div>'
                +'</div>').appendTo(this.enum_container);
                
            
            this._on(this.enum_container.find('#show_terms_1'),{click: this._showOtherTerms}); //manage defTerms
            this._recreateTermsPreviewSelector();
            
    },
    
    //
    //
    //
    _activateEnumControls: function( ele, full_mode ){
        
            var ele = ele.find('.input-div');
            //remove old content
            ele.empty();
            
            
            if(ele.find('#enumVocabulary').length>0) return; //already inited
            
            this.enum_container = ele;
            
            if(true ||  !(this._currentEditID>0) && (full_mode!==true) ){ //insert
            
                $('<div style="line-height:2ex;padding-top:4px">'
                        +'<div id="enumVocabulary" style="display:inline-block;">' //padding-left:4px;
                            +'<select id="selVocab" class="sel_width"></select>'
                            +'<a href="#" id="add_vocabulary_2" style="padding-left:10px">'
                                +'<span class="ui-icon ui-icon-plus"/>add vocabulary</a>'
                            +'<a href="#" id="show_terms_1" style="padding-left:10px">'
                                +'<span class="ui-icon ui-icon-pencil"/>vocabularies editor</a>'
                            +'<br><span id="termsPreview1" style="display:none;padding:10px 0px">'
                                +'<label style="width:60px;min-width:60px">Preview:&nbsp;</label><select id="selPreview"></select>'
                            +'</span>'
                        +'</div>'
                +'</div>').appendTo(this.enum_container);
/*            
                            +'<div style="padding:5px 3px">'
                                //+'<a href="#" id="add_advanced">advanced</a>&nbsp;'  style="margin-left:90px;"
                                +'<a href="#" id="add_vocabulary">add a vocabulary</a>&nbsp;'
                                +'<a href="#" id="add_terms" style="padding-left:10px">add terms to vocabulary</a>&nbsp;'
                                +'<a href="#" id="show_terms_1" style="padding-left:10px">edit terms tree</a>'
                            +'</div>'
*/            
            
            }else{     //@todo remove   Individidual selection - not used anymore
            
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
                
            }
                
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
            this._on(this.enum_container.find('#show_terms_1'),{click: this._showOtherTerms}); //manage defTerms
            this._on(this.enum_container.find('#add_vocabulary_2'),{click: this._onAddVocabulary}); //add vocab via defTerms
            this._on(this.enum_container.find('#add_terms'),{click: this._onAddVocabOrTerms});
           
            
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
        
        
        //add new term to specified vocabulary
        var rg_options = {
                 isdialog: true, 
                 select_mode: 'manager',
                 edit_mode: 'editonly',
                 height: 240,
                 rec_ID: -1,
                 onClose: function( context ){
                    if(context>0){
                        that._editing.setFieldValueByName('dty_JsonTermIDTree', context);
                    }
                    that._recreateTermsVocabSelector();                      
                    
                 }
            };
            
            if(is_add_vocab){
                  rg_options['title'] = 'Add new vocabulary';
                  rg_options['auxilary'] = 'vocabulary';
                  rg_options['suggested_name'] = dt_name+' vocab';
            }else if(vocab_id>0){
                  //rg_options['title'] = 'Add term to vocabulary "'+$Db.trm(vocab_id,'trm_Label')+'"';
                  rg_options['trm_VocabularyID'] = vocab_id;
            }else{
                  window.hWin.HEURIST4.msg.showMsgFlash('Select of add vocabulary first');          
            }
        
            
            
        window.hWin.HEURIST4.ui.showEntityDialog('defTerms', rg_options); // it recreates  
        
        
    },

    _onAddVocabulary: function(){
        this._showOtherTerms('add_new');
    },    
    
    //
    // Opens defTerms manager
    //
    _showOtherTerms: function(event){
        

        var term_type = this._editing.getValue('dty_Type')[0];

        var vocab_id;
        if(event=='add_new'){
            vocab_id = 'add_new';
        }else {
            vocab_id = (term_type=='relationtype')
                            ?this.enum_container.find("#selPreview").val()
                            :this.enum_container.find("#selVocab").val();
            vocab_id = $Db.getTermVocab(vocab_id); //get vocabulary for selected term
            
            if(term_type=='relmarker' && !(vocab_id>0)){
                //get first vocab in relationship group
                var vocab_ids = $Db.trm_getVocabs('relation');
                vocab_id = vocab_ids[0];
    /*            
                $Db.vcg().each2(function(id,rec){
                    if(rec['vcg_Domain']=='relation'){
                        
                    }
                });
    */            
            }
            
        }

        var that = this;
        window.hWin.HEURIST4.ui.showEntityDialog('defTerms', 
            {isdialog: true, 
                //auxilary: vocab_id>0?'term':'vocabulary',
                selection_on_init: vocab_id,  //selected vocabulary  
                innerTitle: false,
                innerCommonHeader: $('<div><span style="margin-left:260px">Field: <b>'
                    +(this._currentEditID>0?$Db.dty(this._currentEditID,'dty_Name'):'New field')
                    +'</b></span> '
                    + (vocab_id>0?('<span style="margin-left:110px">This field uses vocabulary: <b>'+$Db.trm(vocab_id,'trm_Label')+'</b></span>')
                        :'. Addition of new vocabulary')
                    +'</div>'),

                width: 1200, height:700,
                onClose: function( context ){
                    if(context>0 && vocab_id=='add_new'){ //change vocabulary for new addition only 
                        that._editing.setFieldValueByName('dty_JsonTermIDTree', context);
                    }
                    if(term_type=='relationtype'){
                        that._recreateTermsPreviewSelector(); 
                    }else{
                        that._recreateTermsVocabSelector();    
                    }

                }
        });

    },    
    
    //
    //
    //
    _recreateTermsVocabSelector: function(newval){
        
        //selected vocabulary
        var allTerms = newval>0?newval:this._editing.getValue('dty_JsonTermIDTree')[0];
        
        var term_type = this._editing.getValue('dty_Type')[0];
        
        if(term_type!='enum'){
            term_type='relation';
        }
        
        var defaultTermID = null;
        var is_vocabulary = window.hWin.HEURIST4.util.isempty(allTerms) ||
                            window.hWin.HEURIST4.util.isNumber(allTerms);
        if(is_vocabulary){
            defaultTermID = allTerms; //vocabulary
            this.enum_container.find('input[name="enumType"][value="vocabulary"]').prop('checked',true).trigger('change');
        }else{
            this.enum_container.find('input[name="enumType"][value="individual"]').prop('checked',true).trigger('change');
        }
        
        var orig_selector = this.enum_container.find("#selVocab");
        
        var opts = {topOptions:'select...', defaultTermID:is_vocabulary?defaultTermID:0};
        
        if(term_type=='relation'){
            opts.domain = term_type;
        }
        
        var selnew = window.hWin.HEURIST4.ui.createVocabularySelect(orig_selector[0], opts); 

        this._off(orig_selector, 'change');
        this._on(orig_selector, {change: function(event){
            this._editing.setFieldValueByName('dty_JsonTermIDTree', $(event.target).val());
            this._editing.setFieldValueByName('dty_TermIDTreeNonSelectableIDs', '');
            this._recreateTermsPreviewSelector();
        }});
    

        
        
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
        
        //preview_sel.empty();
        //this.enum_container.find('#termsPreview2').empty();
        
        var term_type = this._editing.getValue('dty_Type')[0];
        if(term_type=='relationtype'){
            allTerms = 'relation';
        }

        if(!window.hWin.HEURIST4.util.isempty(allTerms)) {
            
            //var disTerms = this._editing.getValue('dty_TermIDTreeNonSelectableIDs')[0];  //@todo remove - is not used anymore

            //append to first preview
            var new_selector = this.enum_container.find('#selPreview');
            
            new_selector = window.hWin.HEURIST4.ui.createTermSelect(new_selector[0],
                    {vocab_id:allTerms, topOptions:false, supressTermCode:true});

            preview_sel.css({'display':'inline-block'});
            
        }else{
            preview_sel.hide();
        }
        
    },

    //--------------------------------------------------------------------------
    
    
    //
    // update list after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){
        
        // close on addition of new record in select_single mode    
        if(this._currentEditID<0 && 
            (this.options.selectOnSave===true || this.options.select_mode=='select_single'))
        {
                $Db.dty().addRecord(recID, fieldvalues); 
                this._selection = new hRecordSet();
                this._selection.addRecord(recID, fieldvalues);
                this._selectAndClose();
                return;    
                    
        }
        
        $Db.dty().setRecord(recID, fieldvalues); 

        //update local definitions
        this._super( recID, fieldvalues );

        this._triggerRefresh('dty');    
        
        if(this.options.edit_mode == 'editonly'){
            this.closeDialog(true); //force to avoid warning
        }else if(this.it_was_insert){
            this.searchForm.searchDefDetailTypes('startSearch');
            //this._triggerRefresh('dty');
            
            //var dtg_ID = $Db.dty(recID,'dty_DetailTypeGroupID');
            //this.updateGroupCount(dtg_ID, 1);
        }else{
            //this._triggerRefresh('dty');    
        }        
        
        
    },

    //
    // NOT USED ANYMORE
    //
    updateGroupCount:function(dtg_ID,  delta){
        
        if(dtg_ID>0){
            var cnt = parseInt($Db.dtg(dtg_ID,'dtg_FieldCount'));
            var cnt = (isNaN(cnt)?0:cnt)+delta;
            if(cnt<0) cnt = 0;
            $Db.dtg(dtg_ID,'dtg_FieldCount',cnt);
            this._triggerRefresh('dtg');
        }
    },    
    
    
    //-----------------------------------------------------
    //
    // send update request and close popup if edit is in dialog
    // afteraction is used in overriden version of this method
    //
    _saveEditAndClose: function( fields, afterAction, onErrorAction ){
        
        var that_widget = this;
        
        if(!fields){
            fields = this._getValidatedValues();         
            
            if(fields!=null){
                var dt_type = fields['dty_Type'];
                if(window.hWin.HEURIST4.util.isempty(dt_type)){ //actually it is already checked in _getValidatedValues
                    window.hWin.HEURIST4.msg.showMsgDlg('Field "Data type" is requirted');
                    fields = null;
                }else
                
                //last check for constrained pointer
                if(window.hWin.HEURIST4.util.isempty(fields['dty_PtrTargetRectypeIDs'])) 
                {
                    if(dt_type=='resource')
                    {    
                        window.hWin.HEURIST4.msg.showPrompt(
    'Please select target record type(s) for this entity pointer field before clicking the Create Field button.'
    +'<br><br>We strongly recommend NOT creating an unconstrained entity pointer unless you have a very special reason for doing so, as all the clever stuff that Heurist does with wizards for building facet searches, rules, visualisation etc. depend on knowing what types of entities are linked. It is also good practice to plan your connections carefully. If you really wish to create an unconstrained entity pointer - not recommended - check this box <input id="dlg-prompt-value" class="text ui-corner-all" '
                    + ' type="checkbox" value="1"/>', 
                        function(value){
                            if(value==1){
                                that_widget._saveEditAndClose( fields, afterAction );
                            }
                        }, {title:'Target record type(s) should be set',yes:'Continue',no:'Cancel'});
                        return;
                    }else if(dt_type=='relmarker'){
                        window.hWin.HEURIST4.msg.showMsgDlg(
                            'Please select target record type. Unconstrained relationship is not allowed',null, 'Warning',
                            {default_palette_class:this.options.default_palette_class});
                        return;
                    }    
                    
                }else if(!(dt_type=='resource' || dt_type=='relmarker')){
                        fields['dty_PtrTargetRectypeIDs'] = '';
                }
            }
        }
        if(fields==null) return; //validation failed
        
        
        if(this._currentEditID>0 
            && !fields['pwd_ReservedChanges']
            && $Db.dty(this._currentEditID,'dty_Status')=='reserved')
        {
        
            if(window.hWin.HAPI4.sysinfo['pwd_ReservedChanges']){ //password defined
            
                window.hWin.HEURIST4.msg.showPrompt('<p>Reserved field changes '
                +'require a special system administrator password (not a normal login password)</p>Enter password: ',
                    function(password_entered){
                        
                        window.hWin.HAPI4.SystemMgr.action_password({action:'ReservedChanges', password:password_entered},
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK && response.data=='ok'){
                                    //that_widget._super( fields, afterAction );
                                    fields['pwd_ReservedChanges'] = password_entered;
                                    that_widget._saveEditAndClose( fields, afterAction );
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgFlash('Wrong password');
                                }
                            }
                        );
                        
                    },
                'Sysadmin override password required', {password:true});
            }else{
                window.hWin.HEURIST4.msg.showMsgDlg('Reserved field changes are not allowed' 
                + 'unless a special system administrator password is set - please consult system administrator.');
            }
            return;
        }
        fields['pwd_ReservedChanges'] = null;
        
        this._super( fields, afterAction, this._onSaveError );
        
    },
    
    _onSaveError: function(response){
      
            if(response.sysmsg && response.sysmsg.reccount){
     
                var res = response.sysmsg;    
                
                var sMsg = response.message;
                sMsg += '<p><a href="'+window.hWin.HAPI4.baseURL+'?db='
                            + window.hWin.HAPI4.database+'&q=ids:' + res['records'].join(',')
                            + '" target="_blank">'
                    +'List of '+res.reccount+' records which use this vocabulary</a></p>';
                
                window.hWin.HEURIST4.msg.showMsgDlg(sMsg, null, {title:'Vocabulary in use'},
                    {default_palette_class:this.options.default_palette_class});        

                
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);    
            }
    },
    
    
    
    //  -----------------------------------------------------
    //
    // perform validation
    //
    _getValidatedValues: function(){
        
        //fieldvalues - is object {'xyz_Field':'value','xyz_Field2':['val1','val2','val3]}
        var fieldvalues = this._super();
        
        if(fieldvalues!=null){
            
            var dt_type = fieldvalues['dty_Type'];
            if(dt_type=='enum' || dt_type=='relmarker'){

                if(!fieldvalues['dty_JsonTermIDTree']){

                    if(dt_type=='enum'){    
                        window.hWin.HEURIST4.msg.showMsgErr(
                            'Please select or add a vocabulary. Vocabularies must contain at least one term.', 'Warning');
                    }else{
                        window.hWin.HEURIST4.msg.showMsgDlg(
                            'Please select or add relationship types',null, 'Warning',
                            {default_palette_class:this.options.default_palette_class});
                    }
                    return null;                    
                }
            }else{
                fieldvalues['dty_JsonTermIDTree'] = '';
                fieldvalues['dty_TermIDTreeNonSelectableIDs'] = '';
            }
        }           
        return fieldvalues;
        
    },
    
    
    //
    // event handler for select-and-close (select_multi)
    // or for any selection event for select_single
    // triger onselect event
    //
    _selectAndClose: function(){
        
        if(this.options.newFieldForRtyID>0){
            var rst_fields = {rst_RequirementType: this._editing.getValue('rst_RequirementType')[0], 
                              rst_MaxValues: this._editing.getValue('rst_MaxValues')[0], 
                              rst_DisplayWidth: this._editing.getValue('rst_DisplayWidth')[0] };
                              
            this._resultOnSelection = { rst_fields:rst_fields };
        }
        
        this._super();
    },
   
    //
    //
    //                                
    changeDetailtypeGroup: function(params){                                    

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.recordList);

        var that = this;
        this._saveEditAndClose( params ,
            function(){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                that.searchForm.searchDefDetailTypes('startSearch');
                that._triggerRefresh('dty');
                /*
                window.hWin.HAPI4.EntityMgr.refreshEntityData('dtg',
                    function(){
                        that._triggerRefresh('dtg');
                    }
                )*/
        });
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
    // show warning
    //
    addEditRecord: function(recID, is_proceed){

        if(recID<0 && is_proceed !== true){
            var that = this;

            this.coverMessage(recID);
        }else{
            this._super(recID, is_proceed); 
        }
    },

    //
    // cover message warning a standard user to not define base fields at this location
    //
    coverMessage: function(recID){
        var that = this;

        if(this.element.find('#base-field-warning').length == 0){ // Check if message already exists
            this.element.append('<div id="base-field-warning" style="position:relative;background:rgb(0,0,0,0.6);z-index:60000;height:100%;">'
                + '<div style="background:lightgrey;border:2px solid black;color:black;position:absolute;top:50%;left:50%;transform:translate(-50%, -50%);height:200px;width:510px;font-size:1.2em;padding:20px;">'
                + 'The base fields editing function is provided for completeness and for<br/>advanced data management. Most users will not need to use it.<br/><br/>'
                + '<strong>We strongly recommend NOT using this function to create new<br/>base fields. It is much more intuitive to create them <em>in situ</em> while<br/>designing your record structure.</strong><br/></br>'
                + 'Recommended: Design > <span style="text-decoration:underline;cursor:pointer" onclick="$(\'li[data-action=menu-structure-rectypes]\').click();">Record Types</span><br/><br/>'
                + 'Click outside this box for access to base fields manager'
                + '</div></div>'); // Add message

            $('#base-field-warning').on('click', function(e){
                if(e.target !== this){ return; }

                $('#base-field-warning').hide();
            });
        }
        else{ // Show message
            this.element.find('#base-field-warning').show();

            $('#base-field-warning').on('click', function(e){
                if(e.target !== this){ return; }

                var id = (recID)?recID : -1;

                that.addEditRecord(id, true);
            });
        }
    }

});