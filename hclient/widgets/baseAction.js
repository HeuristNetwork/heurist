/**
* baseAction.js - BASE widget for popup dialogue
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

$.widget( "heurist.baseAction", {

    // default options
    options: {
        actionName: '',
    
        default_palette_class: 'ui-heurist-admin', 
        //DIALOG section       
        isdialog: false,     // show as dialog @see  _initDialog(), popupDialog(), closeDialog
        supress_dialog_title: false, //hide dialog title bar (applicable if isdialog=true
        
        height: 400,
        width:  760,
        position: null,
        modal:  true,
        title:  '',
        innerTitle: false, //show title as top panel 
        
        path: '',  // non default path to html content 
        htmlContent: '', //general layout
        helpContent: null,
        
        //listeners
        onInitFinished:null,  // event listener when dialog is fully inited
        beforeClose:null,     // to show warning before close
        onClose:null
        
    },
    
    _as_dialog:null, //reference to itself as dialog (see options.isdialog)
    _toolbar:null,
    
    _need_load_content:true, //flag 
    
    _context_on_close:false, //variable to be passed to options.onClose event listener
    
    _progressInterval:null,
    
    //controls
    //.....selectRecordScope:null,
    
    // the widget's constructor
    _create: function() {
        // prevent double click to select text
        // it prevents inputs in FF this.element.disableSelection();
    }, //end _create
    
    //
    //  load configuration and call _initControls
    //
    _init: function() {

        if(this.options.htmlContent==''){
            this.options.htmlContent = this.options.actionName+'.html';
                    //+(window.hWin.HAPI4.getLocale()=='FRE'?'_fre':'')+'.html';
        }
        
        if(this.options.isdialog){  //show this widget as popup dialog
            this._initDialog();
        }else{
            this.element.addClass('ui-heurist-bg-light');
        }
        
        //init layout
        var that = this;
        
        //load html from file
        if(this._need_load_content && this.options.htmlContent){  //load general layout      
            
            var url = this.options.htmlContent.indexOf(window.hWin.HAPI4.baseURL)===0
                    ?this.options.htmlContent
                    :window.hWin.HAPI4.baseURL+'hclient/' 
                        + this.options.path + this.options.htmlContent
                        +'?t='+window.hWin.HEURIST4.util.random();
//console.log(url);        
            
            this.element.load(url, 
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
        
        //find and activate event listeners for elements
        
        if(this.options.isdialog){
            
            this.popupDialog();
            
        }else{
            
            if(this.options.innerTitle){ 

                var fele = this.element.children().get(0);//('fieldset');
                
                //titlebar            
                this._innerTitle = $('<div class="ui-heurist-header" style="top:0px;">'+this.options.title+'</div>')
                .insertBefore(fele);

                this.closeBtn = $('<button>').button({icon:'ui-icon-closethick',showLabel:false, label:window.hWin.HR('Close')}) 
                .css({'position':'absolute', 'right':'4px', 'top':'6px', height:24, width:24})
                .addClass('ui-fade-color')
                .insertBefore( fele );
                this._on(this.closeBtn, {click:function(){
                    this.closeDialog();
                }});
                this.closeBtn.find('.ui-icon-closethick').css({'color': 'rgb(255,255,255)'});
                
                $(fele).css('margin-top', '38px'); //this._innerTitle.height());
                
            }

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
        //if(this.selectRecordScope) this.selectRecordScope.remove();

    },
    
    //----------------------
    //
    // array of button defintions
    //
    _getActionButtons: function(){

        var that = this;        
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
        
        var btn_opts = {label:options.text, icons:options.icons, title:options.title};
        
        var btn = $('<button>').button(btn_opts)
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
        
            var options = this.options,
                btn_array = this._getActionButtons(), 
                that = this;
        
            if(!options.beforeClose){
                    options.beforeClose = function(){
                        //show warning on close
                        //that.saveUiPreferences();
                        return true;
                    };
            }
            
            if(options.position==null) options.position = { my: "center", at: "center", of: window };
            
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
                title: window.hWin.HEURIST4.util.isempty(options['title'])?'':window.hWin.HR(options['title']), //title will be set in  initControls as soon as entity config is loaded
                position: options['position'],
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
            
    },
    
    //
    // show itself as popup dialog
    //
    popupDialog: function(){
        if(this.options.isdialog){

            var $dlg = this._as_dialog.dialog("open");
            
            
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
                var helpURL = window.hWin.HRes( this.options.helpContent )+' #content';
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
            
            var canClose = true;
            if($.isFunction(this.options.beforeClose)){
                canClose = this.options.beforeClose();
            }
            if(canClose){
                if($.isFunction(this.options.onClose)){
                    this.options.onClose( this._context_on_close );
                }
            }
            this.element.hide();
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
    //  after action event handler
    //
    _afterActionEvenHandler: function( response ){
        
            
    },
    
    //
    // Requests reportProgress every t_interval ms 
    // is_autohide 
    //    true  - stops progress check if it returns null/empty value
    //    false - it shows rotating(loading) image for null values and progress bar for n,count values
    //                  in latter case _hideProgress should be called explicitely
    //
/*    
    _showProgress: function ( session_id, is_autohide, t_interval ){

        if(!(session_id>0)) {
             this._hideProgress();
             return;
        }
        var that = this;
       
        var progressCounter = 0;        
        var progress_url = window.hWin.HAPI4.baseURL + "viewers/smarty/reportProgress.php";

        this.element.find('#div_fieldset').hide();
        this.element.find('.ent_wrapper').hide();
        var progress_div = this.element.find('.progressbar_div').show();
        $('body').css('cursor','progress');
        var btn_stop = progress_div.find('.progress_stop').button({label:window.hWin.HR('Abort')});
        
        this._on(btn_stop,{click: function() {
            
                var request = {terminate:1, t:(new Date()).getMilliseconds(), session:session_id};
                window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                    that._hideProgress();
                    if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                        console.error(response);                   
                    }
                });
            }});
        
        var div_loading = progress_div.find('.loading').show();
        var pbar = progress_div.find('#progressbar');
        var progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});
                
        this._progressInterval = setInterval(function(){ 
            
            var request = {t:(new Date()).getMilliseconds(), session:session_id};            
            
            window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
               
                if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    that._hideProgress();
                }else{
                    //it may return terminate,done,
                    var resp = response?response.split(','):[];
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
                            var val = resp[0]*100/resp[1];
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
*/   
  
});

