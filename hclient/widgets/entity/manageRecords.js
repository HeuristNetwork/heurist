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

//EXPERIMENTAL - THIS FUNCTION IS IN COURSE OF DEVELOPMENT
$.widget( "heurist.manageRecords", $.heurist.manageEntity, {
   
    _entityName:'records',
    
    _currentEditRecTypeID:null,
    _isInsert: false,
    _updated_tags_selection: null,
    _keepYPos: 0,

    usrPreferences:{},
    defaultPrefs:{
        width:(window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
        height:(window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95,
        optfields:true, 
        help_on:true,
        summary_closed:false,          
        summary_width:400, 
        summary_tabs:['0','1']},
    
    
    _init: function() {

        if(this.options.layout_mode=='short'){
                this.options.layout_mode = //slightly modified 'short' layout
                        '<div class="ent_wrapper editor">'
                            +'<div class="ent_wrapper">'
                                +    '<div class="ent_header searchForm"/>'     
                                +    '<div class="ent_content_full recordList"/>'
                            +'</div>'

                            //for inline edit - todo remove and not use!    
                            + '<div class="editFormDialog ent_wrapper editor">'
                                    + '<div class="ui-layout-center"><div class="editForm"/></div>'
                                    + '<div class="ui-layout-east"><div class="editFormSummary">....</div></div>'
                                    //+ '<div class="ui-layout-south><div class="editForm-toolbar"/></div>'
                            +'</div>'
                        +'</div>';
        }
        
        this.options.use_cache = false;
        //this.options.edit_height = 640;
        //this.options.edit_width = 1200;

        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<640)?640:this.options.width;      
        }else{
            this.options.width = 1200;                    
        }

        this.getUiPreferences();
        
        
        this._super();
        
        //this.editForm.empty();

        this.editHeader = this.element.find('.editHeader');
        
        var hasSearchForm = (this.searchForm && this.searchForm.length>0);
        
        if(this.options.edit_mode=='inline' || this.options.edit_mode=='editonly'){
            // for manager - inline mode means that only editor is visible and we have to search init exterally
            // see recordEdit.php
            if(hasSearchForm) this.searchForm.parent().css({width:'0px'});    
            this.editFormPopup.css({left:0}).show();
        }else{
            //for select mode we never will have inline mode
            if(hasSearchForm) this.searchForm.parent().width('100%');
            this.editFormPopup.css({left:0}).hide(); 
        }
        
        //-----------------
        var sh = 7;
        if(hasSearchForm){
            sh = (this.options.parententity>0)?9:7;
            this.searchForm.height((sh+2.5)+'em').css('border','none');    
        }
        this.recordList.css('top', sh+'em');

        var that = this;
        
        jQuery(document).keydown(function(event) {
                // If Control or Command key is pressed and the S key is pressed
                // run save function. 83 is the key code for S.
                if((event.ctrlKey || event.metaKey) && event.which == 83) {
                    // Save Function
                    event.preventDefault();
                    that._saveEditAndClose( null, 'none' );
                    return false;
                }
            }
        );        
        
    },
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }

        // init search header
        if(this.searchForm && this.searchForm.length>0){
            this.searchForm.addClass('ui-heurist-bg-light').searchRecords(this.options);    
        }
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
                

        this.editForm.css({'overflow-y':'auto !important', 'overflow-x':'hidden'});
            
        return true;
    },
    
    _initDialog: function(){
        
            if(this.options.edit_mode == 'editonly'){
                this.getUiPreferences();
                this.options['width']  = this.usrPreferences['width'];
                this.options['height'] = this.usrPreferences['height'];
            }
        
            this._super();
    },

    _navigateToRec: function(dest){
        if(this._currentEditID>0){
                var recset = this.recordList.resultList('getRecordSet');
                var order  = recset.getOrder();
                var idx = order.indexOf(Number(this._currentEditID));
                idx = idx + dest;
                if(idx>=0 && idx<order.length){
                    
                    var newRecID = order[idx];
                    var that = this;
                    
                    if(this._editing.isModified()){
                        
                        var $__dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                        'Save changes and move to '+((dest<0)?'previous':'next')+' record?',
                        {'Save changes' :function(){ 
                                //save changes and go to next step
                                that._saveEditAndClose( null, function(){ that.addEditRecord(newRecID); } );
                                $__dlg.dialog( "close" );
                            },
                         'Drop changes':function(){ 
                                that._currentEditID = null;
                                that.addEditRecord(newRecID);
                                //that._initEditForm_step3(that._currentEditID)
                                $__dlg.dialog( "close" );
                            },
                         'Cancel':function(){ 
                                $__dlg.dialog( "close" );
                            }},
                            {title:'Confirm'});
                            
                    }else{
                        this._toolbar.find('#divNav').html( (idx+1)+'/'+order.length);
                        
                        window.hWin.HEURIST4.util.setDisabled(this._toolbar.find('#btnPrev'), (idx==0));
                        window.hWin.HEURIST4.util.setDisabled(this._toolbar.find('#btnNext'), (idx+1==order.length));
                        
                        if(dest!=0){
                            this.addEditRecord(newRecID);
                        }
                    }
                }
        }
    },    
    //override some editing methods
    
    _getEditDialogButtons: function(){
                                    
            var that = this;        
            var btns = [];

            if(this.options.selectOnSave==true){
                var btns = [
                        {text:window.hWin.HR('Cancel'), 
                              css:{'margin-right':'15px'},
                              click: function() { 
                                  that.closeEditDialog(); 
                              }},
                        /*{text:window.hWin.HR('Cancel'), id:'btnRecCancel', 
                              css:{'visibility':'hidden','margin-right':'15px'},
                              click: function() { that.closeEditDialog(); }},*/
                       
                        {text:window.hWin.HR('Save'), id:'btnRecSaveAndClose',
                              css:{'margin-right':'15px'},
                              click: function() { that._saveEditAndClose( null, 'close' ); }}
                
                ];
            }else{
                var btns = [       /*{text:window.hWin.HR('Reload'), id:'btnRecReload',icons:{primary:'ui-icon-refresh'},
                        click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form*/
                              
                        {showText:false, icons:{primary:'ui-icon-circle-triangle-w'},title:window.hWin.HR('Previous'),
                              css:{'display':'none','margin-right':'0.5em','float':'left'}, id:'btnPrev',
                              click: function() { that._navigateToRec(-1); }},
                        {showText:false, icons:{secondary:'ui-icon-circle-triangle-e'},title:window.hWin.HR('Next'),
                              css:{'display':'none','margin-left':'0.5em','float':'left'}, id:'btnNext',
                              click: function() { that._navigateToRec(1); }},
                              
                        {text:window.hWin.HR('Dupe'), id:'btnRecDuplicate',
                                css:{'margin-left':'1em'},
                                click: function(event) { 

                                    that._saveEditAndClose( null, function(){ 
                                    
                                    var btn = $(event.target);                        
                                    btn.hide();
                                    
                                    var dlged = that._getEditDialog();
                                    if(dlged) window.hWin.HEURIST4.msg.bringCoverallToFront(dlged);
                                    
                                    window.hWin.HAPI4.RecordMgr.duplicate({id: that._currentEditID}, 
                                        function(response){

                                            window.hWin.HEURIST4.msg.sendCoverallToBack();
                                            
                                            btn.css('display','inline-block');  //restore button visibility
                                            if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                                window.hWin.HEURIST4.msg.showMsgFlash(
                                                    window.hWin.HR('Record has been duplicated'));
                                                var new_recID = ''+response.data.added;
                                                that._initEditForm_step3(new_recID);
                                                
                                                var dlged = that._getEditDialog();
                                                dlged.find('.coverall-div-bare').remove();
                                                
                                            }else{
                                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                            }
                                        }); 
                                        
                                    });
                                }},
                        {text:window.hWin.HR('New'), id:'btnRecSaveAndNew',
                              css:{'margin-left':'0.5em','margin-right':'10em'},
                              click: function() { that._saveEditAndClose( null, 'newrecord' ); }},
                                
                                
                                
                        {text:window.hWin.HR('Save'), id:'btnRecSave',
                              accesskey:"S",
                              css:{'font-weight':'bold'},
                              click: function() { that._saveEditAndClose( null, 'none' ); }},
                        {text:window.hWin.HR('Save + Close'), id:'btnRecSaveAndClose',
                              css:{'margin-left':'0.5em'},
                              click: function() { that._saveEditAndClose( null, 'close' ); }},
                        {text:window.hWin.HR('Close'), 
                              css:{'margin-left':'0.5em'},
                              click: function() { 
                                  that.closeEditDialog(); 
                              }},
                        {text:window.hWin.HR('Drop Changes'), id:'btnRecCancel', 
                              css:{'margin-left':'3em'},
                              click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form
                              
                              
                              ]; 
                              
            }
            return btns;
    },
    
    _initEditForm_step1: function(recID){
        if(this.options.edit_mode=='popup'){

            var query = null, popup_options={};
            //NEW WAY open as another widget 
            if(recID<0){
                popup_options = {selectOnSave:this.options.selectOnSave, 
                                 new_record_params:{rt:this._currentEditRecTypeID}};
                if(this.options.select_mode!='manager' && this.options.selectOnSave){ 
                    //this is select form that all addition of new record
                    //it should be closed after addition of new record
                    var that = this;
                    popup_options['onselect'] = function(event, data){
                            if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                //that.selectedRecords(data.selection);
                                //that._selectAndClose();

                                //save last 25 selected records
                                /*
                                var previously_selected_ids = window.hWin.HAPI4.get_prefs('recent_Records');
                                if(!window.hWin.HEURIST4.util.isArrayNotEmpty(previously_selected_ids)){
                                    previously_selected_ids = [];
                                }
                                var now_selected = data.selection.getIds(25);
                                now_selected = now_selected.concat(previously_selected_ids);
                                */
                                var now_selected = data.selection.getIds(25);
                                window.hWin.HAPI4.save_pref('recent_Records', now_selected, 25);      
                                
                                that._trigger( "onselect", null, {selection:data.selection});  
                                that.closeDialog();
                            }
                    };
                    
                }
            }else{
                var recset = this.recordList.resultList('getRecordSet');
                if(recset && recset.length()<1000){
                    query = 'ids:'+recset.getIds().join(',');
                }else{
                    query = null;
                }
            }
            window.hWin.HEURIST4.ui.openRecordEdit( recID, query, popup_options);
            
        }else{
            this._super( recID );
        }
    },
    
    //
    // open popup edit dialog if we need it
    //
    _initEditForm_step2: function(recID){
    
        if(recID==null || this.options.edit_mode=='none') return;
        
        var isOpenAready = false;
        if(this.options.edit_mode=='popup'){
            if(this._edit_dialog){
                try{
                    isOpenAready = this._edit_dialog.dialog('isOpen');
                }catch(e){}
            }
        } else if(this.options.edit_mode=='inline') { //inline 
            isOpenAready = !this.editFormToolbar.is(':empty');
        }

        if(!isOpenAready){            
    
            var that = this; 
            this._currentEditID = recID;
            
            if(this.options.edit_mode=='popup'){
                //OLD WAY - NOT USED
                this.editForm.css({'top': 0});//, 'overflow-y':'auto !important', 'overflow-x':'hidden'});
//this.editFormPopup = this.editForm.parent();//this.element.find('.editFormDialog');

                if(!this.options.beforeClose){
                    this.options.beforeClose = function(){
                            that.saveUiPreferences();
                    };
                }
                
                this._edit_dialog =  window.hWin.HEURIST4.msg.showElementAsDialog({
                        window:  window.hWin, //opener is top most heurist window
                        element:  this.editFormPopup[0],
                        height: this.usrPreferences.height,
                        width:  this.usrPreferences.width,
                        resizable: true,
                        //title: dialog_title,
                        buttons: this._getEditDialogButtons(),
                        //do not save prefs for popup addition beforeClose: this.options.beforeClose
                        //save only for main edit record (editonly)
                    });
                    
                //assign unique identificator to get proper position of child edit dialogs
                //this._edit_dialog.attr('posid','edit'+this._entityName+'-'+(new Date()).getTime());
                    
                //help and tips buttons on dialog header
                this._edit_dialog.addClass('manageRecords'); //need for special behaviour in applyCompetencyLevel
                window.hWin.HEURIST4.ui.initDialogHintButtons(this._edit_dialog,
                    null, //'prefs_'+this._entityName,
                    window.hWin.HAPI4.baseURL+'context_help/'+this.options.entity.helpContent+' #content');
        
                this._toolbar = this._edit_dialog.parent(); //this.editFormPopup.parent();
        
            }//popup
            else { //initialize action buttons
                
                if(this.options.edit_mode=='editonly'){
                    //this is popup dialog
                   this.editFormToolbar
                     .addClass('ui-dialog-buttonpane')
                     .css({
                        padding: '0.8em 1em .2em .4em',
                        background: 'none',
                        'background-color': '#95A7B7 !important'
                     });
                }
                
                if(this.editFormToolbar && this.editFormToolbar.length>0){
                    
                    var btn_array = this._getEditDialogButtons();
                    
                    this._toolbar = this.editFormToolbar;
                    this.editFormToolbar.empty();
                    for(var idx in btn_array){
                        this._defineActionButton2(btn_array[idx], this.editFormToolbar);
                    }
                }
            }
            
            if(this._toolbar){
                this._toolbar.find('.ui-dialog-buttonset').css({'width':'100%','text-align':'right'});
            }
            
            
            if(this._toolbar.find('#divNav3').length===0){
                    $('<div id="divNav3" style="font-weight:bold;padding-left:10em;display:inline-block;text-align:right">Save then</div>')
                        .insertBefore(this._toolbar.find('#btnRecDuplicate'));
            }
            
            var recset = this.recordList.resultList('getRecordSet');
            if(recset && recset.length()>1 && recID>0){
                this._toolbar.find('#btnPrev').css({'display':'inline-block'});
                this._toolbar.find('#btnNext').css({'display':'inline-block'});
                if(this._toolbar.find('#divNav').length===0){
                    $('<div id="divNav2" style="font-weight:bold;padding:0.8em 1em;float:left;text-align:right">Step through filtered subset</div>')
                        .insertBefore(this._toolbar.find('#btnPrev'));
                        
                    $('<div id="divNav" style="font-weight:bold;padding-top:0.8em;min-width:40px;float:left;text-align:center">')
                        .insertAfter(this._toolbar.find('#btnPrev'));
                }
                this._navigateToRec(0); //reload
            }else{
                this._toolbar.find('#btnPrev').hide();
                this._toolbar.find('#btnNext').hide();
                this._toolbar.find('#divNav').hide();
                this._toolbar.find('#divNav2').hide();
            }
            
            //summary tab - specific for records only    
            if(this.editFormSummary && this.editFormSummary.length>0){    
                
                var layout_opts =  {
                    applyDefaultStyles: true,
                    togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-e"></div>',
                    togglerContent_closed:  '<div class="ui-icon ui-icon-triangle-1-w"></div>',
                    //togglerContent_open:    '&nbsp;',
                    //togglerContent_closed:  '&nbsp;',
                    east:{
                        size: this.usrPreferences.summary_width,
                        maxWidth:800,
                        spacing_open:6,
                        spacing_closed:16,  
                        togglerAlign_open:'center',
                        togglerAlign_closed:'top',
                        togglerLength_closed:16,  //makes it square
                        initClosed:(this.usrPreferences.summary_closed==true || this.usrPreferences.summary_closed=='true'),
                        slidable:false,  //otherwise it will be over center and autoclose
                        contentSelector: '.editFormSummary'    
                    },
                    /*south:{
                        spacing_open:0,
                        spacing_closed:0,
                        size:'3em',
                        contentSelector: '.editForm-toolbar'
                    },*/
                    center:{
                        minWidth:400,
                        contentSelector: '.editForm'    
                    }
                };

                this.editFormPopup.show().layout(layout_opts); //.addClass('ui-heurist-bg-light')
                if(!this.usrPreferences.summary_tabs) this.usrPreferences.summary_tabs = ['0','1'];

                //load content for editFormSummary
                if(this.editFormSummary.text()=='....'){
                    this.editFormSummary.empty();

                    var headers = ['Admin','Private','Tags','Linked records','Scratchpad','Discussion']; //,'Dates','Text',
                    for(var idx in headers){
                        var acc = $('<div>').addClass('summary-accordion').appendTo(this.editFormSummary);
                        
                        $('<h3>').text(top.HR(headers[idx])).appendTo(acc);
                        //content
                        $('<div>').attr('data-id', idx).addClass('summary-content').appendTo(acc);
                        
                        acc.accordion({
                            collapsible: idx>0, //admin is always open
                            active: (idx==0 || this.usrPreferences.summary_tabs.indexOf(String(idx))>=0) ,
                            heightStyle: "content",
                            beforeActivate:function(event, ui){
                                if(ui.newPanel.text()==''){
                                    //load content for panel to be activated
                                    that._fillSummaryPanel(ui.newPanel);
                                }
                            }
                        });
                        
                        acc.find('.summary-content').removeClass('ui-widget-content');
                    }

                }
            }
        }//!isOpenAready
            
        this._initEditForm_step3(recID); 
    },
    
    //
    //
    //
    closeEditDialog:function(){
        
        //save preferences
        var that = this;
        
        if(this.options.edit_mode=='editonly'){
            
            if(this.options.isdialog){
                this._as_dialog.dialog('close');
            }else{
                window.close(this._currentEditRecordset);
            }
            
        }else if(this._edit_dialog && this._edit_dialog.dialog('isOpen')){
            this._edit_dialog.dialog('close');
        }
    },
    
    //
    // fill one of summary tab panels
    //
    _fillSummaryPanel: function(panel){
        
        var sContent = '';
        var idx = Number(panel.attr('data-id'));
        var that = this;
        
        var ph_gif = window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif';
        
        panel.empty();
        
        //Admin 0, Private 1, Tags 2, Linked 3, Scratchpad 4, Discussion 5
        
        switch(idx){
            case 0:   //admins -------------------------------------------------------
            
                var recRecTypeID = that._getField('rec_RecTypeID');
                sContent =  
'<div style="margin:10px 4px;"><div style="padding-bottom:0.5em;display:inline-block;width: 100%;">'

+'<h2 class="truncate rectypeHeader" style="float:left;max-width:400px;margin-right:8px;">'
                + '<img src="'+ph_gif+'" style="vertical-align:top;margin-right: 10px;background-image:url(\''
                + top.HAPI4.iconBaseURL+recRecTypeID+'\');"/>'
                + window.hWin.HEURIST4.rectypes.names[recRecTypeID]+'</h2>'
+'<select class="rectypeSelect ui-corner-all ui-widget-content" '
+'style="display:none;z-index: 20;position: absolute;border: 1px solid gray;'  //background:white;
+'top: 5.7em;" size="20"></select><div class="btn-modify non-owner-disable"/></div>'

/* this section is moved on top of editForm 2017-12-21
+'<div style="display:inline-block;float:right;">'   
    +'<div class="btn-config2"/><div class="btn-config"/>'  //buttons
    +'<span class="btn-config3" style="cursor:pointer;display:inline-block;float:right;color:#7D9AAA;padding:2px 4px;">Modify structure</span>'
+'</div>'
+'</div>'
*/

+'<div style="padding-bottom:0.5em;width: 100%;">'
+'<div><label class="small-header">Owner:</label><span id="recOwner">'
    +that._getField('rec_OwnerUGrpID')+'</span><div class="btn-access non-owner-disable"/>'        
+'</div>'
+'<div><label class="small-header">Access:</label><span id="recAccess">'
    +that._getField('rec_NonOwnerVisibility')+'</span>'
+'</div></div>'

+'<div>'
+'<div><label class="small-header">Added By:</label><span id="recAddedBy">'+that._getField('rec_AddedByUGrpID')+'</span></div>'
+'<div><label class="small-header">Added:</label>'+that._getField('rec_Added')+'</div>'
+'<div><label class="small-header">Updated:</label>'+that._getField('rec_Modified')+'</div>'
+'</div>'
+'</div>';



                $(sContent).appendTo(panel);
                
                //resolve user id to name
                window.hWin.HAPI4.SystemMgr.usr_names({UGrpID:that._getField('rec_AddedByUGrpID')},
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            panel.find('#recAddedBy').text(response.data[that._getField('rec_AddedByUGrpID')]);
                        }
                });

                
                
                //activate buttons
                /* moved to top of editForm 2017-12-21
                panel.find('.btn-config2').button({text:false,label:top.HR('Modify record type structure in new window'),
                        icons:{primary:'ui-icon-extlink'}})
                    .addClass('ui-heurist-btn-header1')
                    .css({float: 'right','font-size': '0.8em', height: '18px', 'margin-left':'4px'})
                    .click(function(){
                        that.editRecordTypeOnNewTab();
                    });
                    
                panel.find('.btn-config').button({text:false,label:top.HR('Modify record type structure'),
                        icons:{primary:'ui-icon-gear'}})
                    .addClass('ui-heurist-btn-header1')
                    .css({float: 'right','font-size': '0.8em', height: '18px', 'margin-left':'4px'})
                    .click(function(){that.editRecordType();});
                panel.find('.btn-config3').click(function(){that.editRecordType();});
                */
                    
                var btn_change_rt = panel.find('.btn-modify');                        
                btn_change_rt.button({text:false, label:top.HR('Change record type'),
                        icons:{primary:'ui-icon-triangle-1-s'}})
                    //.addClass('ui-heurist-btn-header1')
                    .css({float: 'left','font-size': '0.8em', height: '14px', width: '14px'})
                    .click(function(){
                         var selRt = panel.find('.rectypeSelect');
                         var selHd = panel.find('.rectypeHeader');
                         if(selRt.is(':visible')){
                             btn_change_rt.button('option',{icons:{primary:'ui-icon-triangle-1-s'}});
                             selRt.hide();
                             //selHd.css({'display':'inline-block'});
                             
                         }else{
                             btn_change_rt.button('option',{icons:{primary:'ui-icon-triangle-1-n'}});
                             selRt.show();
                             //
                             if(selRt.is(':empty')){
                                window.hWin.HEURIST4.ui.createRectypeSelect(selRt.get(0), null, null, true);    
                                selRt.change(function(){
                                    
                                    if(that._getField('rec_RecTypeID')!=selRt.val()){
                                                                         
                                    window.hWin.HEURIST4.msg.showMsgDlg(
                                        '<h4>Changing record type</h4><br>'+
                                        'Data will be re-allocated to appropriate fields, where available. '+
                                        'Not all data may fit in the new record structure, but these data '+
                                        'are retained and shown at the end of the form. No data will be lost, '+
                                        'even when the record is saved. Proceed?', 
                                        function() {
                                        
                                              that._editing.assignValuesIntoRecord();
                                              var record = that._currentEditRecordset.getFirstRecord();
                                              that._currentEditRecordset.setFld(record, 'rec_RecTypeID', selRt.val());
                                              that._initEditForm_step4(null); //reload form
                                              
                                              that._editing.setModified(true); //forcefully set to modified after rt change
                                              that.onEditFormChange();
                                              
                                              selHd.html(
                        '<img src="'+ph_gif+'" style="vertical-align:top;margin-right: 10px;background-image:url(\''
                        + top.HAPI4.iconBaseURL+selRt.val()+'\');"/>'
                        + window.hWin.HEURIST4.rectypes.names[selRt.val()]                                      
                                              );
                                                                                      
                                        },
                                        {title:'Warning',yes:'Proceed',no:'Cancel'});
                                    } 
                                    btn_change_rt.button('option',{icons:{primary:'ui-icon-triangle-1-s'}});
                                    //selRt.val(-1);
                                    selRt.hide();
                                    
                                });
                             }
                             //selRt.val(recRecTypeID);
                         }
                        
                         
                    });
                    

                panel.find('.btn-access').button({text:false,label:top.HR('Change ownership and access rights'),
                        icons:{primary:'ui-icon-pencil'}})
                    //.addClass('ui-heurist-btn-header1')
                    .css({float: 'right','margin': '0 0 0.8em 7px', 'font-size': '0.8em', height: '14px', width: '14px'})
                    .click(function(){

        var url = window.hWin.HAPI4.baseURL + 'hclient/framecontent/recordAction.php?db='+window.hWin.HAPI4.database
                +'&action=ownership&owner='+that._getField('rec_OwnerUGrpID')
                +'&scope=noscope&access='+that._getField('rec_NonOwnerVisibility');

        window.hWin.HEURIST4.msg.showDialog(url, {height:300, width:620,
            padding: '0px',
            resizable:false,
            title: window.hWin.HR('ownership'),
            callback: function(context){

                if(context && context.owner && context.access){
                    
                    var ele = that._editing.getFieldByName('rec_OwnerUGrpID');
                    var vals = ele.editing_input('getValues');
                    
                    if(vals[0]!=context.owner){
                        ele.editing_input('setValue',[context.owner]);
                        ele.editing_input('isChanged', true);
                        
                        window.hWin.HAPI4.SystemMgr.usr_names({UGrpID:context.owner},
                            function(response){
                                if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                    panel.find('#recOwner').html(response.data[context.owner]);
                                }
                            });
                    }

                    ele = that._editing.getFieldByName('rec_NonOwnerVisibility');
                    vals = ele.editing_input('getValues');
                    if(vals[0]!=context.access){
                        ele.editing_input('setValue',[context.access]);
                        ele.editing_input('isChanged', true);
                        panel.find('#recAccess').html(context.access);
                    }
                    that.onEditFormChange();
                }
                
            } } );                    //, class:'ui-heurist-bg-light'
                    
                    });
            
            //
            window.hWin.HAPI4.SystemMgr.usr_names({UGrpID:that._getField('rec_OwnerUGrpID')},
                function(response){
                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                        panel.find('#recOwner').text(response.data[that._getField('rec_OwnerUGrpID')]);
                    }
            });
            
  
                break;
                
            case 3:   //find all reverse links
            
                var relations = that._currentEditRecordset.getRelations();    
                var direct = relations.direct;
                var reverse = relations.reverse;
                var headers = relations.headers;
                var ele1=null, ele2=null;
                
                //relations                            
                var sRel_Ids = [];
                for(var k in direct){
                    if(direct[k]['trmID']>0){ //relation    
                        var targetID = direct[k].targetID;
                        sRel_Ids.push(targetID);
                        
                        var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo(panel, 
                            {rec_ID: targetID, 
                             rec_Title: headers[targetID][0], 
                             rec_RecTypeID: headers[targetID][1], 
                             relation_recID: direct[k]['relationID'], 
                             trm_ID: direct[k]['trmID']}, true);
                        if(!ele1) ele1 = ele;     
                    }
                }
                for(var k in reverse){
                    if(reverse[k]['trmID']>0){ //relation    
                        var sourceID = reverse[k].sourceID;
                        sRel_Ids.push(sourceID);
                        
                        var invTermID = window.hWin.HEURIST4.ui.getInverseTermById(reverse[k]['trmID']);
                        
                        var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo(panel, 
                            {rec_ID: sourceID, 
                             rec_Title: headers[sourceID][0], 
                             rec_RecTypeID: headers[sourceID][1], 
                             relation_recID: reverse[k]['relationID'], 
                             trm_ID: invTermID}, true);
                        if(!ele1) ele1 = ele;     
                    }
                }

                var sLink_Ids = [];
                for(var k in reverse){
                    if(!(reverse[k]['trmID']>0)){ //links    
                        var sourceID = reverse[k].sourceID;
                        sLink_Ids.push(sourceID);
                        
                        var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo(panel, 
                            {rec_ID: sourceID, 
                             rec_Title: headers[sourceID][0], 
                             rec_RecTypeID: headers[sourceID][1]}, true);
                        if(!ele2) ele2 = ele;     
                    }
                }
                
                if(sRel_Ids.length>0){
                    $('<div class="detailRowHeader" style="border:none">Related</div>').css('border','none').insertBefore(ele1);
                }
                if(sLink_Ids.length>0){
                    var ee2 = $('<div class="detailRowHeader">Linked from</div>').insertBefore(ele2);
                    if(sRel_Ids.length==0){
                        ee2.css('border','none')
                    }
                }
                if(sRel_Ids.length==0 && sLink_Ids.length==0){
                    $('<div class="detailRowHeader">none</div>').css('border','none').appendTo(panel);
                }
                

                panel.css({'font-size':'0.9em','line-height':'1.5em','overflow':'hidden !important', margin:'10px 0'});
                panel.find('.link-div').css({background:'none',border:'none'});
                            
