/**
* manageDefTerms.js - main widget to manage defTerms
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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


$.widget( "heurist.manageRecords", $.heurist.manageEntity, {
   
    _entityName:'records',
    
    _currentEditRecTypeID:null,
    _currentEditRecordset:null,
    
    toolbar:null,
    
    _edit_dialog:null,
    
    _init: function() {
        if(this.options.layout_mode=='basic') //replace default short 
        this.options.layout_mode = //slightly modified 'short' layout
                        '<div class="ent_wrapper">'
                            +'<div class="ent_wrapper" style="width:200px">'
                                +    '<div class="ent_header searchForm"/>'     
                                +    '<div class="ent_content_full recordList"/>'
                            +'</div>'
                        //    + '<div id="editFormDialog" class="ent_wrapper editForm">'
// style="height:100%; width:100%;"
                              + '<div class="editFormDialog ent_wrapper">'
                                + '<div class="ui-layout-center"><div class="editForm"/></div>' // ui-layout-content
                                //+ '<div class="editForm ui-layout-center"/>'
                                + '<div class="editFormSummary ui-layout-east">empty</div>'
                        
                            +'</div>'
                        +'</div>';

        
        
        this.options.use_cache = false;
        this.options.edit_height = 640;
        this.options.edit_width = 1200;

        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            if(this.options.edit_mode != 'inline'){
                this.options.width = 640;      
            }              
        }else{
            this.options.width = 1200;                    
        }
    
        this._super();
        
        /*
        if(!(this.options.select_mode!='manager' || this.options.edit_mode!='inline')){
            //edit form is not visible
            this.recordList.parent().width(640);
            this.editForm.parent().css('left',641);
        }
        */
        this.editForm.parent().show(); //restore 
        this.editFormPopup = this.element.find('.editFormDialog').hide();
        this.editFormSummary = this.element.find('.editFormSummary');
        //this.element.find('#editFormSummary').hide(); //temp
        
        this.element.find('#editFormDialog').hide();
        
        //-----------------
        this.recordList.css('top','5.5em');
        if(this.searchForm && this.searchForm.length>0)this.searchForm.height('7.5em').css('border','none');
        
        
    },
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }

        // init search header
        if(this.searchForm && this.searchForm.length>0)this.searchForm.searchRecords(this.options);
