/**
* @file editTranslations.js
* @brief Widget for editing multi-language translations
* 
* @description A jQuery UI widget for editing translations of a text field into multiple languages.
*              It supports manual entry and an optional automatic translation feature if configured.
*              The widget can be displayed as a dialog or embedded directly.
* 
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

/**
 * @class editTranslations
 * @memberof Widgets.Editing
 * @description Creates an editor for managing text translations in multiple languages.
 * The editor allows adding new language entries, inputting translations,
 * and optionally using an automatic translation service.
 *
 * @example
 * // Initialize with default options in a dialog
 * $('<div>').editTranslations({
 *   values: ['eng:Hello', 'fra:Bonjour'],
 *   onclose: function(translations) {
 *     console.log('Saved translations:', translations);
 *   }
 * });
 *
 * @example
 * // Initialize embedded in an existing element
 * $('#myTranslationsEditor').editTranslations({
 *   is_dialog: false,
 *   values: ['Default text'],
 *   fieldtype: 'blocktext',
 *   onclose: function(translations) {
 *     // Handle updated translations
 *   }
 * });
 */
$.widget( "heurist.editTranslations", {

    /**
    * @memberof Widgets.Editing.editTranslations
    * @type {object}
    * @instance
    * 
    * @property {boolean} [is_dialog='true'] - Determines if the widget is displayed within a modal dialog or embedded directly into the target element.
    * @property {string} [fieldtype='freetext'] - Specifies the type of input field to use for translations. 'freetext' renders a single-line input; 'blocktext' renders a multi-line textarea.
    * @property {Array<string>} [values=[""]] - An array of initial translation strings. Each string can be a simple text (considered default language) or prefixed with a language code (e.g., "eng:Hello", "fra:Bonjour"). ISO639-1 (2-letter) or ISO639-2 (3-letter) codes are supported.
    * @property {function(Array<string>):void|null} [onclose=null] - A callback function executed when the "Apply" button is clicked in dialog mode, or when changes are intended to be saved.
    * The callback receives an array of strings representing the current translations, formatted with language prefixes where applicable (e.g., "fra:Bonjour").
    */
    options: {
        is_dialog: true,
        fieldtype: 'freetext',
        values: [''],
        onclose: null
    },
    
    /**
     * Reference to the jQuery UI dialog instance if `options.is_dialog` is true.
     * @private
     * @type {jQuery|null}
     */
    _as_dialog:null,
    
    /**
     * Flag indicating whether the translation values have been changed by the user.
     * @private
     * @type {boolean}
     * @default false
     */
    _was_changed: false,

    /**
     * Main container element for the widget's UI, holding language entries.
     * @private
     * @type {jQuery|null}
     */
    _container: null,

    /**
     * jQuery object for the "Add language" button.
     * @private
     * @type {jQuery|null}
     */
    _btn_add: null,

    /**
     * jQuery object for the "Add translation" (automatic translation) button.
     * @private
     * @type {jQuery|null}
     */
    _btn_translate: null,

    /**
     * Initializes the widget. Sets up the main container and dialog if applicable.
     * Creates the initial form structure.
     * @private
     */
    _init: function() {

        this._container = $('<div class="ent_content_full" style="top:0;padding:10px"></div>')
                    .appendTo( $('<div class="ent_wrapper">').appendTo(this.element) );

        let that = this;

        if(this.options.is_dialog){
            
            let $dlg;
            
            let arrButtons = {};
            arrButtons[window.hWin.HR('Apply')] = function() {
                that._onCloseDialog();
                if(that._as_dialog && that._as_dialog.dialog('instance')) that._as_dialog.dialog( "close" );
            };
            arrButtons[window.hWin.HR('Cancel')] = function() {
                if(that._as_dialog && that._as_dialog.dialog('instance')) that._as_dialog.dialog( "close" );
            };

            $dlg = this.element.dialog({
                autoOpen: true,
                height: 240,
                width: 840,
                modal: true,
                title: 'Define Translations',
                buttons: arrButtons,
                resizeStop: function( event, ui ) {
                    let pele = that.element.parents('div[role="dialog"]');
                    that.element.css({overflow: 'none !important', width:pele.width()-24 });
                },
                close:function(){
                    that._as_dialog.remove();    
                }
            });
            
            $dlg.parent().addClass('ui-heurist-populate');
            
            this._as_dialog = $dlg; 
        }
        
        //create edit form with list of inputs
        this._createForm();        
        
    }, //end _create
    
    //
    //
    //
    _createForm: function(){

        const that = this;
        
        //create - define new language button
        /**
         * @private
         * @type {jQuery}
         */
        this._btn_add = $('<div class="btn_lang_add" style="margin:10px 10px;color:#6A7C99;cursor:pointer;font-size:0.9em;display:inline-block;">'
        +'<span class="ui-icon ui-icon-plus" style="font-size:1em;margin-left: 95px;"></span> Add language'
        +'</div>')
        .appendTo( this._container );
        /**
         * @private
         * @type {jQuery}
         */
        this._btn_translate = $('<div class="btn_translate_add" style="margin:10px 0px;color:#6A7C99;cursor:pointer;font-size:0.9em;display:inline-block;">'
        +'<span class="ui-icon ui-icon-plus" style="font-size:1em;margin-left:25px;"></span> Add translation'
        +'</div>')
        .appendTo( this._container ).hide();
        
        for (let i=0; i<this.options.values.length; i++){
            
            this._createEntry( this.options.values[i] , true);

        }//for
        
        this._adjustDimension();
        
        this._on(this._btn_add, {click:function(){
            //take defaul value
            let ele = this.element.find('[data-def=1]');
            
            this._createEntry(ele.val(), false);
            this._adjustDimension();
        }});

        this._on(this._btn_translate, {
            click: function(){

                let $dlg;

                let msg = 'Language: <select id="selLang"></select><br><br>'
                        + 'You may block translation of some part of the text by adding an html tag with translate="no",<br>'
                        + 'for example:  &lt;span translate=”no”&gt;text not to be translated&lt;/span&gt;';

                let btns = {};
                let labels = {yes: window.HR('Add'), no: 'Cancel', title: 'Add automatic translation'};

                btns[window.HR('Add')] = function(){

                    let first_val = that.element.find('[data-def=1]').val();
                    first_val = window.hWin.HEURIST4.util.isempty(first_val) ? this.element.find('input:first').val() : first_val;

                    let target = $dlg.find('#selLang').val();

                    if(window.hWin.HEURIST4.util.isempty(first_val)){
                        window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value to translate in the first input...', 3000);
                        return;
                    }else if(window.hWin.HEURIST4.util.isempty(target)){
                        window.hWin.HEURIST4.msg.showMsgFlash('Select a language...', 3000);
                        return;
                    }

                    let source = '';

                    if(first_val.match(/^\w{3}:/)){ // check for a source language

                        // Pass as source language
                        source = first_val.match(/^\w{3}:/)[0];
                        source = source.slice(0, -1);

                        first_val = first_val.slice(4); // remove lang prefix
                    }

                    let request = {
                        a: 'translate_string',
                        string: first_val,
                        target: target,
                        source: source
                    };

                    window.hWin.HAPI4.SystemMgr.translate_string(request, function(response){

                        $dlg.dialog('close');

                        if(response.status == window.hWin.ResponseStatus.OK){
                            let new_value = target + ':' + response.data;
                            that._was_changed=true;
                            that._createEntry(new_value, true);
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });

                };

                btns[window.HR('Cancel')] = function(){
                    $dlg.dialog('close');
                };


                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, labels, {default_palette_class: 'ui-heurist-populate'});

                window.hWin.HEURIST4.ui.createLanguageSelect($dlg.find('#selLang'), null, null, true);
            }
        })
    },        
    
    /**
     * Adjusts the height of the widget or its containing dialog based on content.
     * @private
     */
    _adjustDimension: function(){
        
        
        let ch = this.element.find('div.ent_content_full')[0].scrollHeight;
 
        if(ch<150) ch = 150;

        let topPos = 0;
        if(this.options.is_dialog){        
            let pos = this._as_dialog.dialog('option', 'position');
            if(pos && pos.of && !(pos.of instanceof Window)){
                let offset = $(pos.of).offset();
                topPos = (offset?offset.top:0)+40;
            }
            
            ch = ch + 80;

            let ht = Math.min(ch, window.innerHeight-topPos);

            this._as_dialog.dialog('option', 'height', ht);    
        }else{
            topPos = this.element.parent().offset().top + 10;
            
            if(ch > window.innerHeight-topPos){
                ch = window.innerHeight-topPos;
            }
            
            this.element.parent().height(ch);
        }
                
    },
    

    /**
     * Creates a single translation entry, consisting of a language selector (for non-default)
     * and an input field (text or textarea).
     * @private
     * @param {string} value - The initial value for the input field, possibly prefixed with a language code (e.g., "eng:text").
     * @param {boolean} check_default - If true, determines if this entry is the default language
     *                                  based on the absence of a language prefix in `value`.
     *                                  If false, a language selector is always created.
     */
    _createEntry: function(value, check_default){

        let sel_container, values_container, input_ele;
        let that = this; 
        
        let cont = $('<div>').css({margin:'5px'}).insertBefore(this._btn_add);
        
        // selector container - to select language
        sel_container = $('<div>') 
            .css({'display':'inline-block','vertical-align':'top','padding-top':'3px','min-width':'100px'})
            .appendTo(cont);
        // values container
        values_container = $( '<div>' )
            .css({'display':'inline-block','padding':'3px'}) //,'margin-bottom': '2px'
            .appendTo( cont );
            
        
        let _is_default = false;    
        let lang = '';
        
        if(check_default){
            
            if(!window.hWin.HEURIST4.util.isempty(value)){
                if(value.slice(3,4)==':'){ //ISO639-2
                    lang = value.slice(0,3);
                    value = value.slice(4).trim();
                }else if(value.slice(2,3)==':'){ //ISO639-1
                    lang = value.slice(0,2);
                    value = value.slice(3).trim();

                    lang = window.hWin.HAPI4.getLangCode3(lang); //convert to ISO639-2
                }
                lang = lang.toUpperCase();
            }
            
            _is_default = window.hWin.HEURIST4.util.isempty(lang);
        }
        
        if(this.options.fieldtype=='blocktext')
        {
            input_ele = $( "<textarea>",{rows:4}) //min number of lines
                    .css({'overflow-x':'hidden'})
                    .on('keydown', function(e){
                        if (e.keyCode == 65 && e.ctrlKey) {
                            e.target.select()
                        }    
                    });

        }else{
            input_ele = $( "<input>");
            
        }
        
        input_ele.uniqueId()
                    .addClass('text ui-widget-content ui-corner-all')
                    .css({width:'680px'})
                    .val(value)
                    .on('keyup', function(){
                        that._was_changed=true;
                        if(!window.hWin.HEURIST4.util.isempty($(this).val()) && window.hWin.HAPI4.sysinfo.api_Translator){
                            that._btn_translate.show();
                        }else{
                            that._btn_translate.hide();
                        }
                    })
                    .on('change', function(){
                        that._was_changed=true;
                        if(!window.hWin.HEURIST4.util.isempty($(this).val()) && window.hWin.HAPI4.sysinfo.api_Translator){
                            that._btn_translate.show();
                        }else{
                            that._btn_translate.hide();
                        }
                    })
                    .appendTo( values_container );
                    
        if(_is_default){
            input_ele.attr('data-def',1);
        }
        
        let inpt_id = input_ele.attr('id');

        if(_is_default){
            //label for first value
            $('<div class="header_narrow field_header" '
            +'style="min-width:90px;display:inline-block;text-align:right;padding-right: 9px;">'
            +'<label>Default language</label></div>')
                .appendTo( sel_container );
        }else{
            let ind = -1;

            //find last seleted
            if(!check_default){
                let ele = this.element.find('select').last();
                if(ele.length>0){
                    ind = ele[0].selectedIndex;
                    if(ind<0) ind = 0;
                }
            }
            
            // 2. field selector for field or links tokens
            let sel = $( '<select>' )
                .attr('title', 'Select language' )
                .attr('data-input-id', inpt_id)
                .addClass('text ui-corner-all')
                .css({'margin-left':'2em','min-width':'70px','max-width':'70px'})
                .hide()
                .appendTo( sel_container );
                
                
            window.hWin.HEURIST4.ui.createLanguageSelect(sel, null, lang, false);
            
            if(ind>=0){
                sel[0].selectedIndex = ind + 1;
                sel.hSelect('refresh');
            }
            
        }

        let first_val = this.element.find('[data-def=1]').val();
        first_val = window.hWin.HEURIST4.util.isempty(first_val) ? this.element.find('input:first').val() : first_val;
        if(!window.hWin.HEURIST4.util.isempty(first_val) && window.hWin.HAPI4.sysinfo.api_Translator){
            this._btn_translate.show();
        }else{
            this._btn_translate.hide();
        }
    },
    
    /**
     * Called when the dialog is applied/closed. Collects all translation values,
     * formats them with language prefixes, and triggers the `options.onclose` callback
     * if changes were made.
     * @private
     */
    _onCloseDialog: function(){

        if(this._was_changed){
            
            let that = this;
            
            let eles = this.element.find('textarea,input');
            
            let res = [];
            
            eles.each(function(i, item){
                item = $(item); 
                let val = item.val().trim();
                if(!window.hWin.HEURIST4.util.isempty(val)){
                    //find language
                    let sel = that.element.find('select[data-input-id="'+item.attr('id')+'"]');
                    if(sel.length>0 && sel.val() && !item.attr('data-def')){
                        res.push(sel.val()+':'+val);
                    }else{
                        res.push(val);
                    }
                }
                
            });

            if(window.hWin.HEURIST4.util.isFunction(this.options.onclose)){
                this.options.onclose.call(this, res);        
            }
                
        }
    },


    /**
     * Standard jQuery UI widget refresh method. Currently not implemented.
     * @private
     */
    _refresh: function(){
    },
    
    /**
     * Standard jQuery UI widget destroy method.
     * Cleans up elements created by the widget.
     * In this implementation, it primarily relies on jQuery UI's default behavior
     * for removing elements and event handlers bound via `_on`.
     * If `is_dialog` was true, the dialog element itself is removed in the `close` handler of the dialog.
     * @private
     */
    _destroy: function() {
        // remove generated elements
        this._container.remove();
        this._btn_add.remove();
        this._btn_translate.remove();
    },


});
