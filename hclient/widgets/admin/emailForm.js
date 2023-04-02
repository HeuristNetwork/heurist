
/**
* emailForm widget - either creates form or use given one
* send email to address defined in given record id, if record not define 
* it sends email to database owner
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.emailForm", {

    // default options
    options: {
    
        default_palette_class: 'ui-heurist-admin', 
        
        //DIALOG section       
        isdialog: false,    // show as dialog @see  _initDialog(), popupDialog(), closeDialog
        supress_dialog_title: false, //hide dialog title bar (applicable if isdialog=true
        
        //if isdialog is false it loads form to this element
        
        
        height: 400, //size for popup dialog
        width:  760,
        //modal:  true,
        position: null,
        title:  '',
        
        element_id: null, // form html id
        
        htmlContent: 'emailForm.html',
        helpContent: null,
        
        website_record_id: null, //website home page record id with email
        useCaptcha: true,
        
        //listeners
        onInitFinished:null,  //event listener when dialog is fully inited - use to perform initial search with specific parameters
        beforeClose:null,     //to show warning before close
        onClose:null 
        
    },
    
    _as_dialog:null, //reference to itself as dialog (see options.isdialog)

    _element_form: null,

    _open_button: null, //button to open dialog
    _action_button: null, //send button for inline
    
    _need_load_content:true,
    
    _context_on_close:false, //variable to be passed to options.onClose event listener
    
    
    // the widget's constructor
    _create: function() {
        // prevent double click to select text
        //it prevents inputs in FF this.element.disableSelection();
    }, //end _create
    
    //
    //  load configuration and call _initControls
    //
    _init: function() {
        
        
        if(this.options.element_id){
            if(typeof this.options.element_id === 'string' &&
                this.options.element_id.indexOf('#')!==0){
                this.options.element_id = '#'+this.options.element_id;
            }
            
            this._element_form = $(this.options.element_id);    
        }
        
        if(!this._element_form || this._element_form.length==0){
            this._element_form = $('<div>').appendTo(this.element);
        }
        
        
        if(this.options.isdialog){  //show this widget as popup dialog
            
            this._open_button = $('<button>').button(
                {label:window.hWin.HR('Email Us')}) //, icons:options.icons})
            .appendTo(this.element);
            
            this._element_form.hide()
            this._initDialog();
        }else{
            //this.element.addClass('ui-heurist-bg-light');
        }
        
        //init layout
        var that = this;
        
        //load html from file
        if(this._need_load_content && this.options.htmlContent){        
            
            var url = this.options.htmlContent.indexOf(window.hWin.HAPI4.baseURL)===0
                    ?this.options.htmlContent
                    :window.hWin.HAPI4.baseURL+'hclient/widgets/admin/'+this.options.htmlContent
                            +'?t='+window.hWin.HEURIST4.util.random();
            
            this._element_form.load(url, 
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
    
    _destroy: function() {
        
        if(this._element_form) this._element_form.remove();    
        if(this._open_button) this._open_button.remove();    
        if(this._as_dialog) this._as_dialog.remove();    
    },
     
    //  
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        
        var that = this;
        
        //verify that form has all required elements
        var missed = [];
        if(!this._element_form.find('#letter_name')) missed.push('letter_name');
        if(!this._element_form.find('#letter_email')) missed.push('letter_email');
        if(!this._element_form.find('#letter_content')) missed.push('letter_content');
        if(!this._element_form.find('#captcha')) missed.push('captcha');
        
        if(missed.length>0){
            window.hWin.HEURIST4.msg.showMsgErr('Email form must have the following html elements: '+missed.join(','));            
        }
        
        this._refreshCaptcha();
        
        if(this.options.isdialog){
            
            this._on(this._open_button, {click:this.popupDialog});
            
        }else{
            //adds/inits buttons in form
            this._action_button = this._element_form.find('#btnSend');
            if(!this._action_button || this._action_button.length==0){
                this._action_button = $('<button>')
                    .button({label:window.hWin.HR('Email Us')})
                    .appendTo($('<div>').css({'text-align':'center'}).appendTo(this._element_form));
            }
            this._on(this._action_button, {click:this.doAction});
        }
        
        return true;
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
                 {text:window.hWin.HR('Send'),
                    id:'btnDoAction',
                    class:'ui-button-action',
                    //disabled:'disabled',
                    css:{'float':'right'},  
                    click: function() { 
                            that.doAction(); 
                    }}
                 ];
    },

    //
    // define action buttons (if isdialog is false)
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
            
            var $dlg = this._element_form.dialog({
                autoOpen: false ,
                //element: this.element[0],
                height: options['height'],
                width:  options['width'],
                //modal:  (options['modal']!==false),
                title: this.options.title? this.options.title:window.hWin.HR('Email Us'), //title will be set in  initControls as soon as entity config is loaded
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
                    //that._as_dialog.remove();    
                        
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
                this._element_form.removeClass('ui-heurist-bg-light');
            }else{
                this._as_dialog.attr('data-palette', null);
                this._element_form.addClass('ui-heurist-bg-light');
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
        //clear form
        
        this._refreshCaptcha();
        this._element_form.find('#letter_name').val('');
        this._element_form.find('#letter_email').val('');
        this._element_form.find('#letter_content').val('');
        
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
        }
    },

    //
    //
    //
    doAction: function(){
        
        var that = this;
        
        //all fields are mandatory
        var allFields = this._element_form.find('[required="required"]');
        var err_text = '';

        // validate mandatory fields
        allFields.each(function(){
            var input = $(this);
            if(input.attr('required')=='required' && input.val()=='' ){
                input.addClass( "ui-state-error" );
                err_text = err_text + ', '+that._element_form.find('label[for="' + input.attr('id') + '"]').html();
            }
        });
        
        //verify captcha
        //remove/trim spaces
        var ele = this._element_form.find("#captcha");
        var val = ele.val().trim().replace(/\s+/g,'');

        var ss = window.hWin.HEURIST4.msg.checkLength2( ele, '', 1, 0 );
        if(ss!=''){
            err_text = err_text + ', Humanity check';
        }else{
            ele.val(val);
        }

        if(err_text==''){
            //
            // validate email
            // 
            var email = this._element_form.find("#letter_email");
            var bValid = window.hWin.HEURIST4.util.checkEmail(email);
            if(!bValid){
                err_text = err_text + ', '+window.hWin.HR('Email does not appear to be valid');
            }
            if(err_text!=''){
                err_text = err_text.substring(2);
            }


        }else{
            err_text = window.hWin.HR('Missing required field(s)')+': '+err_text.substring(2);
        }
        
        if(err_text==''){
            var fields = {
                website_id: this.options.website_record_id,
                person: this._element_form.find('#letter_name').val(),
                email: this._element_form.find('#letter_email').val(),
                content: this._element_form.find('#letter_content').val(),
                captcha: this._element_form.find("#captcha").val()
                //,captchaid: this._element_form.find("#captchaid").val()
            }

            var request = {
                'a'          : 'save',
                'entity'     : 'sysBugreport',
                'request_id' : window.hWin.HEURIST4.util.random(),
                'captchaid'  : this._element_form.find("#captchaid").val(),
                'fields'     : fields
            };
            
            window.hWin.HEURIST4.msg.bringCoverallToFront(this._element_form);
         
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    window.hWin.HEURIST4.msg.sendCoverallToBack();

                    if(response.status == window.hWin.ResponseStatus.OK){

                        window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Email has been sent'));
                        that.closeDialog(true); //force to avoid warning
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);    
                        that._refreshCaptcha();
                    }
            });


        }else{
            window.hWin.HEURIST4.msg.showMsgErr(err_text);
        }
    },
    
    //
    //
    //
    _refreshCaptcha: function(){

        this._element_form.find('#captcha').val('');
        var $dd = this._element_form.find('#captcha_img');
        var id = window.hWin.HEURIST4.util.random();
        if(true){  //simple captcha
            var that = this;
            
            var url = window.hWin.HAPI4.baseURL+'hsapi/utilities/captcha.php?json&id='+id;
            
            //var request = {json:1,id:id};
            //window.hWin.HEURIST4.util.sendRequest(url, request, null, 
            $.getJSON(url,
                function(captcha){
                        that._element_form.find('#captcha_img').text(captcha.value)
                        that._element_form.find('#captchaid').val(captcha.id);
                    });

        
        }else if(false){
            var url = window.hWin.HAPI4.baseURL+'hsapi/utilities/captcha.php?id='+id;
            $dd.load(url);
        }else{ //image captcha
            $dd.empty();
            $('<img src="'+window.hWin.HAPI4.baseURL+'hsapi/utilities/captcha.php?img='+id+'"/>').appendTo($dd);
        }
    },
    

});