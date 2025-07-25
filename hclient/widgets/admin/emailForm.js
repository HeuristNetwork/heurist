/**
* @file emailForm.js
* @brief emailForm widget - either creates form or uses a given one.
* @fileOverview
* Sends email to the address defined in the given record ID. If the record is not defined,
* it sends email to the database owner.
*
* @todo Use HBaseView as a parent.
*
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4
*/

/**
 * @namespace Widgets.Admin
 * @description Admin and Db widgets
 */

/**
* @class emailForm
* @memberof Widgets.Admin
* @description either creates form or uses a given one.
*
* @property {object} options - Configuration options for the widget.
*/
$.widget( "heurist.emailForm", {

    /**
     * @memberof Widgets.Admin.emailForm
     * @type {object}
     * @property {string} [default_palette_class='ui-heurist-admin'] - Default CSS class for the widget palette.
     * @property {boolean} [isdialog=false] - If true, the widget is displayed as a dialog. See {@link heurist.emailForm#_initDialog}, {@link heurist.emailForm#popupDialog}, {@link heurist.emailForm#closeDialog}.
     * @property {boolean} [supress_dialog_title=false] - If true, hides the dialog title bar (applicable if `isdialog` is true).
     * @property {number} [height=400] - Height of the popup dialog.
     * @property {number} [width=760] - Width of the popup dialog.
     * @property {object|null} [position=null] - Position of the dialog. See jQuery UI dialog position option.
     * @property {string} [title=''] - Title of the dialog.
     * @property {string|null} [element_id=null] - HTML ID of the form element. If `isdialog` is false, the form is loaded into this element.
     * @property {string} [htmlContent='emailForm.html'] - Path to the HTML file for the form content.
     * @property {string|null} [helpContent=null] - Path to the help content.
     * @property {number|null} [website_record_id=null] - Record ID of the website home page containing the email address.
     * @property {boolean} [useCaptcha=true] - If true, uses CAPTCHA for form submission.
     * @property {function|null} [onInitFinished=null] - Callback function executed when the dialog is fully initialized.
     * @property {function|null} [beforeClose=null] - Callback function executed before the dialog closes. Can be used to show a warning.
     * @property {function|null} [onClose=null] - Callback function executed when the dialog closes.
     * @property {string} [language='def'] - Language code for localization.
     */
    options: {
        default_palette_class: 'ui-heurist-admin',
        isdialog: false,
        supress_dialog_title: false,
        height: 400,
        width:  760,
        position: null,
        title:  '',
        element_id: null,
        htmlContent: 'emailForm.html',
        helpContent: null,
        website_record_id: null,
        useCaptcha: true,
        onInitFinished:null,
        beforeClose:null,
        onClose:null,
        language: 'def'
    },

    /**
     * Reference to the dialog instance if `options.isdialog` is true.
     * @private
     * @type {jQuery|null}
     */
    _as_dialog:null,

    /**
     * jQuery object representing the form element.
     * @private
     * @type {jQuery|null}
     */
    _element_form: null,

    /**
     * Button element to open the dialog.
     * @private
     * @type {jQuery|null}
     */
    _open_button: null,

    /**
     * Send button for inline form.
     * @private
     * @type {jQuery|null}
     */
    _action_button: null,

    /**
     * Flag indicating if HTML content needs to be loaded.
     * @private
     * @type {boolean}
     */
    _need_load_content:true,

    /**
     * Context variable passed to the `options.onClose` event listener.
     * @private
     * @type {boolean}
     */
    _context_on_close:false,


    /**
     * @function _create
     * @description The widget's constructor. Prevents double-click text selection.
     * @memberof Widgets.Admin.emailForm
     * @private
     */
    _create: function() {
        // prevent double click to select text
    }, //end _create

    /**
     * @function _init
     * @description Initializes the widget, loads configuration, and calls `_initControls`.
     * @memberof Widgets.Admin.emailForm
     * @private
     */
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
                {label:window.hWin.HR('Email Us')}) //, icon:options.icon})
            .appendTo(this.element);

            this._element_form.hide();
            this._initDialog();
        }

        //init layout
        let that = this;

        //load html from file
        if(this._need_load_content && this.options.htmlContent){
            let url = this.options.htmlContent.indexOf(window.hWin.HAPI4.baseURL)===0
                    ?this.options.htmlContent
                    :window.hWin.HAPI4.baseURL+'hclient/widgets/admin/'+this.options.htmlContent
                            +'?t='+window.hWin.HEURIST4.util.random();

            this._element_form.load(url,
            function(response, status, xhr){
                that._need_load_content = false;
                if ( status == "error" ) {
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: response,
                        error_title: 'Failed to load HTML content',
                        status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                    });
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

    /**
     * @function _destroy
     * @description Destroys the widget, removing elements and cleaning up.
     * @memberof Widgets.Admin.emailForm
     * @private
     */
    _destroy: function() {
        if(this._element_form) this._element_form.remove();
        if(this._open_button) this._open_button.remove();
        if(this._as_dialog) this._as_dialog.remove();
    },


    /**
     * @function _initControls
     * @description Initializes controls after HTML content is loaded.
     * Verifies that the form has all required elements and sets up event handlers.
     * @memberof Widgets.Admin.emailForm
     * @private
     * @returns {boolean} True if initialization is successful, false otherwise.
     */
    _initControls:function(){
        //verify that form has all required elements
        let missed = [];
        if(!this._element_form.find('#letter_name').length) missed.push('letter_name'); 
        if(!this._element_form.find('#letter_email').length) missed.push('letter_email');
        if(!this._element_form.find('#letter_content').length) missed.push('letter_content');
        if(!this._element_form.find('#captcha').length) missed.push('captcha');

        if(missed.length>0){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: `Email form must have the following html elements: ${missed.join(',')}`,
                error_title: 'Missing required fields',
                status: window.hWin.ResponseStatus.INVALID_REQUEST
            });
            return false;
        }

        window.hWin.HRA(this._element_form);//this.element

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

    /**
     * @function _getActionButtons
     * @description Gets the action buttons for the dialog.
     * @memberof Widgets.Admin.emailForm
     * @private
     * @returns {Array<object>} Array of button definitions for jQuery UI dialog.
     *                          Each object can have `text`, `class`, `css`, and `click` properties.
     */
    _getActionButtons: function(){
        let that = this;
        return [
                 {text:window.hWin.HR('Cancel'),
                    class:'btnCancel',
                    css:{'float':'right','margin-left':'30px','margin-right':'20px'},
                    click: function() {
                        that.closeDialog();
                    }},
                 {text:window.hWin.HR('Send'),
                    class:'ui-button-action btnDoAction',
                    //disabled:'disabled',
                    css:{'float':'right'},
                    click: function() {
                            that.doAction();
                    }}
                 ];
    },

    /**
     * @function _defineActionButton2
     * @description Defines action buttons if `isdialog` is false. (NOT CURRENTLY USED)
     * @memberof Widgets.Admin.emailForm
     * @private
     * @param {object} options - Button options.
     * @param {string} [options.label] - Button label.
     * @param {string} [options.text] - Button text (used if label is not provided).
     * @param {string} [options.icon] - Button icon class.
     * @param {string} [options.title] - Button title.
     * @param {function} options.click - Click event handler.
     * @param {string} [options.id] - Button ID.
     * @param {object} [options.css] - CSS properties for the button.
     * @param {string} [options.class] - CSS class for the button.
     * @param {jQuery} container - jQuery element to append the button to.
     */
    _defineActionButton2: function(options, container){
        //for dialog buttons jquery still uses "text"
        let btn_opts = {label:options.label || options.text, icon:options.icon, title:options.title};

        let btn = $('<button>').button(btn_opts)
                    .on('click',options.click)
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


    /**
     * @function _initDialog
     * @description Initializes the dialog widget.
     * Sets up dialog options, buttons, and event handlers.
     * @memberof Widgets.Admin.emailForm
     * @private
     * @see Widgets.Navigation.emailForm#popupDialog
     * @see Widgets.Navigation.emailForm#closeDialog
     */
    _initDialog: function(){
            let options = this.options,
                btn_array = this._getActionButtons();
            const that = this;

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

            let $dlg = this._element_form.dialog({
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
                    if(window.hWin.HEURIST4.util.isFunction(that.options.onClose)){
                      //that.options.onClose(that._currentEditRecordset);
                      that.options.onClose( that._context_on_close );
                    }
                    //that._as_dialog.remove();
                },
                buttons: btn_array
            });
            this._as_dialog = $dlg;
    },

    /**
     * @function popupDialog
     * @description Shows the widget as a popup dialog.
     * This method is called when `options.isdialog` is true.
     * It opens the jQuery UI dialog and applies necessary styling and help content.
     * @memberof Widgets.Admin.emailForm
     */
    popupDialog: function(){
        if(this.options.isdialog){
            window.hWin.HRA(this._element_form);//this.element

            let $dlg = this._as_dialog.dialog("open");

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
                let helpURL = window.hWin.HRes( this.options.helpContent )+' #content';
                window.hWin.HEURIST4.ui.initDialogHintButtons(this._as_dialog, null, helpURL, false);
            }
        }
    },

    /**
     * @function closeDialog
     * @description Closes the dialog or handles the close action for an inline form.
     * Clears the form fields and refreshes CAPTCHA.
     * @param {boolean} [is_force=false] - If true, forces the dialog to close without triggering `beforeClose`.
     * @memberof Widgets.Admin.emailForm
     */
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

    /**
     * @function doAction
     * @description Handles the form submission (send email action).
     * Validates form fields, including CAPTCHA.
     * If validation passes, it sends the email data to the server.
     * Displays success or error messages accordingly.
     * @memberof Widgets.Admin.emailForm
     */
    doAction: function(){
        let that = this;

        //all fields are mandatory
        let allFields = this._element_form.find('[required="required"]');
        let err_text = '';

        // validate mandatory fields
        allFields.each(function(){
            let input = $(this);
            if(input.attr('required')=='required' && input.val()=='' ){
                input.addClass( "ui-state-error" );
                err_text = err_text + ', '+that._element_form.find('label[for="' + input.attr('id') + '"]').html();
            }
        });

        //verify captcha
        //remove/trim spaces
        let ele = this._element_form.find("#captcha");
        let val = ele.val().trim().replace(/\s+/g,'');

        let ss = window.hWin.HEURIST4.msg.checkLength2( ele, '', 1, 0 );
        if(ss!=''){
            err_text = err_text + ', '+window.hWin.HR('Prove you are human');
        }else{
            ele.val(val);
        }

        if(err_text==''){
            //
            // validate email
            //
            let email = this._element_form.find("#letter_email");
            let bValid = window.hWin.HEURIST4.util.checkEmail(email);
            if(!bValid){
                err_text = err_text + ', '+window.hWin.HR('Email does not appear to be valid');
            }
            if(err_text!=''){
                err_text = err_text.substring(2);
            }
        }else{
            err_text = window.hWin.HR('Missing required fields')+': '+err_text.substring(2);
        }

        if(err_text==''){
            let fields = {
                website_id: this.options.website_record_id,
                person: this._element_form.find('#letter_name').val(),
                email: this._element_form.find('#letter_email').val(),
                content: this._element_form.find('#letter_content').val(),
                captcha: this._element_form.find("#captcha").val()
                //,captchaid: this._element_form.find("#captchaid").val()
            };

            let request = {
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
            window.hWin.HEURIST4.msg.showMsgErr({
                message: err_text,
                error_title: 'Invalid field values',
                status: window.hWin.ResponseStatus.INVALID_REQUEST
            });
        }
    },

    /**
     * @function _refreshCaptcha
     * @description Refreshes the CAPTCHA image or text.
     * Clears the CAPTCHA input field and loads a new CAPTCHA.
     * @memberof Widgets.Admin.emailForm
     * @private
     */
    _refreshCaptcha: function(){
        this._element_form.find('#captcha').val('');
        let $dd = this._element_form.find('#captcha_img');
        let id = window.hWin.HEURIST4.util.random();
        const is_simple_captcha = true;
        if(is_simple_captcha){  //simple captcha
            let that = this;

            const url = window.hWin.HAPI4.baseURL+'hserv/utilities/captcha.php?json&id='+id;

            $.getJSON(url,
                function(captcha){
                        that._element_form.find('#captcha_img').text(captcha.value);
                        that._element_form.find('#captchaid').val(captcha.id);
                    });

        // }else if(false){


        }else{ //image captcha
            $dd.empty();
            $('<img alt src="'+window.hWin.HAPI4.baseURL+'hserv/utilities/captcha.php?img='+id+'"/>').appendTo($dd);
        }
    },
});