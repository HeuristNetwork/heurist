/**
* @file reportSelector.js
* @brief Provides a widget so select/add/edit template for given record type.
* @fileOverview 
*
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

/**
 * @widget heurist.reportSelector
 * @augments $.heurist.baseAction
 * @description
 * jQuery UI widget so select/add/edit template for given record type
 */
$.widget( "heurist.reportSelector", $.heurist.baseAction, {

    /**
     * @memberof heurist.reportEditor
     * @instance
     * @property {Object} options - Default options for the widget.
     * @property {number} options.height - Default height of the editor dialog.
     * @property {number} options.width - Default width of the editor dialog.
     * @property {string} options.title - Default title of the editor dialog.
     * @property {string} options.default_palette_class - CSS class for the default palette.
     * @property {string} options.actionName - Name of the action.
     * @property {string} options.htmlContent - HTML file for the widget's content.
     * @property {?number} options.rty_ID - Record Type ID.
     */
    options: {
        height: 300,
        width:  450,
        title:  'Custom Report Selector',
        default_palette_class: 'ui-heurist-populate',
        actionName: 'reportSelector',
        htmlContent: 'reportSelector.html',
        path: 'widgets/report/',

        rty_ID:null, 
        rec_ID: null
    },
    
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Widget creation method. Initializes options and sets up beforeClose behavior.
     */
    _create: function() {
        this._super();
        
        //this.options.beforeClose = ()=>return this._beforeClose();

    }, //end _create
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Widget initialization method. Loads the template if already initialized.
     */
    _init: function() {
        
        this._super();
        
    },
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Widget destruction method. Removes the temporary test form.
     */
    _destroy: function() {
    },

   
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Initializes controls after HTML content is loaded. Sets up layout, CodeMirror, and event handlers.
     * @returns {boolean} False if superclass initialization fails, otherwise true.
     */
    _initControls: function(){
        
        const res  = this._super();
        if(!res) return false;
        
        this._updateTemplatesList();
        
        this._context_on_close = null;
        
        this._on(this._$('#lnkMinTemplate'), {click:()=>this._onTemplateEdit(true)});

        this._on(this._$('#lnkDefTemplate'), {click:()=>{
                window.hWin.HAPI4.actionHandler.executeActionById('menu-profile-preferences');
                this.closeDialog();
        }});
        
        return true;
    },
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @description Saves the current template. Handles "Save As" functionality and prompts for a name if needed.
     * @param {boolean} [is_save_as=false] - If true, prompts for a new template name.
     * @param {boolean} [need_close=false] - If true, closes the dialog after saving.
     */
    doAction: function(){
        
        this._currentTemplate = this._$('#selTemplates').val();
        this._context_on_close = null;
        
        const action = this._$('input[name="template_action"]:checked').val();
        
        if(action=='use'){
            //reload viewer tab with given template
            this._context_on_close = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                +'&template='+this._currentTemplate+'&q=ids:'+this.options.rec_ID;
        }else if(action=='setdefault'){
            
            window.hWin.HAPI4.save_pref('main_recview', this._currentTemplate);
            this._context_on_close = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                +'&template='+this._currentTemplate+'&q=ids:'+this.options.rec_ID;
            
        }else if(action=='new'){
            //create new template based on selected one
            this._onTemplateEdit(true, this._currentTemplate);
        }else if(action=='edit'){
            //open editor
            this._onTemplateEdit(false);
        }
        
        this.closeDialog();
    },
    
    _updateTemplatesList: function(template_to_select) {
        
        this._currentTemplate = template_to_select;// || window.hWin.HAPI4.get_prefs('viewerCurrentTemplate');
        
        let sel = this._$('#selTemplates');
        sel.empty();
        this._off(sel,'change');
        
        window.hWin.HEURIST4.ui.createTemplateSelector(sel, [{key:'',title:'select ...'}], this._currentTemplate, null);
        
        this._on(sel,{change:(event)=>{
                let template_file = $(event.target).val();
                window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), template_file===''); 
        }});
    },        

    _onTemplateEdit: function(isNew, basedOn) {
        let that = this;
        
        let popup_dialog_options = {path: 'widgets/report/', 
                    keep_instance:true, 
                    template: isNew?null:this._currentTemplate,
                    basedOn: basedOn, //@TODO
                    onClose: function(is_update_list){
                        if(is_update_list){
                            that._updateTemplatesList();
                        }
                    }
        };
        window.hWin.HEURIST4.ui.showRecordActionDialog('reportEditor', popup_dialog_options);
    },
    
        
});

