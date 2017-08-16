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

    usrPreferences:{},
    defaultPrefs:{
        width:(window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
        height:(window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95,
        help_on:true, 
        optfields:true, 
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

                            + '<div class="editFormDialog ent_wrapper editor">'
                                    + '<div class="ui-layout-center"><div class="editForm"/></div>'
                                    + '<div class="ui-layout-east"><div class="editFormSummary">empty</div></div>'
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

        this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, this.defaultPrefs);
        
        
        this._super();
        
        //this.editForm.empty();
        
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
        this.recordList.css('top','7em');
        if(hasSearchForm){
            this.searchForm.height('9.5em').css('border','none');    
        }
        
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
            this.searchForm.searchRecords(this.options);    
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
                this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, this.defaultPrefs);
                this.options['width']  = this.usrPreferences['width'];
                this.options['height'] = this.usrPreferences['height'];
                if(this.options['width']<600) this.options['width']=600;
                if(this.options['height']<300) this.options['height']=300;
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
                    this._toolbar.find('#divNav').html( (idx+1)+' of '+order.length);
                    if(dest!=0){
                        this.addEditRecord(order[idx]);
                    }
                }
        }
    },    
    //override some editing methods
    
    _getEditDialogButtons: function(){
                                    
            var that = this;        
            return [       /*{text:window.hWin.HR('Reload'), id:'btnRecReload',icons:{primary:'ui-icon-refresh'},
                        click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form*/
                              
                        {text:window.hWin.HR('Duplicate'), id:'btnRecDuplicate',
                                click: function(event) { 
                                    var btn = $(event.target);
                                    btn.hide();
                                    window.hWin.HAPI4.RecordMgr.duplicate({id: that._currentEditID}, 
                                        function(response){
                                            btn.css('display','inline-block');
                                            if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                                window.hWin.HEURIST4.msg.showMsgFlash(
                                                    window.hWin.HR('Record has been duplicated'));
                                                var new_recID = ''+response.data.added;
                                                that._initEditForm_step3(new_recID);
                                            }else{
                                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                            }
                                        }); 
                                }},
                        {text:window.hWin.HR('Save + new'), id:'btnRecSaveAndNew',
                              css:{'visibility':'hidden', 'margin-right':'60px'},
                              click: function() { that._saveEditAndClose( null, 'newrecord' ); }},
                                
                        {text:window.hWin.HR('Previous'),icons:{primary:'ui-icon-circle-triangle-w'},
                              css:{'display':'none'}, id:'btnPrev',
                              click: function() { that._navigateToRec(-1); }},
                        {text:window.hWin.HR('Next'),icons:{secondary:'ui-icon-circle-triangle-e'},
                              css:{'display':'none','margin-right':'60px'}, id:'btnNext',
                              click: function() { that._navigateToRec(1); }},
                              
                        {text:window.hWin.HR('Cancel'), id:'btnRecCancel', 
                              css:{'visibility':'hidden','margin-right':'15px'},
                              click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form
                       
                        {text:window.hWin.HR('Save'), id:'btnRecSave',
                              css:{'visibility':'hidden','margin-right':'5px'},
                              click: function() { that._saveEditAndClose( null, 'none' ); }},
                        {text:window.hWin.HR('Save / Close'), id:'btnRecSaveAndClose',
                              css:{'visibility':'hidden','margin-right':'15px'},
                              click: function() { that._saveEditAndClose( null, 'close' ); }},
                        {text:window.hWin.HR('Close'), 
                              click: function() { 
                                  that.closeEditDialog(); 
                              }}]; 
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
                        title: this.options['edit_title']
                                    ?this.options['edit_title']
                                    :window.hWin.HR('Edit') + ' ' +  this.options.entity.entityName,                         
                        buttons: this._getEditDialogButtons(),
                        beforeClose: this.options.beforeClose
                    });
                
                //help and tips buttons on dialog header
                window.hWin.HEURIST4.ui.initDialogHintButtons(this._edit_dialog,
                    'prefs_'+this._entityName,
                    window.hWin.HAPI4.baseURL+'context_help/'+this.options.entity.helpContent+' #content');
        
                this._toolbar = this._edit_dialog.parent(); //this.editFormPopup.parent();
        
            }//popup
            else { //initialize action buttons
                
                // in_popup_dialog - it means that list is not visible 
                // we works with edit part only
                
                if(this.options.edit_mode=='editonly'){
                    //this is popup dialog
                   this.editFormToolbar
                     .addClass('ui-dialog-buttonpane')
                     .css({
                        padding: '0.8em 1em .2em .4em',
                        background: 'none',
                        'background-color': '#95A7B7 !important',
                        'text-align':'right'
                     });
                }
                
                if(this.editFormToolbar && this.editFormToolbar.length>0){
                    
                    var btn_array = this._getEditDialogButtons();
                    if(this.options.edit_mode=='editonly' && this.options.in_popup_dialog!==false){
                        //this is standalone window
                        btn_array.pop();btn_array.pop(); //remove two last buttons about close edit form
                    }
                    
                    this._toolbar = this.editFormToolbar;
                    this.editFormToolbar.empty();
                    for(var idx in btn_array){
                        this._defineActionButton2(btn_array[idx], this.editFormToolbar);
                    }
                }
            }

            
            var recset = this.recordList.resultList('getRecordSet');
            if(recset && recset.length()>1 && recID>0){
                this._toolbar.find('#btnPrev').css({'display':'inline-block'});
                this._toolbar.find('#btnNext').css({'display':'inline-block'});
                if(this._toolbar.find('#divNav').length===0){
                    $('<div id="divNav" style="min-width:40px;padding:0 1em;display:inline-block;text-align:center">')
                        .insertAfter(this._toolbar.find('#btnPrev'));
                }
                this._navigateToRec(0);
            }else{
                this._toolbar.find('#btnPrev').hide();
                this._toolbar.find('#btnNext').hide();
                this._toolbar.find('#divNav').hide();
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
                        minWidth:800,
                        contentSelector: '.editForm'    
                    }

                };

                this.editFormPopup.show().layout(layout_opts); //.addClass('ui-heurist-bg-light')
                if(!this.usrPreferences.summary_tabs) this.usrPreferences.summary_tabs = ['0','1'];

                //load content for editFormSummary
                if(this.editFormSummary.text()=='empty'){
                    this.editFormSummary.empty();
                    var headers = ['Admin','Linked records','Scratchpad','Private','Tags','Discussion','Dates']; //'Text',
                    for(var idx in headers){
                        var acc = $('<div>').addClass('summary-accordion').appendTo(this.editFormSummary);
                        
                        $('<h3>').text(top.HR(headers[idx])).appendTo(acc);
                        //content
                        $('<div>').attr('data-id', idx).addClass('summary-content').appendTo(acc);
                        
                        acc.accordion({
                            collapsible: true,
                            active: (this.usrPreferences.summary_tabs.indexOf(String(idx))>=0) ,
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
            
            if(this.options.in_popup_dialog==true){
                window.close(this._currentEditRecordset);
            } else if(this.options.isdialog){
                this._as_dialog.dialog('close');
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
        
        switch(idx){
            case 0:   //admins -------------------------------------------------------
            
                var recRecTypeID = that._getField('rec_RecTypeID');
                sContent =  
'<div style="margin:4px;"><div style="padding-bottom:0.5em;display: inline-block;width: 100%;">'

+'<h2 class="truncate rectypeHeader" style="float:left;max-width:400px;margin-right:8px;">'
                + '<img src="'+ph_gif+'" style="vertical-align:top;margin-right: 10px;background-image:url(\''
                + top.HAPI4.iconBaseURL+recRecTypeID+'\');"/>'
                + window.hWin.HEURIST4.rectypes.names[recRecTypeID]+'</h2>'
+'<select class="rectypeSelect" style="display:none;z-index: 20;background:white;position: absolute;border: 1px solid gray;'
+'top: 5.7em;" size="20"></select><div class="btn-modify"/>'
+'<div style="display:inline-block;float:right;">'
    +'<div class="btn-config2"/><div class="btn-config"/>'
    +'<span style="display:inline-block;float:right;color:#7D9AAA;padding:2px 4px;">Modify structure</span>'
+'</div>'
+'</div>'

+'<div style="display:inline-block;padding:0 8px 0 27px;float:left"><label class="small-header" style="min-width:0">Owner:</label><span id="recOwner">'
    +that._getField('rec_OwnerUGrpID')+'</span><br>'
+'<label class="small-header" style="min-width:0">Access:</label><span id="recAccess">'
    +that._getField('rec_NonOwnerVisibility')+'</span></div>'
+'<div class="btn-access"/>'    
    
+'<div style="display:inline-block;float:right;">'
    +'<label><input type="checkbox" class="chb_show_help"/>Show help</label><br>'
    +'<label><input type="checkbox" class="chb_opt_fields"/>Optional fields</label>'
+'</div>';

                $(sContent).appendTo(panel);
                //activate buttons
                panel.find('.btn-config2').button({text:false,label:top.HR('Modify record type structure in new window'),
                        icons:{primary:'ui-icon-extlink'}})
                    .addClass('ui-heurist-btn-header1')
                    .css({float: 'right','font-size': '0.8em', height: '18px'})
                    .click(function(){
                        that.editRecordTypeOnNewTab();
                    });
                    
                panel.find('.btn-config').button({text:false,label:top.HR('Modify record type structure'),
                        icons:{primary:'ui-icon-gear'}})
                    .addClass('ui-heurist-btn-header1')
                    .css({float: 'right','font-size': '0.8em', height: '18px'})
                    .click(function(){that.editRecordType();});

                    
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
                                window.hWin.HEURIST4.ui.createRectypeSelect(selRt.get(0));    
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
                                              
                                              that._editing.setModified(true);
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
                    

                panel.find('.btn-access').button({text:false,label:top.HR('Change ownership and access right'),
                        icons:{primary:'ui-icon-lock'}})
                    .addClass('ui-heurist-btn-header1')
                    .css({float: 'left','margin': '0.8em 7px 0 0', 'font-size': '0.8em', height: '18px'})
                    .click(function(){
                    
        var url = window.hWin.HAPI4.baseURL + 'hclient/framecontent/recordAction.php?db='+window.hWin.HAPI4.database+'&action=ownership&owner='+that._getField('rec_OwnerUGrpID')+'&scope=noscope&access='+that._getField('rec_NonOwnerVisibility');

        window.hWin.HEURIST4.msg.showDialog(url, {height:300, width:500,
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
                        
                        if(Number(context.owner)==0){
                            sUserName = window.hWin.HR('Everyone');
                        }else if(context.owner == window.hWin.HAPI4.currentUser['ugr_ID']){
                            sUserName = window.hWin.HAPI4.currentUser['ugr_FullName'];
                        }else{
                            sUserName = window.hWin.HAPI4.currentUser.usr_GroupsList[Number(context.owner)][1];
                        }
                        
                        panel.find('#recOwner').html(sUserName);
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
            window.hWin.HAPI4.SystemMgr.usr_names({UGrpID:[that._getField('rec_OwnerUGrpID')]},
                function(response){
                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                        panel.find('#recOwner').text(response.data[that._getField('rec_OwnerUGrpID')]);
                    }
            });
            
            
                panel.find('.chb_show_help').attr('checked', this.usrPreferences['help_on']==true || this.usrPreferences['help_on']=='true')
                    .change(function( event){
                        var ishelp_on = $(event.target).is(':checked');
                        that.usrPreferences['help_on'] = ishelp_on;
                        window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, $(that.element));
                    });
                panel.find('.chb_opt_fields').attr('checked', this.usrPreferences['optfields']==true || this.usrPreferences['optfields']=='true')
                    .change(function( event){
                        var isfields_on = $(event.target).is(':checked');
                        that.usrPreferences['optfields'] = isfields_on;
                        $(that.element).find('div.optional').parent().css({'display': (isfields_on?'table':'none')} ); 
                    });
            
            
                break;
            case 1:   //find all reverse links
            
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
                    $('<div class="detailRowHeader" style="border:none">Related</div>').insertBefore(ele1);
                }
                if(sLink_Ids.length>0){
                    $('<div class="detailRowHeader">Linked from</div>').insertBefore(ele2);
                }
                if(sRel_Ids.length==0 && sLink_Ids.length==0){
                    $('<div class="detailRowHeader">none</div>').appendTo(panel);
                }
                

                panel.css({'font-size':'0.9em','line-height':'1.5em','overflow':'hidden !important'});
                            
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
            case 2:   //scrtachpad
            
                //find field in hEditing
                var ele = that._editing.getFieldByName('rec_ScratchPad');
                ele.editing_input('option',{showclear_button:false, show_header:false});
                ele[0].parentNode.removeChild(ele[0]);                
                ele.css({'display':'block','width':'99%'});
                ele.find('textarea').attr('rows', 10).css('width','100%');
                ele.show().appendTo(panel);
                
                break;
            case 3:   //private
            
                if(panel.text()!='') return;
                
                panel.append('<div class="bookmark" style="min-height:2em;padding:4px 2px"/>'
                +'<div class="reminders" style="min-height:2em;padding:4px 2px"/>');
                
                //find bookmarks and reminders
                that._renderSummaryBookmarks(null, panel);
                that._renderSummaryReminders(null, panel);
            
            
                break;
            case 4:   //tags
            
                if(panel.text()!='') return;
                panel.text('....');
            
                var request = {};
                request['a']          = 'search'; //action
                request['entity']     = 'usrTags';
                request['details']    = 'name';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                request['rtl_RecID']  = this._currentEditID;
                
                //request['DBGSESSID'] = '423997564615200001;d=1,p=0,c=0';

                var that = this;                                                
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            var recordset = new hRecordSet(response.data);
                            that._renderSummaryTags(recordset, panel);
                        }
                    });
                
            
                break;
            case 6:   //dates
                
 $('<div><label class="small-header">Added By:</label><span id="recAddedBy">'+that._getField('rec_AddedByUGrpID')+'</span></div>'
+'<div><label class="small-header">Added:</label>'+that._getField('rec_Added')+'</div>'
+'<div><label class="small-header">Updated:</label>'+that._getField('rec_Modified')+'</div>').appendTo(panel);

            window.hWin.HAPI4.SystemMgr.usr_names({UGrpID:[that._getField('rec_AddedByUGrpID')]},
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
                sContent = 'no reminders';
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
                icons:{primary:'ui-icon-mail'}})
                .css({float:'right', height: '18px'})
                .addClass('ui-heurist-btn-header1')
                .click(function(){
                    
                        window.hWin.HEURIST4.ui.showEntityDialog('usrReminders', {
                                isdialog: true,
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
                sContent = 'not bookmarked';
            }else{
                var rec = recordset.getFirstRecord();
                var val = recordset.fld(rec, 'bkm_Rating');
                sContent = (!window.hWin.HEURIST4.util.isempty(val))?('Rating: '+val+'. '):''; 
                val = recordset.fld(rec, 'bkm_PwdReminder');
                sContent = sContent + ((!window.hWin.HEURIST4.util.isempty(val))?('Password reminder: '+val):''); 
                val = recordset.fld(rec, 'bkm_Notes');
                sContent = sContent + ((!window.hWin.HEURIST4.util.isempty(val))
                        ?('<br>Notes: '+val.substr(0,500)+(val.length>500?'...':'')):''); 
                
            }
            pnl.append(sContent);
            
            //append/manage button
            $('<div>').button({label:top.HR('Manage bookmark info'), text:false,
                icons:{primary:'ui-icon-bookmark'}})
                .addClass('ui-heurist-btn-header1')
                .css({float:'right', height: '18px'})
                .click(function(){
                    
                        window.hWin.HEURIST4.ui.showEntityDialog('usrBookmarks', {
                                isdialog: true,
                                bkm_RecID: that._currentEditID,
                                onClose:function(){
                                    //refresh
                                    that._renderSummaryBookmarks(null, panel);
                                }
                                
                        });
                })
             .appendTo(pnl);        
    },
    //
    //
    //
    _renderSummaryTags: function(recordset, panel){
        
            var that = this, idx, isnone=true;
            
            panel.empty().css({'font-size': '0.9em'});
            
            $('<div><i style="display:inline-block;">Personal:&nbsp;</i></div>')
                .css({'padding':'2px 4px 16px 4px'})
                .attr('data-id', window.hWin.HAPI4.currentUser['ugr_ID'])
                .hide().appendTo(panel);
            
            var groups = window.hWin.HAPI4.currentUser.usr_GroupsList;
            if(groups)
            for (idx in groups)
            {
                if(idx){
                    var groupID = idx;
                    var name = groups[idx][1];
                    if(!window.hWin.HEURIST4.util.isnull(name))
                    {
                        $('<div><i style="display:inline-block;">'+name+':&nbsp;</i></div>')
                            .css({'padding':'0 2 4 2px'})
                            .attr('data-id', groupID).hide().appendTo(panel);
                    }
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
                                isdialog: true,
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
                                        that._renderSummaryTags(data.selection, panel);
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
            
                var sMsg = "Click YES to save changes and modify the record structure.<br>"
                            +"If you are unable to save changes, click Cancel and open<br>"
                            +"structure modification in a new tab (button next to clicked one)";
                window.hWin.HEURIST4.msg.showMsgDlg(sMsg, function(){
                    
                        that._saveEditAndClose( null, function(){
                            that._editing.initEditForm(null, null); //clear edit form
                            that._initEditForm_step3(that._currentEditID); //reload edit form                       
                            that.editRecordType();
                        })
                });   
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
        
            window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+recID, w: "all", f:"complete", l:1}, 
                        function(response){ that._initEditForm_step4(response); });

        }else if(recID<0){ //add new record
        
            if(!that.options.new_record_params) that.options.new_record_params = {};
        
            if(this._currentEditRecTypeID>0){
                that.options.new_record_params['rt'] = this._currentEditRecTypeID;
            }        
            
            that.options.new_record_params['temp'] = 1;
            
            if(!(that.options.new_record_params['rt']>0)){

                //select record type first
                if(!this._rt_select_dialog){
                    this._rt_select_dialog = $('<div>').css({'text-align': 'center'}).appendTo(this.element);
                    var selRt = $('<select>').appendTo(this._rt_select_dialog);
                    window.hWin.HEURIST4.ui.createRectypeSelect(selRt.get(0));    
                }
                
                var $dlg, btns = [
                {text:window.hWin.HR('Add Record'),
                                click: function(){  
                                    
                that._currentEditRecTypeID = that._rt_select_dialog.find('select').val();
                that.options.new_record_params['rt'] = this._currentEditRecTypeID;
                                    
                window.hWin.HAPI4.RecordMgr.add( that.options.new_record_params,
                        function(response){ that._initEditForm_step4(response); });
                                
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
                       
               
            }else{
                //this._currentEditRecTypeID is set in add button
                window.hWin.HAPI4.RecordMgr.add( that.options.new_record_params,
                        function(response){ that._initEditForm_step4(response); });
            }
        }
        
        
        

        return;
    },
    
    //
    //
    //
    _getFakeRectypeField: function(detailTypeID){
        
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
        ffr[fieldIndexMap['rst_DisplayOrder']] = 999;
        ffr[fieldIndexMap['rst_DisplayWidth']] = 50;
        ffr[fieldIndexMap['rst_FilteredJsonTermIDTree']] = (dt?dt[dtyFieldNamesIndexMap['dty_JsonTermIDTree']]:"");
        ffr[fieldIndexMap['rst_LocallyModified']] = 0;
        ffr[fieldIndexMap['rst_Modified']] = 0;
        ffr[fieldIndexMap['rst_NonOwnerVisibility']] = (dt?dt[dtyFieldNamesIndexMap['dty_NonOwnerVisibility']]:"viewable");
        ffr[fieldIndexMap['rst_OrderForThumbnailGeneration']] = 0;
        ffr[fieldIndexMap['rst_OriginatingDBID']] = 0;
        ffr[fieldIndexMap['rst_PtrFilteredIDs']] = (dt?dt[dtyFieldNamesIndexMap['dty_PtrTargetRectypeIDs']]:"");
        ffr[fieldIndexMap['rst_RecordMatchOrder']] = 0;
        ffr[fieldIndexMap['rst_RequirementType']] = 'optional';
        ffr[fieldIndexMap['rst_Status']] = (dt?dt[dtyFieldNamesIndexMap['dty_Status']]:"open");
        ffr[fieldIndexMap['dty_Type']] = (dt?dt[dtyFieldNamesIndexMap['dty_Type']]:"freetext");
        
        return ffr;
    },
    
    //
    // prepare fields and init editing
    //
    _initEditForm_step4: function(response){
        
        var that = this;
        
        if(response==null || response.status == window.hWin.HAPI4.ResponseStatus.OK){
            
            if(response){
                that._currentEditRecordset = new hRecordSet(response.data);
            }
            
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
                fi_maxval = fi['rst_MaxValues']; //need for proper repeat
            
            var s_fields = []; //sorted fields
            var fields_ids = [];
            for(var dt_ID in rfrs){ //in rt structure
                if(dt_ID>0){
                    rfrs[dt_ID]['dt_ID'] = dt_ID;
                    s_fields.push(rfrs[dt_ID]);
                    fields_ids.push(Number(dt_ID));
                }
            }
            //sort by order
            s_fields.sort(function(a,b){ return a[fi_order]<b[fi_order]?-1:1});

            //add non-standard fields that are not in structure
            var field_in_recset = that._currentEditRecordset.getDetailsFieldTypes();
            var addhead = true;
            for(var k=0; k<field_in_recset.length; k++){
                if(fields_ids.indexOf(field_in_recset[k])<0){
                    if(addhead){                    
                        var rfr = that._getFakeRectypeField(1);
                        rfr[fi_name] = 'Non-standard record type fields for this record';
                        rfr[fi_type] = 'separator';
                        s_fields.push(rfr);
                        addhead = false;
                    }
                    s_fields.push(that._getFakeRectypeField(field_in_recset[k]));
                }
                
                
            }           
            
             
            
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
            }//for s_fields
            //add children to last group
            if(group_fields!=null){
                fields[fields.length-1].children = group_fields;
            }
            
            that._editing.initEditForm(fields, that._currentEditRecordset);
            that._afterInitEditForm();

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
            
        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }
    },        
    

    
    
    //  -----------------------------------------------------
    //
    //  send update request and close popup if edit is in dialog
    // OVERRIDE
    //
    _saveEditAndClose: function( fields, afterAction ){

            if(!fields){
                fields = this._getValidatedValues(); 
            }
            
            if(fields==null) return; //validation failed
       
            var request = {ID: this._currentEditID, 
                           RecTypeID: this._currentEditRecTypeID, 
                           URL: fields['rec_URL'],
                           OwnerUGrpID: fields['rec_OwnerUGrpID'],
                           NonOwnerVisibility: fields['rec_NonOwnerVisibility'],
                           ScratchPad: fields['rec_ScratchPad'],
                           'details': fields};
        
            var that = this;                                    

            that.onEditFormChange(true); //forcefully hide all "save" buttons
            
                //that.loadanimation(true);
                window.hWin.HAPI4.RecordMgr.save(request, 
                    function(response){
                        
                        //that.onEditFormChange();
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

                            var recID = ''+response.data[0];
                            var rec_Title = response.rec_Title;
                            
                            //that._afterSaveEventHandler( recID, fields);
                            //
                            
                            if($.isFunction(afterAction)){
                               
                               afterAction.call(); 
                                
                            }else if(afterAction=='close'){
                                that._currentEditID = null;
                                that._currentEditRecordset.setFld(
                                        that._currentEditRecordset.getFirstRecord(),'rec_Title',rec_Title);
                                that.closeEditDialog();            
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
    onEditFormChange:function(force_hide){
        var mode = 'hidden';
        if(force_hide!==true){
            var isChanged = this._editing.isModified();
            mode = isChanged?'visible':'hidden';
        }
        //show/hide save buttons
        var ele = this._toolbar;
        ele.find('#btnRecCancel').css('visibility', mode);
        ele.find('#btnRecSaveAndNew').css('visibility', mode);
        ele.find('#btnRecSave').css('visibility', mode);
        ele.find('#btnRecSaveAndClose').css('visibility', mode);
        
        ele.find('#btnRecDuplicate').css({'display':((this._currentEditID>0)?'inline-block':'none')});
        
        //ele.find('#btnRecReload').css('visibility', !mode);
    },
    
    //
    //
    //    
    _afterInitEditForm: function(){
        
        //$(this.element).find('.chb_show_help').is(':checked');        
        var ishelp_on = this.usrPreferences['help_on']==true || this.usrPreferences['help_on']=='true';
        window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, $(this.element));
        
        var isfields_on = this.usrPreferences['optfields']==true || this.usrPreferences['optfields']=='true';
        $(this.element).find('div.optional').parent().css({'display': (isfields_on?'table':'none')} ); 
        
        //add record title at the top
        
        if(this._getField('rec_Title')!=''){
            this.element.find('.ui-heurist-header2').remove();
            $('<div class="ui-heurist-header2"><span style="display:inline-block;padding-right:20px">ID: '+this._currentEditID
                +'</span><h3 style="display:inline-block">'+ this._getField('rec_Title')+'</h3></div>')
                .css({'padding':'10px 0 10px 30px'})
                .insertBefore(this.editForm.first('fieldset'));
        }
        
        this.onEditFormChange();
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
            optfields = true,
            help_on = true;
            
            
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
                
                help_on = that.editFormSummary.find('.chb_show_help').is(':checked');
                optfields = that.editFormSummary.find('.chb_opt_fields').is(':checked');
        }
            
        
        if(this.options.edit_mode=='editonly'){
            if(that.options.isdialog){
                dwidth  = that._as_dialog.dialog('option','width');
                dheight = that._as_dialog.dialog('option','height');
            }else if(that.options.in_popup_dialog==true){
                dwidth  = window.innerWidth+20;
                dheight = window.innerHeight+46;
            }            
        }else                
        if(that._edit_dialog && that._edit_dialog.dialog('isOpen')){
            dwidth  = that._edit_dialog.dialog('option','width'); 
            dheight = that._edit_dialog.dialog('option','height');
        } 


        window.hWin.HAPI4.save_pref('prefs_'+this._entityName, 
            {width:dwidth, height:dheight, help_on:help_on, optfields:optfields, 
                summary_closed:isClosed, summary_width:sz, summary_tabs:activeTabs} );

        
        return true;
    }
    
    
});
