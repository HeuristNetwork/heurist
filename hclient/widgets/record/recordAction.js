/**
* recordAction.js - BASE widget for actions for scope of records
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.recordAction", {

    // default options
    options: {
    
        is_h6style: false,    
        default_palette_class: 'ui-heurist-explore', 
        //DIALOG section       
        isdialog: false,     // show as dialog @see  _initDialog(), popupDialog(), closeDialog
        supress_dialog_title: false, //hide dialog title bar (applicable if isdialog=true
        
        height: 400,
        width:  760,
        position: null,
        modal:  true,
        title:  '',
        
        path: '',
        htmlContent: 'recordAction.html',
        helpContent: null,
        
        //parameters
        scope_types: null, // [all, selected, current, rectype ids, none]
        init_scope: '',  // inital selection
        currentRecordset: null,
        
        //listeners
        onInitFinished:null,  //event listener when dialog is fully inited - use to perform initial search with specific parameters
        beforeClose:null,     //to show warning before close
        onClose:null 
        
    },
    
    _currentRecordset:null,
    _currentRecordsetSelIds:null,
    _currentRecordsetColIds: null,
    
    _as_dialog:null, //reference to itself as dialog (see options.isdialog)
    _toolbar:null,
    
    _need_load_content:true,
    
    _context_on_close:false, //variable to be passed to options.onClose event listener
    
    _progressInterval:null,
    
    //selector control
    selectRecordScope:null,
    
    // the widget's constructor
    _create: function() {
        // prevent double click to select text
        //it prevents inputs in FF this.element.disableSelection();
    }, //end _create
    
    //
    //  load configuration and call _initControls
    //
    _init: function() {
        
        if(this.options.currentRecordset){
            this._currentRecordset  = this.options.currentRecordset;
            this._currentRecordsetSelIds = null;
            this._currentRecordsetColIds = null;
        }else if(window.hWin.HAPI4.currentRecordset){
            this._currentRecordset = window.hWin.HAPI4.currentRecordset;
            this._currentRecordsetSelIds = window.hWin.HAPI4.currentRecordsetSelection;
            this._currentRecordsetColIds = window.hWin.HAPI4.currentRecordsetCollected;
        }else{
            //Testing
            this._currentRecordset = new HRecordSet({count: "0",offset: 0,reccount: 0,records: [], rectypes:[]});
            this._currentRecordsetSelIds = null;
            this._currentRecordsetColIds = null;
        }
        
        
        if(this.options.isdialog){  //show this widget as popup dialog
            this._initDialog();
        }else{
            this.element.addClass('ui-heurist-bg-light');
        }
            
            
        
        //init layout
        let that = this;
        
        //load html from file
        if(this._need_load_content && this.options.htmlContent){        
            
            let url = this.options.htmlContent.indexOf(window.hWin.HAPI4.baseURL)===0
                    ?this.options.htmlContent
                    :window.hWin.HAPI4.baseURL+'hclient/' 
                        + (this.options.path?this.options.path:'widgets/record/')+this.options.htmlContent
                            +'?t='+window.hWin.HEURIST4.util.random();
            
            this.element.load(url, 
            function(response, status, xhr){
                that._need_load_content = false;
                if ( status == "error" ) {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }else{
                    if(that._initControls()){
                        if(window.hWin.HEURIST4.util.isFunction(that.options.onInitFinished)){
                            that.options.onInitFinished.call(that);
                        }        
                    }
                }
            });
            return;
        }else{
            if(that._initControls()){
                if(window.hWin.HEURIST4.util.isFunction(that.options.onInitFinished)){
                    that.options.onInitFinished.call(that);
                }        
            }
        }

        
        
    },
    
     
    //  
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        
        let that = this;

        this.popupDialog();
        
        this.element.find('label[for="sel_record_scope"]').text(window.hWin.HR('recordAction_select_lbl'));
        
        this.selectRecordScope = this.element.find('#sel_record_scope');
        if(this.selectRecordScope.length>0){
            if(this._fillSelectRecordScope()===false){
                this.closeDialog();                
            }   return;
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

        let that = this;        
        return [
                 {text:window.hWin.HR('Cancel'), 
                    id:'btnCancel',
                    css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                    click: function() { 
                        that.closeDialog();
                    }},
                 {text:window.hWin.HR('Go'),
                    id:'btnDoAction',
                    class:'ui-button-action',
                    disabled:'disabled',
                    css:{'float':'right'},  
                    click: function() { 
                            that.doAction(); 
                    }}
                 ];
    },

    //
    // define action buttons for edit toolbar
    //
    _defineActionButton2: function(options, container){        
        
        let btn_opts = {label:options.text, icons:options.icons, title:options.title};
        
        let btn = $('<button>').button(btn_opts)
                    .click(options.click)
                    .appendTo(container);
        if(options.id){
            btn.attr('id', options.id);
        }
        if(options.css){
            btn.css(options.css);
        }
        if(options.class){
            btn.addClass(options.class);
        }
    },
    
    
    //
    // init dialog widget
    // see also popupDialog, closeDialog 
    //
    _initDialog: function(){
        
            let options = this.options,
                btn_array = this._getActionButtons(), 
                that = this;
        
            if(!options.beforeClose){
                    options.beforeClose = function(){
                        //show warning on close
                        return true;
                    };
            }
            
            if(options.position==null) options.position = { my: "center", at: "center", of: window };
            
            let maxw = (window.hWin?window.hWin.innerWidth:window.innerWidth);
            if(options['width']>maxw) options['width'] = maxw*0.95;
            let maxh = (window.hWin?window.hWin.innerHeight:window.innerHeight);
            if(options['height']>maxh) options['height'] = maxh*0.95;
            
            let $dlg = this.element.dialog({
                autoOpen: false ,
                //element: this.element[0],
                height: options['height'],
                width:  options['width'],
                modal:  (options['modal']!==false),
                title: window.hWin.HEURIST4.util.isempty(options['title'])?'':window.hWin.HR(options['title']), //title will be set in  initControls as soon as entity config is loaded
                position: options['position'],
                beforeClose: options.beforeClose,
                resizeStop: function( event, ui ) {//fix bug
                    that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
                },
                close:function(){
                    if(window.hWin.HEURIST4.util.isFunction(that.options.onClose)){
                      //that.options.onClose(that._currentEditRecordset);  
                      that.options.onClose( that._context_on_close );
                    } 
                    that._as_dialog.remove();    
                        
                },
                buttons: btn_array
            }); 
            this._as_dialog = $dlg; 
            
    },
    
    //
    // show itself as popup dialog
    //
    popupDialog: function(){
        if(this.options.isdialog){

            let $dlg = this._as_dialog.dialog("open");
            
            
            if(this._as_dialog.attr('data-palette')){
                $dlg.parent().removeClass(this._as_dialog.attr('data-palette'));
            }
            if(this.options.default_palette_class){
                this._as_dialog.attr('data-palette', this.options.default_palette_class);
                $dlg.parent().addClass(this.options.default_palette_class);
                this.element.removeClass('ui-heurist-bg-light');
            }else{
                this._as_dialog.attr('data-palette', null);
                this.element.addClass('ui-heurist-bg-light');
            }

            if(this.options.supress_dialog_title) $dlg.parent().find('.ui-dialog-titlebar').hide();
            
            
            if(this.options.helpContent){
                let helpURL = window.hWin.HRes( this.options.helpContent )+' #content';
                window.hWin.HEURIST4.ui.initDialogHintButtons(this._as_dialog, null, helpURL, false);    
            }
            
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
        }else{
            
            let canClose = true;
            if(window.hWin.HEURIST4.util.isFunction(this.options.beforeClose)){
                canClose = this.options.beforeClose();
            }
            if(canClose){
                if(window.hWin.HEURIST4.util.isFunction(this.options.onClose)){
                    this.options.onClose( this._context_on_close );
                }
            }
        }
    },

    //
    //
    //
    doAction: function(){
        return;
    },

    //  -----------------------------------------------------
    //
    //  after save event handler
    //
    _afterActionEvenHandler: function( context ){
        
            
    },

    //
    //
    //
    _fillSelectRecordScope: function (){

        let scope_types = this.options.scope_types;
        this.selectRecordScope.empty();
        
        if(scope_types=='none'){
            this.selectRecordScope.parent().hide();
            return;    
        }

        let opt, selScope = this.selectRecordScope.get(0);

        window.hWin.HEURIST4.ui.addoption(selScope,'',window.hWin.HR('recordAction_select_hint'));
        
        let is_initscope_empty = window.hWin.HEURIST4.util.isempty(scope_types);
        if(is_initscope_empty) scope_types = [];   
        
        if(scope_types.indexOf('all')>=0){
            window.hWin.HEURIST4.ui.addoption(selScope,'all',window.hWin.HR('All records'));
        }
        
        if ((is_initscope_empty || scope_types.indexOf('selected')>=0)
            && (this._currentRecordsetSelIds &&  this._currentRecordsetSelIds.length > 0)){
                
            window.hWin.HEURIST4.ui.addoption(selScope,'selected',
                window.hWin.HR('Selected results set (count=') + this._currentRecordsetSelIds.length+')');
        }
        
        if ((is_initscope_empty || scope_types.indexOf('current')>=0)
            && (this._currentRecordset &&  this._currentRecordset.length() > 0)){
                
            window.hWin.HEURIST4.ui.addoption(selScope,'current',
                window.hWin.HR('Current results set (count=') + this._currentRecordset.length()+')');
        }

        let rectype_Ids = [];
        if (!is_initscope_empty){
            for (let rty in scope_types)
            if(rty>=0 && scope_types[rty]>0 && $Db.rty(scope_types[rty],'rty_Name')){ 
                rectype_Ids.push(scope_types[rty]);
            }
        }else if(this._currentRecordset &&  this._currentRecordset.length() > 0){
            rectype_Ids = this._currentRecordset.getRectypes();
        }
        
        for (let rty in rectype_Ids){
            if(rty>=0){
                rty = rectype_Ids[rty];
                
                let name = $Db.rty(rty,'rty_Plural');
                if(!name) name = $Db.rty(rty,'rty_Name');
                
                window.hWin.HEURIST4.ui.addoption(selScope,rty,
                        window.hWin.HR('only:')+' '+name);
            }
        }

        this._on( this.selectRecordScope, {
                change: this._onRecordScopeChange} );        
        this.selectRecordScope.val(this.options.init_scope);    
        if(selScope.selectedIndex<0) selScope.selectedIndex=0;
        this._onRecordScopeChange();
    },

    //
    //
    //    
    _onRecordScopeChange: function () 
    {
        let isdisabled = (this.selectRecordScope.val()=='');
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction'), isdisabled );
        
        return isdisabled;
    },
    
    
    //
    // Requests reportProgress every t_interval ms 
    // is_autohide 
    //    true  - stops progress check if it returns null/empty value
    //    false - it shows rotating(loading) image for null values and progress bar for n,count values
    //                  in latter case _hideProgress should be called explicitely
    //
    _showProgress: function ( session_id, is_autohide, t_interval ){

        if(!(session_id>0)) {
             this._hideProgress();
             return;
        }
        let that = this;
       
        let progressCounter = 0;        
        let progress_url = window.hWin.HAPI4.baseURL + "viewers/smarty/reportProgress.php";

        this.element.find('#div_fieldset').hide();
        this.element.find('.ent_wrapper').hide();
        let progress_div = this.element.find('.progressbar_div').show();
        $('body').css('cursor','progress');
        let btn_stop = progress_div.find('.progress_stop').button({label:window.hWin.HR('Abort')});
        
        this._on(btn_stop,{click: function() {
            
                let request = {terminate:1, t:(new Date()).getMilliseconds(), session:session_id};
                window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                    that._hideProgress();
                    //if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    //    console.error(response);                   
                    //}
                });
            }});
        
        let div_loading = progress_div.find('.loading').show();
        let pbar = progress_div.find('#progressbar');
        let progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});
        //pbar.progressbar('value', 0);
        /*{
              value: false,
              change: function() {
                progressLabel.text( progressbar.progressbar( "value" ) + "%" );
              },
              complete: function() {
                progressLabel.text( "Complete!" );
              }
        });*/
        
        this._progressInterval = setInterval(function(){ 
            
            let request = {t:(new Date()).getMilliseconds(), session:session_id};            
            
            window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
               
                if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    that._hideProgress();
                }else{
                    //it may return terminate,done,
                    let resp = response?response.split(','):[];
                    if(response=='terminate' || resp.length!=2){
                        if(response=='terminate' || is_autohide){
                            that._hideProgress();
                        }else{
                            div_loading.show();    
                            //pbar.progressbar( "value", 0 );
                            //progressLabel.text('wait...');
                        }
                    }else{
                        div_loading.hide();
                        if(resp[0]>0 && resp[1]>0){
                            let val = resp[0]*100/resp[1];
                            pbar.progressbar( "value", val );
                            progressLabel.text(resp[0]+' of '+resp[1]);
                        }else{
                            progressLabel.text(window.hWin.HR('preparing')+'...');
                            pbar.progressbar( "value", 0 );
                        }
                    }
                    
                    progressCounter++;
                    
                }
            },'text');
          
        
        }, t_interval);                
        
    },
    
    _hideProgress: function (){
        
        $('body').css('cursor','auto');
        
        if(this._progressInterval!=null){
            
            clearInterval(this._progressInterval);
            this._progressInterval = null;
        }
        this.element.find('.progressbar_div').hide();
        this.element.find('#div_fieldset').show();
        
    },
    
  
});