/*        
        var iheight = 2;
        //if(this.searchForm.width()<200){  - width does not work here  
        if(this.options.select_mode=='manager'){            
            iheight = iheight + 4;
        }
        
        this.searchForm.css({'height':iheight+'em'});
        this.recordList.css({'top':iheight+0.4+'em'});
*/        
        this.recordList.resultList({
                searchfull:null,
                renderer:true //use default renderer but custom onaction see _onActionListener
        }); //use default recordList renderer
        
        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
        }
        
        if(this.searchForm && this.searchForm.length>0){
        this._on( this.searchForm, {
                "searchrecordsonresult": this.updateRecordList,
                "searchrecordsonaddrecord": function( event, _rectype_id ){
                    this._currentEditRecTypeID = _rectype_id;
                    this.addEditRecord(-1);
                }
        });
        }
                
       //---------    EDITOR PANEL - DEFINE ACTION BUTTONS
       //if actions allowed - add div for edit form - it may be shown as right-hand panel or in modal popup
       if(this.options.edit_mode!='none'){
/*
           //define add button on left side
           this._defineActionButton({key:'add', label:'Add New Vocabulary', title:'', icon:'ui-icon-plus'}, 
                        this.editFormToolbar, 'full',{float:'left'});
                
           this._defineActionButton({key:'add-child',label:'Add Child', title:'', icon:''},
                    this.editFormToolbar);
           this._defineActionButton({key:'add-import',label:'Import Children', title:'', icon:''},
                    this.editFormToolbar);
           this._defineActionButton({key:'merge',label:'Merge', title:'', icon:''},
                    this.editFormToolbar);
               
           //define delete on right side
           this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-minus'},
                    this.editFormToolbar,'full',{float:'right'});
*/                    
       }
        
       return true;
    },


    _navigateToRec: function(dest){
        if(this._currentEditID>0){
                var recset = this.recordList.resultList('getRecordSet');
                var order  = recset.getOrder();
                var idx = order.indexOf(Number(this._currentEditID));
                idx = idx + dest;
                if(idx>=0 && idx<order.length){
                    this.toolbar.find('#divNav').html( (idx+1)+' of '+order.length);
                    if(dest!=0){
                        this.addEditRecord(order[idx]);
                    }
                }
        }
    },    
    //override some editing methods
    
    //
    // open popup edit dialog if need it
    //
    _initEditForm_step1: function(recID){
    
        if(recID!=null && this.options.edit_mode!='none'){ //show in popup 
        
            var isOpenAready = false;
            if(this.options.edit_mode=='popup' && this._edit_dialog){
                try{
                    isOpenAready = this._edit_dialog.dialog('isOpen');
                }catch(e){}
            }

            if(!isOpenAready){            
        
                var that = this; 
                this._currentEditID = recID;
                
                var recset = this.recordList.resultList('getRecordSet');
                var recset_length = recset.length();

                var btn_array = [
                                  {text:window.hWin.HR('Duplicate'), id:'btnRecDuplicate',
                                  css:{'display':((that._currentEditID>0)?'inline-block':'none')},
                            click: function(event) { 
                                var btn = $(event.target);
                                btn.hide();
                                window.hWin.HAPI4.RecordMgr.duplicate({id: that._currentEditID}, 
                                    function(response){
                                        btn.css('display','inline-block');
                                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                            window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Record has been duplicated'));
                                            var new_recID = ''+response.data.added;
                                            that._initEditForm_continue(new_recID);
                                        }else{
                                            window.hWin.HEURIST4.msg.showMsgErr(response);
                                        }
                                    }); 
                            }},
                                  {text:window.hWin.HR('Previous'),icons:{primary:'ui-icon-circle-triangle-w'},
                                  css:{'display':((recset_length>1 && recID>0)?'inline-block':'none')}, id:'btnPrev',
                            click: function() { that._navigateToRec(-1); }},
                                  {text:window.hWin.HR('Next'),icons:{secondary:'ui-icon-circle-triangle-e'},
                                  css:{'display':((recset_length>1 && recID>0)?'inline-block':'none')},
                            click: function() { that._navigateToRec(1); }},
                                  {text:window.hWin.HR('Cancel'), id:'btnRecCancel', 
                                  css:{'visibility':'hidden'},
                            click: function() { that._initEditForm_continue(that._currentEditID) }},  //reload edit form
                                  {text:window.hWin.HR('Save and new record'), id:'btnRecSaveAndNew',
                                  css:{'visibility':'hidden'},
                            click: function() { that._saveEditAndClose( 'newrecord' ); }},
                                  {text:window.hWin.HR('Save'), id:'btnRecSave',
                                  css:{'visibility':'hidden'},
                            click: function() { that._saveEditAndClose( 'none' ); }},
                                  {text:window.hWin.HR('Save and Close'), id:'btnRecSaveAndClose',
                                  css:{'visibility':'hidden'},
                            click: function() { that._saveEditAndClose( 'close' ); }},
                                  {text:window.hWin.HR('Close'), 
                            click: function() { that._edit_dialog.dialog('close'); }}]; 
                
                if(this.options.edit_mode=='popup'){
                
                    this.editForm.css({'top': 0, overflow:'auto !important'});
                 
                this._edit_dialog =  window.hWin.HEURIST4.msg.showElementAsDialog({
                        window:  window.hWin, //opener is top most heurist window
                        element:  this.editFormPopup[0],
                        height: this.options['edit_height']?this.options['edit_height']:400,
                        width:  this.options['edit_width']?this.options['edit_width']:740,
                        title: this.options['edit_title']
                                    ?this.options['edit_title']
                                    :window.hWin.HR('Edit') + ' ' +  this.options.entity.entityName,                         
                        buttons: btn_array
                    });
                    
                 
/*                    this.editFormPopup.dialog({
                        autoOpen: true,
                        height: this.options['edit_height']?this.options['edit_height']:400,
                        width:  this.options['edit_width']?this.options['edit_width']:740,
                        modal:  true,
                        title: this.options['edit_title']
                                    ?this.options['edit_title']
                                    :window.hWin.HR('Edit') + ' ' +  this.options.entity.entityName,
                        resizeStop: function( event, ui ) {//fix bug
                            that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
                        },
                        buttons: btn_array
                    });        
*/                    
                    //help and tips buttons on dialog header
                    window.hWin.HEURIST4.ui.initDialogHintButtons(this._edit_dialog,
                     window.hWin.HAPI4.baseURL+'context_help/'+this.options.entity.helpContent+' #content');
            
                    this.toolbar = this._edit_dialog.parent(); //this.editFormPopup.parent();
            
                }//popup
                else if(this.editFormToolbar){ //initialize action buttons
                    
                    btn_array.pop();btn_array.pop();
                    
                    this.toolbar = this.editFormToolbar;
                    this.editFormToolbar.empty();
                    for(var idx in btn_array){
                        this._defineActionButton2(btn_array[idx], this.editFormToolbar);
                    }
                }

                if(recset_length>1 && recID>0){
                    $('<div id="divNav" style="min-width:40px;padding:0 4px;display:inline-block;text-align:center">')
                        .insertAfter(this.toolbar.find('#btnPrev'));
                    this._navigateToRec(0);
                }
                    
                if(this.editFormSummary && this.editFormSummary.length>0){    
                    var layout_opts =  {
                        applyDefaultStyles: true,
                        togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-e"></div>',
                        togglerContent_closed:  '<div class="ui-icon ui-icon-triangle-1-w"></div>',
                        //togglerContent_open:    '&nbsp;',
                        //togglerContent_closed:  '&nbsp;',
                        east:{
                            size: 400,
                            spacing_open:6,
                            spacing_closed:16,  
                            togglerAlign_open:'center',
                            togglerAlign_closed:'top',
                            togglerLength_closed:16,  //makes it square
                            initClosed:true,
                            slidable:false  //otherwise it will be over center and autoclose
                        },
                        center:{
                            minWidth:800,
                            contentSelector: '.editForm'    
                        }

                    };

                    this.editFormPopup.addClass('ui-heurist-bg-light').layout(layout_opts);

                    //load content for editFormSummary
                    if(this.editFormSummary.text()=='empty'){
                        this.editFormSummary.empty();
                        var headers = ['Admin','Links','Scratchpad','Private','Workgroup Tags','Text','Discussion'];
                        for(var idx in headers){
                            $('<h3>').text(top.HR(headers[idx])).appendTo(this.editFormSummary);
                            //content
                            $('<div>').attr('data-id', idx).addClass('summary-content ui-heurist-bg-light').appendTo(this.editFormSummary);
                        }
                        this.editFormSummary.accordion({
                            collapsible: true,
                            heightStyle: "content",
                            beforeActivate:function(event, ui){
                                if(ui.newPanel.text()==''){
                                    var sContent = '';
                                    var idx = ui.newPanel.attr('data-id');
                                    //load content for panel to be activated
                                    if(idx==0){//admin
                                        sContent = '<span>added:'+that._getField('rec_Added')+'</span><br>'
                                                +'<span>updated:'+that._getField('rec_Modified')+'</span>';
                                    }else{
                                        sContent = 'to be implemented';
                                    }
                                    $(sContent).appendTo(ui.newPanel);
                                }
                        }});
                    }
                }
            }//!isOpenAready
            
        }
        this._initEditForm_continue(recID); 
    },
    
    _getField: function(fname){
        
        if(this._currentEditRecordset){
            var record = this._currentEditRecordset.getFirstRecord();
            var value  = this._currentEditRecordset.fld(record, fname);
            return value;
        }else{
            return '';
        }
    },
    
    //
    //
    //
    _initEditForm_continue: function(recID){
        
        //fill with values
        this._currentEditID = recID;
        
        var that = this;
        
        function __load(response){
            if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                
                //@todo - move navigation for recordset into editing
                that._currentEditRecordset = new hRecordSet(response.data);
                
                var rectypeID = that._getField('rec_RecTypeID');
                var rectypes = window.hWin.HEURIST4.rectypes;
                var rfrs = rectypes.typedefs[rectypeID].dtFields;
                
                //pass structure and record details
                that._currentEditID = that._getField('rec_ID');;
                that._currentEditRecTypeID = rectypeID;
                
                //@todo - move it inside editing
                //convert structure - 
                var fields = window.hWin.HEURIST4.util.cloneJSON(that.options.entity.fields);
                var fieldNames = rectypes.typedefs.dtFieldNames;
                var fi = rectypes.typedefs.dtFieldNamesToIndex;

                var fi_type = fi['dty_Type'],
                    fi_name = fi['rst_DisplayName'],
                    fi_order = fi['rst_DisplayOrder'],
                    fi_maxval = fi['rst_MaxValues']; //need for proper repeat
                
                var s_fields = []; //sorted fields
                for(var dt_ID in rfrs){
                    if(dt_ID>0){
                        rfrs[dt_ID]['dt_ID'] = dt_ID;
                        s_fields.push(rfrs[dt_ID]);
                    }
                }
                
                //sort by order
                s_fields.sort(function(a,b){ return a[fi_order]<b[fi_order]?-1:1});

                var group_fields = null;
                
                for(var k=0; k<s_fields.length; k++){
                    
                    rfr = s_fields[k];
                    
                    if(rfr[fi_type]=='separator'){
                        if(group_fields!=null){
                            fields[fields.length-1].children = group_fields;
                        }
                        var dtGroup = {
                            groupHeader: rfr[fi_name],
                            groupType: 'group', //accordion, tabs, group
                            groupStyle: {},
                            children:[]
                        };
                        fields.push(dtGroup);
                        group_fields = [];
                    }else {
                    
                        var dtFields = {};
                        for(idx in rfr){
                            if(idx>=0){
                                dtFields[fieldNames[idx]] = rfr[idx];
                                
                                if(idx==fi_type){ //fieldNames[idx]=='dty_Type'){
                                    if(dtFields[fieldNames[idx]]=='file'){
                                        dtFields['rst_FieldConfig'] = {"entity":"records", "accept":".png,.jpg,.gif", "size":200};
                                    }   
                                }else if(idx==fi_maxval){
                                    if(window.hWin.HEURIST4.util.isnull(dtFields[fieldNames[idx]])){
                                        dtFields[fieldNames[idx]] = 0;
                                    }
                                }
                            }
                        }//for
                        
                        if(group_fields!=null){
                            group_fields.push({"dtID": rfr['dt_ID'], "dtFields":dtFields});
                        }else{
                            fields.push({"dtID": rfr['dt_ID'], "dtFields":dtFields});
                        }
                    }
                }//for rfrs
                //add children to last group
                if(group_fields!=null){
                    fields[fields.length-1].children = group_fields;
                }
                
                that._editing.initEditForm(fields, that._currentEditRecordset);
                that._afterInitEditForm();
                
                if(that.editFormSummary && that.editFormSummary.length>0){
                    that.editFormSummary.accordion({
                        active:0
                    });
                }
                
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        }        
        

        //clear content of accordion
        if(this.editFormSummary && this.editFormSummary.length>0){
            this.editFormSummary.find('.summary-content').empty();
            this.editFormSummary.accordion({active: false});
        }
        
        if(recID==null){
            this._editing.initEditForm(null, null); //clear and hide
        }else if(recID>0){ //edit existing record
            
            window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+recID, w: "all", f:"complete", l:1}, __load);

        }else if(recID<0 && this._currentEditRecTypeID>0){ //add new record
            //this._currentEditRecTypeID is set in add button
            window.hWin.HAPI4.RecordMgr.add( {rt:this._currentEditRecTypeID, temp:1}, //ro - owner,  rv - visibility
                        __load);
        }
        
        
        

        return;
    },
    
    
    //  -----------------------------------------------------
    //
    //  send update request and close popup if edit is in dialog
    // OVERRIDE
    //
    _saveEditAndClose: function( afterAction ){

            var fields = this._getValidatedValues(); 
            
            if(fields==null) return; //validation failed
       
            var request = {ID: this._currentEditID, RecTypeID: this._currentEditRecTypeID, 'details': fields};
        
            var that = this;                                                
            
            that.onEditFormChange(true); //forcefully hide all "save" buttons
            
                //that.loadanimation(true);
                window.hWin.HAPI4.RecordMgr.save(request, 
                    function(response){
                        
                        //that.onEditFormChange();
                        
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

                            var recID = ''+response.data[0];
                            
                            //that._afterSaveEventHandler( recID, fields);
                            window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Record has been saved'));
                            
                            if(afterAction=='close'){
                                that._currentEditID = null;
                                that._edit_dialog.dialog('close');    
                            }else if(afterAction=='newrecord'){
                                that._initEditForm_continue(-1);
                            }else{
                                //reload after save
                                that._initEditForm_continue(that._currentEditID)
                            }
                            
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
    },   
    
    //
    //
    //
    onEditFormChange:function(force_hide){
        var mode = 'hidden';
        if(force_hide!==true){
            var isChanged = this._editing.isModified();
            mode = isChanged?'visible':'hidden';
        }
        //show/hide save buttons
        var ele = this.toolbar;
        ele.find('#btnRecCancel').css('visibility', mode);
        ele.find('#btnRecSaveAndNew').css('visibility', mode);
        ele.find('#btnRecSave').css('visibility', mode);
        ele.find('#btnRecSaveAndClose').css('visibility', mode);
        
    },
    
    //
    //
    //    
    _afterInitEditForm: function(){
        this.onEditFormChange();
    },
    
    
});

//
// Show as dialog - to remove
//
function showManageRecords( options ){

    var manage_dlg; // = $('#heurist-records-dialog');  //@todo - unique ID

    if(true){ //manage_dlg.length<1){
        
        options.isdialog = true;

        manage_dlg = $('<div id="heurist-records-dialog-'+window.hWin.HEURIST4.util.random()+'">')
                .appendTo( $('body') )
                .manageRecords( options );
    }

    manage_dlg.manageRecords( 'popupDialog' );
}
