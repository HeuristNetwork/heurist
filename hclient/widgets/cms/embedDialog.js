/**
* embedDialog.js - Popup to get code (url or script snippet) to embed Heurist 
* functionality as a single page into third party website
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

$.widget( "heurist.embedDialog", {

    // default options
    options: {
    
        //DIALOG section       
        isdialog: false,     // show as dialog @see  _initDialog(), popupDialog(), closeDialog
        height: 400,
        width:  760,
        modal:  true,
        title:  '',
        htmlContent: 'embedDialog.html',
        helpContent: null,
        
        path: 'cms/',
        
        //parameters
        layout_rec_id: 0,
        
        cms_popup_dialog_options: {},
        
        //listeners
        onInitFinished:null,  //event listener when dialog is fully inited - use to perform initial search with specific parameters
        beforeClose:null,     //to show warning before close
        onClose:null       
    },
    
    _as_dialog:null, //reference to itself as dialog (see options.isdialog)
    
    _need_load_content:true,
    
    _context_on_close:false, //variable to be passed to options.onClose event listener
    
    //controls
    selectRecordScope:null, //selector for WebPage records
    
    RT_CMS_MENU:0, DT_CMS_PAGETYPE:0,
    
    // the widget's constructor
    _create: function() {
        // prevent double click to select text
        //it prevents inputs in FF this.element.disableSelection();
    }, //end _create
    
    //
    //  load configuration and call _initControls
    //
    _init: function() {
        
        var that = this;
        
        this.RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'];
        this.DT_CMS_PAGETYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETYPE'];
        
        if (!(this.RT_CMS_MENU>0 && this.DT_CMS_PAGETYPE>0)){
            var $dlg2 = window.hWin.HEURIST4.msg.showMsgDlg('You will need record types '
            +'99-52 (Web manu/page) with field 2-928 (Page type) which are available as part of Heurist_Core_Definitions. '
            +'Click "Import" to get these definitions',
                        {'Import':function(){
                            var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                            $dlg2.dialog('close');
            
                            //import recctype 51 (CMS Home page) from Heurist_Core_Definitions        
                            window.hWin.HEURIST4.msg.bringCoverallToFront();
                            window.hWin.HEURIST4.msg.showMsgFlash('Import definitions', 10000);
                            
                            //synch record type from Database #2 Heurist_Core_Definitions
                            window.hWin.HAPI4.SystemMgr.import_definitions(2, 52,
                                function(response){    
                                    window.hWin.HEURIST4.msg.sendCoverallToBack(); 
                                    var $dlg2 = window.hWin.HEURIST4.msg.getMsgFlashDlg();
                                    if($dlg2.dialog('instance')) $dlg2.dialog('close');

                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        that._init(); //call itself again
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);     
                                    }
                                });
                            
                        },
                        'Cancel':function(){
                                var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                                $dlg2.dialog('close');}
                        },
                                'Definitions required');
            
            return;
        }
        
        this.element.addClass('ui-heurist-bg-light');
            
        
        if(this.options.isdialog){  //show this widget as popup dialog
            this._initDialog();
        }
        
        //load html from file
        if(this._need_load_content && this.options.htmlContent){        
            this.element.load(window.hWin.HAPI4.baseURL+'hclient/widgets/cms/'+this.options.htmlContent
                            +'?t='+window.hWin.HEURIST4.util.random(), 
            function(response, status, xhr){
                that._need_load_content = false;
                if ( status == "error" ) {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }else{
                    if(that._initControls()){
                        if($.isFunction(that.options.onInitFinished)){
                            that.options.onInitFinished.call(that);
                        }        
                    }
                }
            });
            return;
        }else{
            if(that._initControls()){
                if($.isFunction(that.options.onInitFinished)){
                    that.options.onInitFinished.call(that);
                }        
            }
        }

        
        
    },
    
     
    //  
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        
        var that = this;

        this.selectRecordScope = this.element.find('#sel_layout');
        
        if(this.options.layout_rec_id>0){
            
            this._fillEmbedForm()
            
        }else if(this.selectRecordScope.length>0){
            
            window.hWin.HEURIST4.ui.initHSelect(this.selectRecordScope, false);
            
            this._on( this.selectRecordScope, { change: function(){
                if(this.selectRecordScope.val()>0){
                    
                    this.options.cms_popup_dialog_options.record_id = this.selectRecordScope.val();
                    window.hWin.HEURIST4.ui.showEditCMSDialog( this.options.cms_popup_dialog_options );
                    that.closeDialog();
                }
            }} );        
            var ele = this.element.find('#btnCreateLayout');
            ele.button();
            this._on( ele, { click: this._createNewLayout } );        
            
            /*
            ele =this.element.find('#btnEditLayout');
            ele.button();
            this._on( ele, { click: function(){
                window.hWin.HEURIST4.ui.showEditCMSDialog( this.selectRecordScope.val() );
            } } );        
            */
            
            
            this._fillSelectLayout();
        }
        
        if(this.options.isdialog){
            this.popupDialog();
        }
        
        //show hide hints and helps according to current level
        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, this.element); 
        
        return true;
    },

    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        this._superApply( arguments );
    },

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        if(this.selectRecordScope) this.selectRecordScope.remove();

    },
    
    //----------------------
    //
    // array of button defintions
    //
    _getActionButtons: function(){

        var that = this;        
        return [
                 {text:window.hWin.HR('Close'), 
                    id:'btnCancel',
                    css:{'float':'right','margin-left':'30px'}, 
                    click: function() { 
                        that.closeDialog();
                    }}
                    /*,
                 {text:window.hWin.HR('Next'),
                    id:'btnDoAction',
                    disabled:'disabled',
                    css:{'float':'right'},  
                    click: function() { 
                            that.doAction(); 
                    }}*/
                 ];
    },

    //
    // init dialog widget
    // see also popupDialog, closeDialog 
    //
    _initDialog: function(){
        
            var options = this.options,
                btn_array = this._getActionButtons(), 
                position = null,
                    that = this;
        
            if(!options.beforeClose){
                    options.beforeClose = function(){
                        //show warning on close
                        /*
                            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                                    'You have made changes to the data. Click "Save" otherwise all changes will be lost.',
                                    buttons,
                                    {title:'Confirm',yes:'Save',no:'Ignore and close'});
                            return false;   
                        }
                        */
                        //that.saveUiPreferences();
                        return true;
                    };
            }
            
            if(position==null) position = { my: "center", at: "center", of: window };
            var maxw = (window.hWin?window.hWin.innerWidth:window.innerWidth);
            if(options['width']>maxw) options['width'] = maxw*0.95;
            var maxh = (window.hWin?window.hWin.innerHeight:window.innerHeight);
            if(options['height']>maxh) options['height'] = maxh*0.95;
            
            var that = this;
            
            var $dlg = this.element.dialog({
                autoOpen: false ,
                //element: this.element[0],
                height: options['height'],
                width:  options['width'],
                modal:  (options['modal']!==false),
                title: window.hWin.HEURIST4.util.isempty(options['title'])?'Embedding code':options['title'],
                position: position,
                beforeClose: options.beforeClose,
                resizeStop: function( event, ui ) {//fix bug
                    that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
                },
                close:function(){
                    if($.isFunction(that.options.onClose)){
                      //that.options.onClose(that._currentEditRecordset);  
                      that.options.onClose( that._context_on_close );
                    } 
                    that._as_dialog.remove();    
                        
                },
                buttons: btn_array
            }); 
            this._as_dialog = $dlg; 
            
            if(this.options.cms_popup_dialog_options.container){
                //$dlg.addClass('ui-heurist-bg-light');
                $dlg.parent().addClass('ui-dialog-heurist ui-heurist-publish');
            }
            
            
    },
    
    //
    // show itself as popup dialog
    //
    popupDialog: function(){
        if(this.options.isdialog){

            this._as_dialog.dialog("open");
            
            var helpURL = (this.options.helpContent)
                ?(window.hWin.HAPI4.baseURL+'context_help/'+this.options.helpContent+' #content'):null;
            
            window.hWin.HEURIST4.ui.initDialogHintButtons(this._as_dialog, null, helpURL, false);
        }
    },
    
    //
    // close dialog
    //
    closeDialog: function(is_force){
        if(this.options.isdialog){
            
            if(is_force===true){
                this._as_dialog.dialog('option','beforeClose',null);
            }
            
            this._as_dialog.dialog("close");
        }
    },
    
    _fillEmbedForm: function(){
        
        //show embed panel
        this.element.find('#div_layout').hide();
        this.element.find('#div_embed').show();
        
        var rec_id = this.options.layout_rec_id;
            
        var query = window.hWin.HAPI4.baseURL+
                    '?db='+window.hWin.HAPI4.database+'&website&id='+rec_id;
                
                //searchIDs=
                
        this.element.find('#code-textbox3').val(query);
        
        this.element.find('#code-textbox').val('<iframe src=\'' + query +
                    '\' width="100%" height="700" frameborder="0"></iframe>');
                
    },

    //
    //either open website editor or show embed panel
    //
    _createNewLayout: function(){
        
        //either open website editor
        if(this.selectRecordScope.val()>0){
            this.options.layout_rec_id = this.selectRecordScope.val();
            this._fillEmbedForm();            
        }else{
            
            var layout_name = this.element.find('#layout_name').val();
            
            if(window.hWin.HEURIST4.util.isempty(layout_name)){
                
                window.hWin.HEURIST4.msg.showMsgFlash('Specify name of new layout',1000);
                
            }else{
                
                var that = this;
                //create new web page
                this.options.cms_popup_dialog_options.record_id = -2;
                this.options.cms_popup_dialog_options.webpage_title = layout_name;
                window.hWin.HEURIST4.ui.showEditCMSDialog( this.options.cms_popup_dialog_options );
                /*
                    callback: function(rec_id, rec_title){
                        if(rec_id>0){
                            window.hWin.HEURIST4.ui.addoption(that.selectRecordScope[0],
                                        rec_id,rec_title);
                            that.selectRecordScope.val(rec_id);
                            that.doAction();
                        }
                });*/
                this.closeDialog();
            }
            
            
        }
    },

    //  -----------------------------------------------------
    //
    // Loads all RT_CMS_MENU with DT_CMS_PAGETYPE==2-6254
    //
    _fillSelectLayout: function (init_value){

        this.selectRecordScope.empty();
        
        var opt, selScope = this.selectRecordScope.get(0);

        window.hWin.HEURIST4.ui.addoption(selScope, '', 'select...');
        //window.hWin.HEURIST4.ui.addoption(selScope, 0, 'Create new layout');
        
        //page type
        var local_trmID = $Db.getLocalID('trm', '2-6254');
        var that = this;
        var query = {t:this.RT_CMS_MENU};
        query['f:'+this.DT_CMS_PAGETYPE] = local_trmID;
        var request = {detail:'header',q:query};
        
        window.hWin.HAPI4.RecordMgr.search(request,
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        var resdata = new hRecordSet(response.data);
                        var idx, records = resdata.getRecords();
                        for(idx in records){
                            if(idx)
                            {
                                var record = records[idx];
                                window.hWin.HEURIST4.ui.addoption(selScope,
                                    resdata.fld(record, 'rec_ID'),
                                    //resdata.fld(record, 'rec_Title') );
                                    window.hWin.HEURIST4.util.stripTags(resdata.fld(record, 'rec_Title')) );
                                    //window.hWin.HEURIST4.util.htmlEscape(resdata.fld(record, 'rec_Title')) ); 
                            }
                        }//for
                        
                        if(init_value>0) that.selectRecordScope.val(init_value);    
                        if(selScope.selectedIndex<0) selScope.selectedIndex=0;
                        that.selectRecordScope.hSelect("refresh");
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        that.closeDialog(true);
                    }
                    
                });

        this.element.find('#layout_name').val('');
    },

});