/*            
                window.hWin.HAPI4.RecordMgr.search_related({ids:this._currentEditID}, //direction: -1}, 
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                    
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
*/                
                break;
                
            case 4:   //scrtachpad
            
                //find field in hEditing
                var ele = that._editing.getFieldByName('rec_ScratchPad');
                ele.editing_input('option',{showclear_button:false, show_header:false});
                ele[0].parentNode.removeChild(ele[0]);                
                ele.css({'display':'block','width':'99%'});
                ele.find('textarea').attr('rows', 10).css('width','90%');
                ele.show().appendTo(panel);
                
                break;
                
            case 1:   //private
            
                if(panel.text()!='') return;
                
                panel.append('<div class="bookmark" style="min-height:2em;padding:4px 30px 4px 0"/>'
                +'<div class="reminders" style="min-height:2em;padding:4px 30px 4px 0"/>');
                
                //find bookmarks and reminders
                that._renderSummaryBookmarks(null, panel);
                that._renderSummaryReminders(null, panel);
            
            
                break;
            case 2:   //tags
            
                if(panel.text()!='') return;
                panel.text('....');
            
                var request = {};
                request['a']          = 'search'; //action
                request['entity']     = 'usrTags';
                request['details']    = 'id';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                request['rtl_RecID']  = this._currentEditID;
                
                //request['DBGSESSID'] = '423997564615200001;d=1,p=0,c=0';

                var that = this;                                                
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            panel.empty();
                            var recs = (response.data && response.data.records)?response.data.records:[];
                            
                            window.hWin.HEURIST4.ui.showEntityDialog('usrTags', {
                                    isdialog: false,
                                    container: panel,
                                    select_mode:'select_multi', 
                                    layout_mode: '<div class="recordList"/>',
                                    list_mode: 'compact', //special option for tags
                                    selection_ids: recs, //already selected tags
                                    select_return_mode:'recordset', //ids by default
                                    onselect:function(event, data){
                                        if(data && data.selection){
                                            that._updated_tags_selection = data.selection;
                                            that.onEditFormChange();
                                        }
                                    }
                            });
                        }
                    });
                
            
                break;
            case 5:   //discussion
                if(panel.text()!='') return;
                
                sContent = '<p>Contact Heurist team if you need this function</p>';
                break;
            case 6:   //dates - moved back to admin section (2017-10-31)
                
 $('<div><label class="small-header">Added By:</label><span id="recAddedBy">'+that._getField('rec_AddedByUGrpID')+'</span></div>'
+'<div><label class="small-header">Added:</label>'+that._getField('rec_Added')+'</div>'
+'<div><label class="small-header">Updated:</label>'+that._getField('rec_Modified')+'</div>').appendTo(panel);

            //resolve user id to name
            window.hWin.HAPI4.SystemMgr.usr_names({UGrpID:that._getField('rec_AddedByUGrpID')},
                function(response){
                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                        panel.find('#recAddedBy').text(response.data[that._getField('rec_AddedByUGrpID')]);
                    }
            });

                break;
            default:
                sContent = '<p>to be implemented</p>';
        }

        if(idx>1 && sContent) $(sContent).appendTo(panel);
        if(idx>0 && idx<6){
            panel.css({'margin-left':'27px'});
        }
        
    },
    
    //
    //
    //
    _renderSummaryReminders: function(recordset, panel){
        
            var that = this, sContent = '',
                pnl = panel.find('.reminders');
                
            pnl.empty().css({'font-size': '0.9em'});
        
            if(recordset==null){
                
                var request = {};
                request['rem_RecID']  = this._currentEditID;
                request['a']          = 'search'; //action
                request['entity']     = 'usrReminders';
                request['details']    = 'name';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                var that = this;                                                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            var recordset = new hRecordSet(response.data);
                            that._renderSummaryReminders(recordset, panel);
                        }
                    });        
      
                return;
            }
        
            if(recordset.length()==0){
                sContent = '<i>no reminders</i>';
            }else{
                
                var rec = recordset.getFirstRecord();
                var val = recordset.fld(rec, 'rem_ToWorkgroupID');
                if(val){
                    sContent = val;
                }else{
                    val = recordset.fld(rec, 'rem_ToUserID');    
                    if(val) {
                        sContent = val;
                    } else{
                        sContent = recordset.fld(rec, 'rem_ToEmail');    
                    }
                }
                val = recordset.fld(rec, 'rem_Message');
                if(val.length>30){
                    val = val.subs(0,30)+'...';
                }
                sContent = 'Reminder to: '+sContent+' '+val;
                //sContent = 'found :'+recordset.length();
            }
            pnl.append(sContent);

            //append/manage button
            $('<div>').button({label:top.HR('Manage reminders'), text:false,
                icons:{primary:'ui-icon-pencil'}})  //ui-icon-mail
                .css({position:'absolute',right:'13px', height: '18px'})
                .addClass('non-owner-disable')
                .click(function(){
                    
                        window.hWin.HEURIST4.ui.showEntityDialog('usrReminders', {
                                edit_mode: 'editonly',
                                rem_RecID: that._currentEditID,
                                onClose:function(){
                                    //refresh
                                    that._renderSummaryReminders(null, panel);
                                }
                        });
                })
             .appendTo(pnl);        
            
    },
    
    //
    //
    //
    _renderSummaryBookmarks: function(recordset, panel){

            var that = this, sContent = '',
                pnl = panel.find('.bookmark');
                
            pnl.empty().css({'font-size': '0.9em'});
        
            if(recordset==null){
                
                var request = {};
                request['bkm_RecID']  = this._currentEditID;
                request['a']          = 'search'; //action
                request['entity']     = 'usrBookmarks';
                request['details']    = 'name';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                var that = this;                                                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            var recordset = new hRecordSet(response.data);
                            that._renderSummaryBookmarks(recordset, panel);
                        }
                    });        
                return;
            }
        
            //rating and password reminder
            if(recordset.length()==0){
                sContent = '<i>not bookmarked (no passwords, rating or notes)</i>';
            }else{
                var rec = recordset.getFirstRecord();
                var val = recordset.fld(rec, 'bkm_Rating');
                sContent = 'Rating: '+(!window.hWin.HEURIST4.util.isempty(val)?val:''); 
                val = recordset.fld(rec, 'bkm_PwdReminder');
                sContent = sContent + '&nbsp;&nbsp;&nbsp;Pwd: '+((!window.hWin.HEURIST4.util.isempty(val))?val:''); 
                val = recordset.fld(rec, 'bkm_Notes');
                sContent = sContent + '<br>Notes: '+((!window.hWin.HEURIST4.util.isempty(val))
                        ?(val.substr(0,500)+(val.length>500?'...':'')):''); 
                
            }
            pnl.append(sContent);
            
            //append/manage button
            $('<div>').button({label:top.HR('Manage bookmark info'), text:false,
                icons:{primary:'ui-icon-pencil'}})  //ui-icon-bookmark
                .addClass('non-owner-disable')
                .css({position:'absolute',right:'13px', height: '18px'})
                .click(function(){
                    
                        window.hWin.HEURIST4.ui.showEntityDialog('usrBookmarks', {
                                bkm_RecID: that._currentEditID,
                                height:400,
                                onClose:function(){
                                    //refresh
                                    that._renderSummaryBookmarks(null, panel);
                                }
                                
                        });
                })
             .appendTo(pnl);        
    },
    //
    // NOT USED anymore TO REMOVE
    //
    _renderSummaryTags: function(recordset, panel){
        
            var that = this, idx, isnone=true;
            
            panel.empty().css({'font-size': '0.9em'});
            
            $('<div><i style="display:inline-block;">Personal:&nbsp;</i></div>')
                .css({'padding':'2px 4px 16px 4px'})
                .attr('data-id', window.hWin.HAPI4.currentUser['ugr_ID'])
                .hide().appendTo(panel);
            
            //render group divs
            for (var groupID in window.hWin.HAPI4.currentUser.ugr_Groups)
            if(groupID>0){
                var name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                if(!window.hWin.HEURIST4.util.isnull(name)){
                        $('<div><i style="display:inline-block;">'+name+':&nbsp;</i></div>')
                            .css({'padding':'0 2 4 2px'})
                            .attr('data-id', groupID).hide().appendTo(panel);
                }
            }
            
            var records = recordset.getRecords();
            var order = recordset.getOrder();
            var recID, label, groupid, record, grp;
            
            for (idx=0;idx<order.length;idx++){
                recID = order[idx];
                if(recID && records[recID]){
                    record = records[recID];
                    label = recordset.fld(record,'tag_Text');
                    groupid = recordset.fld(record,'tag_UGrpID');
                 
                    grp = panel.find('div[data-id='+groupid+']').show();
                    $('<a href="'
                         + window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&q=tag:'+label
                         + '" target="_blank" style="display:inline-block; padding-right:4px">'+label+'</a>')
                         .appendTo(grp);
                   
                   isnone = false;  
                }
            }
            
            
            if(isnone){
                $('<div class="detailRowHeader">none</div>').appendTo(panel);
            }
            
            //append manage button
            $('<div>').button({label:top.HR('Manage record tags'), text:false,
                icons:{primary:'ui-icon-tag'}})
                .addClass('ui-heurist-btn-header1')
                .css({float:'right', height: '18px'})
                .click(function(){
                    
                        /*
                        this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, this.defaultPrefs);
                        this.options['width']  = this.usrPreferences['width'];
                        this.options['height'] = this.usrPreferences['height'];
                        */
                    
                        window.hWin.HEURIST4.ui.showEntityDialog('usrTags', {
                                width: 800,
                                height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95,
                                select_mode:'select_multi', 
                                selection_ids:order,
                                select_return_mode:'recordset', //ids by default
                                onselect:function(event, data){
                                    if(data && data.selection){
                                        //assign new set of tags to record
                                        
                                        var request = {};
                                        request['a']          = 'action'; //batch action
                                        request['entity']     = 'usrTags';
                                        request['tagIDs']  = data.selection.getOrder();
                                        request['recIDs']  = that._currentEditID;
                                        request['request_id'] = window.hWin.HEURIST4.util.random();
                                        
                                        window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                            function(response){
                                                if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                                }
                                            });
                                        //update panel
                                        //that._renderSummaryTags(data.selection, panel);
                                    }
                                }
                        });
                })
             .appendTo(panel);        
                             
    },


    //
    // Open Edit record structure on new tab
    //
    editRecordTypeOnNewTab: function(){

        var that = this;
        
        var smsg = "<p>Changes made to the record type will not become active until you reload this page (hit page reload in your browser).</p>";
        
        if(this._editing.isModified()){
            var smsg = smsg + "<br/>Please SAVE the record first in order not to lose data";
        }
        window.hWin.HEURIST4.msg.showMsgDlg(smsg);

        var url = window.hWin.HAPI4.baseURL + 'admin/adminMenuStandalone.php?db='
            +window.hWin.HAPI4.database
            +'&mode=rectype&rtID='+that._currentEditRecTypeID;
        window.open(url, '_blank');
    },
    
    //
    //
    //
    editRecordType: function(){

        var that = this;
        
        if(this._editing.isModified()){
            
                var sMsg = "Click YES to save changes and modify the record structure.<br><br>"
                            +"If you are unable to save changes, click Cancel and open<br>"
                            +"structure modification in a new tab (button next to clicked one)";
                window.hWin.HEURIST4.msg.showMsgDlg(sMsg, function(){
                    
                        that._saveEditAndClose( null, function(){
                            that._editing.initEditForm(null, null); //clear edit form
                            that._initEditForm_step3(that._currentEditID); //reload edit form                       
                            that.editRecordType();
                        })
                }, {title:'Data have been modified', yes:window.hWin.HR('Yes') ,no:window.hWin.HR('Cancel')});   
                return;                           
        }
        

        var url = window.hWin.HAPI4.baseURL + 'admin/structure/fields/editRecStructure.html?db='+window.hWin.HAPI4.database
            +'&rty_ID='+that._currentEditRecTypeID;

        var body = $(window.hWin.document).find('body');
            
        window.hWin.HEURIST4.msg.showDialog(url, {
            height: body.innerHeight()*0.9,
            width: 860,
            padding: '0px',
            title: window.hWin.HR('Edit record structure'),
            callback: function(context){
                    if(!top.HEURIST.util.isnull(context) && context) {
                        //reload structure definitions w/o message
                        window.hWin.HAPI4.SystemMgr.get_defs_all( false, window.hWin.document, function(){
                            that._initEditForm_step3(that._currentEditID); //reload form    
                        } );
                        
                    }
            }
        });        
        
    },
    
    //
    //
    //
    _initEditForm_step3: function(recID){
        
        //fill with values
        this._currentEditID = recID;
        
        var that = this;
        
        //clear content of accordion
        if(this.editFormSummary && this.editFormSummary.length>0){
            this.editFormSummary.find('.summary-content').empty();
            //this.editFormSummary.accordion({active: false});
        }
        
        if(recID==null){
            this._editing.initEditForm(null, null); //clear and hide
        }else if(recID>0){ //edit existing record
        
            window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+recID, w: "e", f:"complete", l:1}, 
                        function(response){ response.is_insert=false; that._initEditForm_step4(response); });

        }else if(recID<0){ //add new record
        
            if(!that.options.new_record_params) that.options.new_record_params = {};
        
            if(that._currentEditRecTypeID>0){
                that.options.new_record_params['rt'] = that._currentEditRecTypeID;
            }        
            
            that.options.new_record_params['temp'] = 1;
            
            if(!(that.options.new_record_params['rt']>0)){
                
                //record type not defined
                var url = window.hWin.HAPI4.baseURL + 'hclient/framecontent/recordAction.php?db='
                        + window.hWin.HAPI4.database
                        + '&action=add_record&scope=popup';

                window.hWin.HEURIST4.msg.showDialog(url, {height:500, width:700,
                    padding: '0px',
                    //resizable:false,
                    title: window.hWin.HR('add_record'),
                    callback: function(context){
                        if(context && context.rt>0){

                            that._currentEditRecTypeID = context.rt;
                            that.options.new_record_params =  context;
                                                
                            window.hWin.HAPI4.RecordMgr.add( that.options.new_record_params,
                                    function(response){  response.is_insert=true; that._initEditForm_step4(response); });
                            
                        }else{
                             that.closeDialog();
                        }
                    }
                });

                /*
                //select record type first
                if(!this._rt_select_dialog){
                    this._rt_select_dialog = $('<div>').css({'text-align': 'center'}).appendTo(this.element);
                    var selRt = $('<select>').addClass('text ui-corner-all ui-widget-content').appendTo(this._rt_select_dialog);
                    window.hWin.HEURIST4.ui.createRectypeSelect(selRt.get(0), null, null, true);    
                }
                
                var $dlg, btns = [
                {text:window.hWin.HR('Add Record'),
                    click: function(){  
                                    
                            that._currentEditRecTypeID = that._rt_select_dialog.find('select').val();
                            that.options.new_record_params['rt'] = that._currentEditRecTypeID;
                                                
                            window.hWin.HAPI4.RecordMgr.add( that.options.new_record_params,
                                    function(response){  response.is_insert=true; that._initEditForm_step4(response); });
                                            
                            $dlg.dialog('close');
                                                    
                    } },
                {text:window.hWin.HR('Cancel'),
                                click:function(){
                                      $dlg.dialog('close');
                                      that.closeDialog();
                                } } ];
                       
                $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({
                        window:  window.hWin, //opener is top most heurist window
                        element:  this._rt_select_dialog[0],
                        height: 120,
                        width:  400,
                        resizable: false,
                        title: window.hWin.HR('Select record type for new record'),                         
                        buttons: btns
                    });
                */       
               
            }else{
                //this._currentEditRecTypeID is set in add button
                window.hWin.HAPI4.RecordMgr.add( that.options.new_record_params,
                        function(response){ response.is_insert=true; that._initEditForm_step4(response); });
            }
        }

        return;
    },
    
    //
    // apparently it should be moved to ui?
    //
    __findParentRecordTypes: function(childRecordType){

        var parentRecordTypes = []; //result
        
        var fieldIndexMap = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;
        //var dtyFieldNamesIndexMap = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex;
        
        childRecordType = ''+childRecordType; //must be strig otherwise indexOf fails
        
        var rtyID, dtyID, allrectypes = window.hWin.HEURIST4.rectypes.typedefs;
        for (rtyID in allrectypes)
        if(rtyID>0){

            var fields = allrectypes[rtyID].dtFields;
            
            for (dtyID in fields)
            if(dtyID>0 && 
                fields[dtyID][fieldIndexMap['dty_Type']]=='resource' &&
                fields[dtyID][fieldIndexMap['rst_CreateChildIfRecPtr']]==1)
            { //for all fields in this rectype
                var ptrIds = fields[dtyID][fieldIndexMap['rst_PtrFilteredIDs']];
                if(ptrIds.split(',').indexOf(childRecordType)>=0){
                    parentRecordTypes.push(rtyID);
                    break;
                }
            }
        }
        
        return parentRecordTypes;
    },
    
    //
    // get record field structure. It needs to addition of non-standard fields
    //
    _getFakeRectypeField: function(detailTypeID, order){
        
        var dt = window.hWin.HEURIST4.detailtypes.typedefs[detailTypeID]['commonFields'];
        
        var fieldIndexMap = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;
        var dtyFieldNamesIndexMap = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex;
           
        //init array 
        var ffr = [];
        var l = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNames.length;
        var i;
        for (i=0; i<l; i++){ffr.push("");}
            
        ffr[fieldIndexMap['rst_DisplayName']] = dt?dt[dtyFieldNamesIndexMap['dty_Name']]:'Fake field';
        ffr[fieldIndexMap['dty_FieldSetRectypeID']] = dt?dt[dtyFieldNamesIndexMap['dty_FieldSetRectypeID']] : 0;
        ffr[fieldIndexMap['dty_TermIDTreeNonSelectableIDs']] = (dt?dt[dtyFieldNamesIndexMap['dty_TermIDTreeNonSelectableIDs']]:"");
        ffr[fieldIndexMap['rst_TermIDTreeNonSelectableIDs']] = (dt?dt[dtyFieldNamesIndexMap['dty_TermIDTreeNonSelectableIDs']]:"");
        ffr[fieldIndexMap['rst_MaxValues']] = 1;
        ffr[fieldIndexMap['rst_MinValues']] = 0;
        ffr[fieldIndexMap['rst_CalcFunctionID']] = null;
        ffr[fieldIndexMap['rst_DefaultValue']] = null;
        ffr[fieldIndexMap['rst_DisplayDetailTypeGroupID']] = (dt?dt[dtyFieldNamesIndexMap['dty_DetailTypeGroupID']]:"");
        ffr[fieldIndexMap['rst_DisplayExtendedDescription']] = (dt?dt[dtyFieldNamesIndexMap['dty_ExtendedDescription']]:"");
        ffr[fieldIndexMap['rst_DisplayHelpText']] = (dt?dt[dtyFieldNamesIndexMap['dty_HelpText']]:"");
        ffr[fieldIndexMap['rst_DisplayOrder']] = (order>0)?order:999;
        ffr[fieldIndexMap['rst_DisplayWidth']] = 50;
        ffr[fieldIndexMap['rst_FilteredJsonTermIDTree']] = (dt?dt[dtyFieldNamesIndexMap['dty_JsonTermIDTree']]:"");
        ffr[fieldIndexMap['rst_LocallyModified']] = 0;
        ffr[fieldIndexMap['rst_Modified']] = 0;
        ffr[fieldIndexMap['rst_NonOwnerVisibility']] = (dt?dt[dtyFieldNamesIndexMap['dty_NonOwnerVisibility']]:"viewable");
        ffr[fieldIndexMap['rst_OrderForThumbnailGeneration']] = 0;
        ffr[fieldIndexMap['rst_OriginatingDBID']] = 0;
        ffr[fieldIndexMap['rst_PtrFilteredIDs']] = (dt?dt[dtyFieldNamesIndexMap['dty_PtrTargetRectypeIDs']]:"");
        ffr[fieldIndexMap['rst_CreateChildIfRecPtr']] = 0;
        ffr[fieldIndexMap['rst_RecordMatchOrder']] = 0;
        ffr[fieldIndexMap['rst_RequirementType']] = 'optional';
        ffr[fieldIndexMap['rst_Status']] = (dt?dt[dtyFieldNamesIndexMap['dty_Status']]:"open");
        ffr[fieldIndexMap['dty_Type']] = (dt?dt[dtyFieldNamesIndexMap['dty_Type']]:"freetext");
        
        ffr['dt_ID'] = detailTypeID;
        
        return ffr;
    },
    
    //
    // prepare fields and init editing
    //
    _initEditForm_step4: function(response){
        
        var that = this;
        
        if(response==null || response.status == window.hWin.HAPI4.ResponseStatus.OK){
            
            //response==null means reload/refresh edit form
            
            if(response){ // && response.length()>0
                that._currentEditRecordset = new hRecordSet(response.data);
                that._isInsert = response.is_insert; 
            }
            
            var rectypeID = that._getField('rec_RecTypeID');
            var rectypes = window.hWin.HEURIST4.rectypes;
            var rfrs = rectypes.typedefs[rectypeID].dtFields;
            
            //pass structure and record details
            that._currentEditID = that._getField('rec_ID');;
            that._currentEditRecTypeID = rectypeID;
            
            var dialog_title = this.options['edit_title'];
            if(!dialog_title){
                 
                 dialog_title = window.hWin.HR(that._isInsert ?'Add':'Edit') + ' '
                                    + window.hWin.HEURIST4.rectypes.names[rectypeID];                         
                                    
            }
            
            if(this.options.edit_mode=='popup' && this._edit_dialog){
                that._edit_dialog.dialog('option','title', dialog_title); 
            }else if(this.options.edit_mode=='editonly' && this._as_dialog){
                that._as_dialog.dialog('option','title', dialog_title); 
            }
                
             
            
            
            //@todo - move it inside editing
            //convert structure - 
            var fields = window.hWin.HEURIST4.util.cloneJSON(that.options.entity.fields);
            var fieldNames = rectypes.typedefs.dtFieldNames;
            var fi = rectypes.typedefs.dtFieldNamesToIndex;

            /*
            function __findFieldIdxById(id){
                for(var k in fields){
                    if(fields[k]['dtID']==id){
                        return k;
                    }
                }
                return -1;
            }
            //hide url field
            var fi_url = rectypes.typedefs.commonNamesToIndex['rty_ShowURLOnEditForm'];
            if(rectypes.typedefs[rectypeID].commonFields[fi_url]=='0'){
                fields[__findFieldIdxById('rec_URL')]['rst_Visible'] = false;
            }
            */
            
            var fi_type = fi['dty_Type'],
                fi_name = fi['rst_DisplayName'],
                fi_order = fi['rst_DisplayOrder'],
                fi_defval = fi['rst_DefaultValue'],
                fi_help =  fi['rst_DisplayHelpText'],
                fi_reqtype =  fi['rst_RequirementType'],
                fi_ptrs = fi['rst_PtrFilteredIDs'],
                fi_maxval = fi['rst_MaxValues']; //need for proper repeat
            
            var s_fields = []; //sorted fields
            var fields_ids = [];
            for(var dt_ID in rfrs){ //in rt structure
                if(dt_ID>0){
                    rfrs[dt_ID]['dt_ID'] = dt_ID;
                    s_fields.push(rfrs[dt_ID]);
                    fields_ids.push(Number(dt_ID));  //fields in structure
                }
            }

            //add non-standard fields that are not in structure
            var field_in_recset = that._currentEditRecordset.getDetailsFieldTypes();

            //add special 2-247 field "Parent Entity"
            //verify that current record type is a child for pointer fields with rst_CreateChildIfRecPtr=1
            var parentsIds = that.__findParentRecordTypes(rectypeID);
            
            var DT_PARENT_ENTITY  = Number(window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY']);
            if(field_in_recset.indexOf(DT_PARENT_ENTITY)<0 && 
                    (this.options.parententity>0 ||   //parent record id is set already (case: this is addition of new child from search record dialog)
                    (parentsIds.length>0 && that._isInsert) ))   //current rectype is referenced as a child and this is ADDITION
            {
                    field_in_recset.push(DT_PARENT_ENTITY);
            }
            
            var addhead = 0;
            for(var k=0; k<field_in_recset.length; k++){
                if(fields_ids.indexOf(field_in_recset[k])<0){ //field in recset is not in structure
                    if(field_in_recset[k]==DT_PARENT_ENTITY){

                        var rfr = that._getFakeRectypeField(DT_PARENT_ENTITY);
                        rfr[fi_name] = 'Child record of';
                        rfr[fi_order] = -1;//top most
                        rfr[fi_reqtype] = 'required';
                        if(this.options.parententity>0){
                            rfr[fi_defval] = this.options.parententity;  //parent Record ID
                            rfr[fieldNames.length] = 'readonly';
                        }
                        if(parentsIds.length>0){
                           rfr[fi_ptrs] = parentsIds; //constrained to parent record types
                           
                           //if the only value readonly as well
                           if(!that._isInsert){
                                record = that._currentEditRecordset.getById(that._currentEditID);
                                var values = that._currentEditRecordset.values(record, DT_PARENT_ENTITY);
                                if(values && values.length==1){
                                    rfr[fieldNames.length] = 'readonly';   
                                }
                           }  
                        }
                        
                        fieldNames.push('rst_Display');
                        s_fields.push(rfr);
                        
                    }else{
                    
                        if(addhead==0){                    
                            var rfr = that._getFakeRectypeField(1);
                            rfr[fi_name] = 'Non-standard fields for this record type';
                            rfr[fi_type] = 'separator';
                            s_fields.push(rfr);
                            addhead++;
                        }
                        
                        var rfr = that._getFakeRectypeField(field_in_recset[k], 999+addhead);
                        s_fields.push(rfr);
                    }
                }
            }//for           

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
                        groupHelpText: rfr[fi_help],
                        groupTitleVisible: (rfr[fi_reqtype]!=='forbidden'),
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
            }//for s_fields
            //add children to last group
            if(group_fields!=null){
                fields[fields.length-1].children = group_fields;
            }
            
            that._editing.initEditForm(fields, that._currentEditRecordset);
            
            that._afterInitEditForm();

            if(this._keepYPos>0){
                this.editForm.scrollTop(this._keepYPos);
                this._keepYPos = 0;
            }
            
            //show rec_URL 
            var fi_url = rectypes.typedefs.commonNamesToIndex['rty_ShowURLOnEditForm'];
            if(rectypes.typedefs[rectypeID].commonFields[fi_url]=='1'){
                var ele = that._editing.getFieldByName('rec_URL');
                ele.show();
            }
            
            if(that.editFormSummary && that.editFormSummary.length>0){
                /*@todo that.editFormSummary.accordion({
                    active:0    
                });*/
                //@todo restore previous accodion state
                that.editFormSummary.find('.summary-accordion').each(function(idx,item){
                    var active = $(item).accordion('option','active');
                    if(active!==false){
                        $(item).accordion({active:0});
                        if($(item).find('.summary-content').is(':empty'))
                            that._fillSummaryPanel($(item).find('.summary-content'));
                    }
                            
                });
                
            }
            
            //show coverall to prevnt edit
            //1. No enough premission
            var no_access = that._getField('rec_OwnerUGrpID')>0 &&  //0 is everyone
                            !window.hWin.HAPI4.has_access(that._getField('rec_OwnerUGrpID'));
                            //!window.hWin.HAPI4.is_admin()
            
            //2. Popup for resource field
            var dlged = that._getEditDialog();
            if(dlged && (no_access || this.options.edit_obstacle)){ 
                
                var ele = $('<div><div class="edit-button" style="background:#f48642 !important;margin: 40px auto;width:200px;padding:10px;border-radius:4px;">'
                            +'<h2 style="display:inline-block;color:white">View-only mode</h2>&nbsp;&nbsp;'
                            +'<a href="#" style="color:white">edit</a><span>No enough rights</span></div></div>')
                       .addClass('coverall-div-bare')
                       .css({top:'30px', 'text-align':'center','zIndex':9999999999, height:'auto'}) //, bottom: '40px', 'background':'red'
                       .appendTo(dlged);
                
                var eles = dlged.find('.ui-layout-center > div');       
                if(no_access){
                    eles.css({'background-image': 'url('+window.hWin.HAPI4.baseURL+'hclient/assets/non-editable-watermark.png)'});                    
                }else{
                    dlged.find('.ui-layout-center').css({'background':'lightgray'});    
                    //eles.css({'background':'lightgray'});    
                }
                that._editing.setDisabled(true);
                
                eles = dlged.find('.ui-layout-east > .editFormSummary');
                if(no_access){
                    eles.css({'background-image': 'url('+window.hWin.HAPI4.baseURL+'hclient/assets/non-editable-watermark.png)'});                    
                }else{
                    eles.css({'background':'lightgray'});    
                }
                //screen for summary
                $('<div>').addClass('coverall-div-bare')
                    .css({top:0,height:'100%',left:35,right:0,'zIndex':9999999999})
                    .appendTo(eles);
                    
                       
                if(no_access){
                    ele.find('a').hide();
                }else{       
                    //find('a')
                    ele.find('.edit-button').button().click(function(){
                        ele.remove();
                        //restore edit ability 
                        that._editing.setDisabled(false);
                        dlged.find('.ui-layout-center > div').css({'background':'none'});
                        dlged.find('.ui-layout-center').css({'background':'none'});
                        var eles = dlged.find('.ui-layout-east > .editFormSummary')
                        eles.css({'background':'none'});
                        //remove screen
                        eles.find('.coverall-div-bare').remove();
                    });
                    ele.find('span').hide(); //how no enough rights
                }
                this.options.edit_obstacle = false;
            } 
            
            
        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }
    },        
    

    
    
    //  -----------------------------------------------------
    //  OVERRIDE
    //  send update request and close popup if edit is in dialog
    //  afteraction - none, close, newrecord or callback function
    //
    _saveEditAndClose: function( fields, afterAction ){

            if(!(this._currentEditID>0)) return;
        
            if(!fields){
                try{
                    fields = this._getValidatedValues(); 
                }catch(e){
                    fields = null;
                }
            }
            
            if(fields==null) return; //validation failed

            var that = this;                                    

            //assign new set of tags to record
            if($.isArray(that._updated_tags_selection)){
                var request2 = {};
                request2['a']          = 'action'; //batch action
                request2['entity']     = 'usrTags';
                request2['tagIDs']  = that._updated_tags_selection;
                request2['recIDs']  = that._currentEditID;
                that._updated_tags_selection = null;
                
                window.hWin.HAPI4.EntityMgr.doRequest(request2, 
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            
                            that._saveEditAndClose( fields, afterAction );
                        }
                    });
                return;
            }


       
            var request = {ID: this._currentEditID, 
                           RecTypeID: this._currentEditRecTypeID, 
                           URL: fields['rec_URL'],
                           OwnerUGrpID: fields['rec_OwnerUGrpID'],
                           NonOwnerVisibility: fields['rec_NonOwnerVisibility'],
                           ScratchPad: fields['rec_ScratchPad'],
                           'details': fields};
        
            if(fields['no_validation']){
                request['no_validation'] = 1;
            }
        
            //keep current overflow position
            this._keepYPos = this.editForm.scrollTop();
        
            that.onEditFormChange(true); //forcefully hide all "save" buttons
            
            var dlged = that._getEditDialog();
            if(dlged) window.hWin.HEURIST4.msg.bringCoverallToFront(dlged);
            
            window.hWin.HAPI4.RecordMgr.save(request, 
                    function(response){
                        
                        window.hWin.HEURIST4.msg.sendCoverallToBack();
                        
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            
                            that._editing.setModified(false); //reset modified flag after save
                            
                            //var recID = ''+response.data[0];
                            var rec_Title = response.rec_Title;
                            
                            //that._afterSaveEventHandler( recID, fields);
                            //
                            
                            if($.isFunction(afterAction)){
                               
                               afterAction.call(); 
                                
                            }else if(afterAction=='close'){

                                that._currentEditRecordset.setFld(
                                        that._currentEditRecordset.getFirstRecord(),'rec_Title',rec_Title);
                                
                                that._currentEditID = null;
                                if(that.options.selectOnSave==true){
                                    that.selectedRecords(that._currentEditRecordset);
                                    that._selectAndClose();
                                }else{
                                    that._currentEditID = null;
                                    that.closeEditDialog();            
                                }
                                
                            }else if(afterAction=='newrecord'){
                                that._initEditForm_step3(-1);
                            }else{
                                //reload after save
                                that._initEditForm_step3(that._currentEditID)
                            }
                            
                            window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Record has been saved'));
                            
                        }else{
                            that.onEditFormChange(); //restore save buttons visibility
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
    },   
    
    //
    //
    //
    onEditFormChange:function(changed_element){
        
        var force_hide = (changed_element===true);
        
        var mode = 'hidden';
        if(force_hide!==true){
            var isChanged = this._editing.isModified() || this._updated_tags_selection!=null;
            mode = isChanged?'visible':'hidden';
            
            if(isChanged && changed_element){
                //if this is parent-child pointer AUTOSAVE
                var parententity = changed_element.f('rst_CreateChildIfRecPtr');                
                if(parententity==1){
                    //get values without validation
                    var fields = this._editing.getValues(false);
                    var that = this;
                    fields['no_validation'] = 1; //do not validate required fields
                    this._saveEditAndClose( fields, function(){
                        that._editing.setModified(true); //restore flag after autosave
                        that.onEditFormChange();
                    });
                    return;                    
                }
            }
        }
        
        //show/hide save buttons
        var ele = this._toolbar;
        /*ele.find('#btnRecCancel').css('visibility', mode);
        ele.find('#btnRecSaveAndNew').css('visibility', mode);
        ele.find('#btnRecSave').css('visibility', mode);
        ele.find('#btnRecSaveAndClose').css('visibility', mode);*/
        
        //window.hWin.HEURIST4.util.setDisabled(ele.find('#btnRecDuplicate'), (mode=='hidden'));
        //window.hWin.HEURIST4.util.setDisabled(ele.find('#btnRecSaveAndNew'), (mode=='hidden'));
        
        window.hWin.HEURIST4.util.setDisabled(ele.find('#btnRecCancel'), (mode=='hidden'));
        window.hWin.HEURIST4.util.setDisabled(ele.find('#btnRecSaveAndClose'), (mode=='hidden'));
        
        //window.hWin.HEURIST4.util.setDisabled(ele.find('#btnRecSave'), (mode=='hidden'));
        
        //save buton is always enabled - just greyout in nonchanged state
        if(mode=='hidden'){
            ele.find('#btnRecSave').css({opacity: '.35'});  //addClass('ui-state-disabled'); 
        }else{
            ele.find('#btnRecSave').css({opacity: '1'}); //.removeClass('ui-state-disabled'); // ui-button-disabled
        }
        
        
        //ele.find('#btnRecReload').css('visibility', !mode);
    },
    
    //
    //
    //    
    _afterInitEditForm: function(){
        
        var ishelp_on = (this.usrPreferences['help_on']==true || this.usrPreferences['help_on']=='true');
        var isfields_on = this.usrPreferences['optfields']==true || this.usrPreferences['optfields']=='true';
        
        //show-hide optional fields     
        $(this.element).find('div.optional').parent().css({'display': (isfields_on?'table':'none')} ); 
        $(this.element).find('div.optional_hint').css({'display': (isfields_on?'none':'block')} ); 

        $(this.element).find('div.forbidden').parent().css({'display':'none'} ); 

        
        if(this.element.find('.chb_opt_fields').length==0){  //not inited yet
            
            $('<div style="display:inline-block;text-align:left;padding:10px 0px 5px 35px;">'
                    +'<label><input type="checkbox" class="chb_show_help" '
                        +(ishelp_on?'checked':'')+'/>Show help</label><span style="display:inline-block;width:40px"></span>'
                    +'<label><input type="checkbox" class="chb_opt_fields" '
                        +(isfields_on?'checked':'')+'/>Optional fields</label>'
                    +'<hr style="margin-top: 10px;width:250px">'
             +'</div>'
             +'<div style="float:right;padding: 20px 40px 0px 0px;font-weight: bold;">'
                +'<span class="btn-config4" style="cursor:pointer;color:#7D9AAA;padding:2px 0 20px 10px">Modify structure</span>'
                +'<span class="btn-config5 ui-icon ui-icon-gear smallicon"></span>'
             +'</div>').insertBefore(this.editForm.first('fieldset'));



                
            var that = this;    
            
            if(window.hWin.HAPI4.is_admin()){
                this.element.find('.btn-config5')
                        .css({height: '18px', 'margin-left':'4px'})
                        .click(function(){that.editRecordType();});
                this.element.find('.btn-config4').click(function(){that.editRecordType();});
            }else{
                this.element.find('.btn-config5').hide();
                this.element.find('.btn-config4').hide();
            }
            
                
                
            this.element.find('.chb_show_help') //.attr('checked', ishelp_on)
                        .change(function( event){
                            var ishelp_on = $(event.target).is(':checked');
                            that.usrPreferences['help_on'] = ishelp_on;
                            window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, $(that.element));
                        });
            
            
            this.element.find('.chb_opt_fields') //.attr('checked', isfields_on)
                        .change(function( event){
                            var isfields_on = $(event.target).is(':checked');
                            that.usrPreferences['optfields'] = isfields_on;
                            $(that.element).find('div.optional').parent().css({'display': (isfields_on?'table':'none')} ); 
                            $(that.element).find('div.optional_hint').css({'display': (isfields_on?'none':'block')} ); 
                        });
        }
        
        //add record title at the top ======================
        
        if(this.editHeader && this.editHeader.length>0){ 

            this.editHeader.find('.ui-heurist-header2').remove();
            
            //define header - rectype icon, retype name and record title
            var ph_gif = window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif';
            var sheader = '<div class="ui-heurist-header2" style="text-align:left">'
                    + '<img src="'+ph_gif
                        + '" width=25 height=25 style="vertical-align:middle;margin-right: 10px;background-image:url(\''
                        + top.HAPI4.iconBaseURL+this._currentEditRecTypeID
                        + 'm&color=rgb(255,255,255)\');"/><span style="display:inline-block;vertical-align:middle">'
                        + window.hWin.HEURIST4.rectypes.names[this._currentEditRecTypeID]                         
                    + '</span>';
                    
            if(!this._isInsert){
                sheader = sheader + 
                    '&nbsp;<span style="display:inline-block;padding:0 20px;vertical-align:middle">ID: '+this._currentEditID
                    + '</span><h3 style="display:inline-block;max-width:900;vertical-align:middle" class="truncate">'
                    + this._getField('rec_Title')+'</h3>';            
                
            
            }
            sheader = sheader + '</div>';
            $(sheader).appendTo(this.editHeader);
            
            if(!this._as_dialog){
                this.element.addClass('manageRecords');                
                window.hWin.HEURIST4.ui.initDialogHintButtons(this.element, 
                    '.ui-heurist-header2', //where to put button
                    window.hWin.HAPI4.baseURL+'context_help/'+this.options.entity.helpContent+' #content');
            }
        }

        //need refresh layout to init overflows(scrollbars)        
        if(this.editFormSummary && this.editFormSummary.length>0){
             this.editFormPopup.layout().resizeAll();
        }
        
        if(this.element.find('.btn-modify_structure').length>0){
            var that = this;
            this.element.find('.btn-modify_structure').click(function(){that.editRecordType();});
        }


        $(this.element).find('div.optional').parent().css({'display': (isfields_on?'table':'none')} ); 
        $(this.element).find('div.optional_hint').css({'display': (isfields_on?'none':'block')} ); 

        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, this.editForm);
        //show-hide help text below fields - it overrides comptency level
        window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, $(this.element));
        
        this.onEditFormChange();
    },

    
    //
    //
    //
    getUiPreferences: function(){
        this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, this.defaultPrefs);
        if(this.usrPreferences.width<600) this.usrPreferences.width=600;
        if(this.usrPreferences.height<300) this.usrPreferences.height=300;
        if (this.usrPreferences.width>this.defaultPrefs.width) this.usrPreferences.width=this.defaultPrefs.width;
        if (this.usrPreferences.height>this.defaultPrefs.height) this.usrPreferences.height=this.defaultPrefs.height;
        return this.usrPreferences;
    },
    
    //
    // save width,heigth and summary tab prefs
    //
    saveUiPreferences: function(){
        
        var that = this;
        
        var dwidth = this.defaultPrefs['width'],
            dheight = this.defaultPrefs['height'],
            isClosed = false,
            activeTabs = [],
            sz = 400,
            help_on = true,
            optfields = true;
            
        var params = this.getUiPreferences();    
            
        if(that.editFormSummary && that.editFormSummary.length>0){
            
                that.editFormSummary.find('.summary-accordion').each(function(idx,item){
                    var active = $(item).accordion('option','active');
                    if(active!==false){
                        activeTabs.push(String(idx));
                    }
                            
                });

                var myLayout = that.editFormPopup.layout();                
                sz = myLayout.state.east.size;
                isClosed = myLayout.state.east.isClosed;
                
        }
        help_on = that.element.find('.chb_show_help').is(':checked');
        optfields = that.element.find('.chb_opt_fields').is(':checked');
            
        
        if(this.options.edit_mode=='editonly'){
            if(that.options.isdialog){
                dwidth  = that._as_dialog.dialog('option','width');
                dheight = that._as_dialog.dialog('option','height');
                
                var cnt = $('div.ui-dialog[posid^="edit'+this._entityName+'"]').length;
                if(cnt==1){ //save position
                    var dlged = that._as_dialog.parent('.ui-dialog');
                    params['top'] = parseInt(dlged.css('top'),10);
                    params['left'] = parseInt(dlged.css('left'), 10);
                }
                
            }else{
                //dwidth  = window.innerWidth+20;
                //dheight = window.innerHeight+46;
            }      
                  
        }else                
        if(that._edit_dialog && that._edit_dialog.dialog('isOpen')){
            dwidth  = that._edit_dialog.dialog('option','width'); 
            dheight = that._edit_dialog.dialog('option','height');
        } 

        params.width = dwidth;
        params.height = dheight;
        params.help_on = help_on;
        params.optfields = optfields;
        params.summary_closed = isClosed;
        params.summary_width = sz;
        params.summary_tabs = activeTabs;

        window.hWin.HAPI4.save_pref('prefs_'+this._entityName, params);
        
        return true;
    }
    
    
});
