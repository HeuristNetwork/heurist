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
    fields_list_div: null,
    set_detail_type_btn: null,
    
    //
    //
    //    
    _init: function() {

        //allow select existing fieldtype by typing
        //or add new field both to defDetailTypes and defRecStructure
        //this.options.newFieldForRtyID = 0; 
        
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
        
    _destroy: function() {
        
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
                
            html = html + '<div class="rec_actions user-list" style="top:4px;width:60px;">'
                    + '<div title="Click to edit field" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;&nbsp;';
               if(fld('dty_Status')=='reserved'){ 
                    html = html      
                    + '<div title="Status: reserved - Locked" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-lock"></span><span class="ui-button-text"></span>'
                    + '</div>';    
               }else{
                    html = html      
                    + '<div title="Status: '+fld('dty_Status')+' - Delete" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete" style="height:16px">'
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
        
        if(!(this._currentEditID>0)){ //insert       
            
            var dty_DetailTypeGroupID = this.searchForm.find('#input_search_group').val();
            
            if(!(dty_DetailTypeGroupID>0)){
                //take first from list of groups
                dty_DetailTypeGroupID = window.hWin.HEURIST4.detailtypes.groups[0].id;                
            }

            var ele = this._editing.getFieldByName('dty_DetailTypeGroupID');
            ele.editing_input('setValue', dty_DetailTypeGroupID, false);
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
                    
                    window.hWin.HEURIST4.ui.addoption(el, _dty_Type, window.hWin.HEURIST4.detailtypes.lookups[_dty_Type]);

                    if(_dty_Type=='float' || _dty_Type=='date'){
                        window.hWin.HEURIST4.ui.addoption(el, 'freetext', window.hWin.HEURIST4.detailtypes.lookups['freetext']);
                    }else if(_dty_Type=='freetext'){
                        window.hWin.HEURIST4.ui.addoption(el, 'blocktext', window.hWin.HEURIST4.detailtypes.lookups['blocktext']);
                    }else if(_dty_Type=='blocktext'){
                        window.hWin.HEURIST4.ui.addoption(el, 'freetext', window.hWin.HEURIST4.detailtypes.lookups['freetext']);
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
                }else if(!this.set_detail_type_btn){
                    //change selector to button
                    var ele = this._editing.getFieldByName('dty_Type');  
                    ele = ele.find('.input-div');
                    ele.find('.ui-selectmenu-button').hide();
                    
                    this.set_detail_type_btn = $('<button>')
                        .button({label:'click to select data type'})
                        .css('min-width', '200px');
                    this.set_detail_type_btn.appendTo(ele);
                    
                    var that = this;
                    
                    this._on( this.set_detail_type_btn, {    
                        'click': function(event){

                            var dt_type = this._editing.getValue('dty_Type')[0];

                            var dim = { h:530, w:800 };
                            var sURL = window.hWin.HAPI4.baseURL +
                            "admin/structure/fields/selectFieldType.html?&db="+window.hWin.HAPI4.database;
                            window.hWin.HEURIST4.msg.showDialog(sURL, {
                                "close-on-blur": false,
                                //"no-resize": true,
                                //"no-close": true, //hide close button
                                title: 'Select data type of field',
                                height: dim.h,
                                width: dim.w,
                                callback: function(context) {
                                    if(context!="" && context!=undefined) {

                                        var changeToNewType = true;
                                        if(((dt_type==="resource") || (dt_type==="relmarker") || 
                                            (dt_type==="enum"))  && dt_type!==context)
                                        {

                                            window.hWin.HEURIST4.msg.showMsgDlg("If you change the type to '"
                                                + window.hWin.HEURIST4.detailtypes.lookups[context] 
                                                + "' you will lose all your settings for type '"   //vocabulary 
                                                + window.hWin.HEURIST4.detailtypes.lookups[dt_type]+
                                                "'.\n\nAre you sure?",                                            
                                                function(){   
                                                    that._onDataTypeChange(context);                                                   
                                                }, {title:'Change type for field',yes:'Continue',no:'Cancel'});                                                
                                        }else{
                                            that._onDataTypeChange(context);                                                   
                                        }                            


                                    }
                                }
                            });

                    }});
                }

            $(elements[0]).change(); //trigger
        }
        
        if(this.options.newFieldForRtyID>0){
            
            //disable all fields except field name
            var elements = this._editing.getInputs('dty_Name');
            this._on( $(elements[0]), {
                keypress: window.hWin.HEURIST4.ui.preventChars,
                keyup: this._onFieldAddSuggestion });
        }
        

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
               this.set_detail_type_btn.button({label:window.hWin.HEURIST4.detailtypes.lookups[dt_type]});
               var elements = this._editing.getInputs('dty_Type');               
               $(elements[0]).val( dt_type );
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
           if(dt_type=='enum' || dt_type=='relationtype' || dt_type=='relmarker'){
                var ele = this._editing.getFieldByName('dty_Mode_enum');  
                this._activateEnumControls(ele);
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
      
        if(input_name.val().length>2){
           
            var rty_ID = this.options.rty_ID; 
            var dty_ID, field_name,
                fi = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex;
                
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


            //find among fields that are not in current record type
            for (dty_ID in window.hWin.HEURIST4.detailtypes.names){
               if(dty_ID>0){ 
                   var td = window.hWin.HEURIST4.detailtypes.typedefs[dty_ID];
                   var deftype = td.commonFields;

                   var aUsage = window.hWin.HEURIST4.detailtypes.rectypeUsage[dty_ID];
                   
                   field_name = window.hWin.HEURIST4.detailtypes.names[dty_ID];

                   if( deftype[fi.dty_ShowInLists]!="0" && deftype[fi.dty_Type]!='separator'
                        && ( window.hWin.HEURIST4.util.isnull(aUsage) || aUsage.indexOf(rty_ID)<0 ) 
                        && (field_name.toLowerCase().indexOf( input_name.val().toLowerCase() )>=0) )
                   {
                       
                       var ele;
                       if(field_name.toLowerCase()==input_name.val().toLowerCase()){
                           ele = first_ele;
                       }else{
                           ele = $('<div class="truncate">').appendTo(this.fields_list_div);
                       }

                       is_added = true;
                       ele.attr('dty_ID',dty_ID)
                           .text(field_name+' ['+ window.hWin.HEURIST4.detailtypes.lookups[deftype[fi.dty_Type]] +']')
                           .click( function(event){
                                window.hWin.HEURIST4.util.stopEvent(event);

                                var ele = $(event.target).hide();
                                var _dty_ID = ele.attr('dty_ID');
                         
                       
                                if(_dty_ID>0){
                                    that.fields_list_div.hide();
                                    input_name.val('').focus();
                                    
                                    window.hWin.HEURIST4.msg.showMsgFlash('Field is added to record structure');
                                    
                                    //that.selectedRecords( [_dty_ID] );
                                    //that._selectAndClose();
                                    that._trigger( "onselect", null, {selection: [_dty_ID] });
                                    that.closeDialog( true ); //force without warning
                                    
                                }
                       });
                        
                   }
                }
            }

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
    _activateEnumControls: function( ele ){
        
            var ele = ele.find('.input-div');
            //remove old content
            ele.empty();
            
            
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

    //
    //
    //
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
        var is_vocabulary = window.hWin.HEURIST4.util.isNumber(allTerms);
        if(is_vocabulary){
            defaultTermID = allTerms; //vocabulary
            this.enum_container.find('input[name="enumType"][value="vocabulary"]').prop('checked',true).trigger('change');
        }else{
            this.enum_container.find('input[name="enumType"][value="individual"]').prop('checked',true).trigger('change');
        }
        
        var orig_selector = this.enum_container.find("#selVocab");
        var selnew = window.hWin.HEURIST4.ui.createTermSelectExt2(orig_selector[0], 
            {vocabsOnly:true, datatype:term_type, topOptions:'select...', useHtmlSelect:false, 
                defaultTermID:is_vocabulary?defaultTermID:0 })

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

        //update local definitions
        var detailtypes = window.hWin.HEURIST4.detailtypes;
        var fi = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex;
        if(!detailtypes.typedefs[recID]){
            detailtypes.typedefs[recID] = {commonFields:[]};
            var len = Object.keys(fi).length;
            for(var i=0; i<len; i++) detailtypes.typedefs[recID].commonFields.push('');
        }
        //var recset = this.getRecordSet()
        //var record = recset.getById(recID);
        var fields = detailtypes.typedefs[recID].commonFields;
        for(var fname in fi)
        if(fname){
            fields[fi[fname]] = fieldvalues[fname]; //recset.fld(record, fname);
        }

        // close on addition of new record in select_single mode    
        if(this._currentEditID<0 && 
            (this.options.selectOnSave===true || this.options.select_mode=='select_single'))
        {
                this._selection = new hRecordSet();
                this._selection.addRecord(recID, fieldvalues);
                this._selectAndClose();
                return;    
                    
        }
        
        this._super( recID, fieldvalues );
        
        if(this.options.edit_mode == 'editonly'){
            this.closeDialog(true); //force to avoid warning
        }else{
            var recset = this.recordList.resultList('getRecordSet');
            recset.setRecord(recID, fieldvalues);
            this.recordList.resultList('refreshPage');  
            //this.getRecordSet().setRecord(recID, fieldvalues);
            //this.updateRecordList();
        }
    },

    
    //-----------------------------------------------------
    //
    // send update request and close popup if edit is in dialog
    // afteraction is used in overriden version of this method
    //
    _saveEditAndClose: function( fields, afterAction ){
        
        var that_widget = this;
        
        if(!fields){
            fields = this._getValidatedValues();         
            
            if(fields!=null){
                var dt_type = fields['dty_Type'];
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
                            'Please select target record type. Unconstrained relationship is not allowed',null, 'Warning');
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
            && window.hWin.HEURIST4.detailtypes.typedefs[this._currentEditID] 
            && 'reserved'==window.hWin.HEURIST4.detailtypes.typedefs[this._currentEditID].commonFields[
                                window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex.dty_Status])
        {
        
            if(window.hWin.HAPI4.sysinfo['pwd_ReservedChanges']){ //password defined
            
                window.hWin.HEURIST4.msg.showPrompt('Enter password: ',
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
                'This action is password-protected', {password:true});
            }else{
                window.hWin.HEURIST4.msg.showMsgDlg('Reserved field changes is not allowed unless a challenge password is set'
                +' - please consult system administrator');
            }
            return;
        }
        fields['pwd_ReservedChanges'] = null;
        
        this._super( fields, afterAction );
        
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
            if(dt_type=='enum' || dt_type=='relationtype' || dt_type=='relmarker'){

                if(!fieldvalues['dty_JsonTermIDTree']){

                    if(dt_type=='enum'){    
                        window.hWin.HEURIST4.msg.showMsgErr(
                            'Please select or add a vocabulary. Vocabularies must contain at least one term.', 'Warning');
                    }else{
                        window.hWin.HEURIST4.msg.showMsgDlg(
                            'Please select or add relationship types',null, 'Warning');
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

});