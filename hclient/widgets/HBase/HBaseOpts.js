/**
 * @file HBaseOpts.js
 * @brief base widget for form to modify widget options  (in CMS editor)
 * @fileOverview
 * @project     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @since       7.0
 */
import '../HBase/HBaseView.js';

/**
 * @class HBaseOpts
 * @augments {HBaseView}
 * @memberof Widgets.UI
 * @description base widget for form to modify widget options  (in CMS editor)
 * @param {object} options - Configuration options for the widget.
 */
$.widget( 'heurist.HBaseOpts', $.heurist.HBaseView, {
    
    /**
     * @memberof Widgets.UI.HBaseOpts
     * @type {object}
     * @property {number} height - The height of the editor.
     * @property {string} title - The title of the editor.
     * @property {string} default_palette_class - The default palette class.
     * @property {string} resourcePath - The path to the form html.
     * @property {object} menuParent - The parent element for HSelect.
     * @property {object} editOptions - The options to edit.
     * @property {function} onChange - The function to call when the options change.
     */
    options: {
        height: 600,
        title: 'Options editor',
        default_palette_class: 'ui-heurist-publish',
        resourcePath: '', //relative path+filename to form html
        menuParent: null, //parent element for HSelect
        editOptions: {},
        onChange: null
    },

    /**
     * @private
     * @memberof Widgets.UI.HBaseOpts
     * @description Use it a) to add event listeners for subelements of this widget
     * b) perform some default actions (intial search for example)
     */
    _initControls:function(){
        
        this._super();
        
        this.show();  
        
        //Init some controls
        this._$('select').each((i,selObj)=>{
            selObj = window.hWin.HEURIST4.ui.initHSelect(selObj,false,null,null,{menu_parent: this.options.menuParent });
        });
        
        this._$('#tabs').tabs();

    },

    /**
     * @private
     * @memberof Widgets.UI.HBaseOpts
     * @description Cleanup. Removes generated elements and off event listeners
     */
    _destroy: function() {
        // remove generated elements
        this._super();
    },
    
    /**
     * @memberof Widgets.UI.HBaseOpts
     * @description Displays options editor
     * @param {object} editOptions - options object to be edited
     */
    show: function(editOptions) {
        this._super();
        
        if(editOptions){
            this.options.editOptions = editOptions;  
        } 
        this._fillControls();
    },
    
    /**
     * @private
     * @memberof Widgets.UI.HBaseOpts
     * @description Gets the action buttons.
     * @returns {Array} The action buttons.
     */
    _getActionButtons: function() {
        let btns = this._super();
        let that = this;
        btns.push(
        {text:window.hWin.HR('Apply'),
                    class:'ui-button-action btnDoAction',
                    //disabled:'disabled',
                    css:{'float':'right'},  
                    click: () => that._applyChanges()
        });
        return btns;
    }, 
    
    /**
     * @private
     * @memberof Widgets.UI.HBaseOpts
     * @description from editOptions to UI
     */
    _fillControls: function(){

        let that = this;
        let allFields = this._$('input,select,textarea');
        if(!that.options.editOptions) that.options.editOptions = {};
        //from prefs to ui
        allFields.each(function(){
            let opt = that.options.editOptions[this.name];
            if(opt){
                if(this.type=="checkbox"){
                    this.checked = (opt=='1' || opt=='true')
                }else if(window.hWin.HEURIST4.util.isJSON(opt)){
                    $(this).val(JSON.stringify(opt));
                }else{
                    $(this).val(opt);
                }
            };
        });
        
        if (this.$H.isFunction(this.options.onChange)) { 
            //event listeners for all input,select and textarea
            this._on(allFields, {change:this._triggerOnChange});
        }
    },
    
    /**
     * @private
     * @memberof Widgets.UI.HBaseOpts
     * @description Triggers the onChange event.
     */
    _triggerOnChange(){
        this._getEditOptions();
        this.options.onChange.call(this, this.options.editOptions);   
    },

    /**
     * @private
     * @memberof Widgets.UI.HBaseOpts
     * @description Gets the edit options.
     */
    _getEditOptions: function(){
        
        let that = this;
        let allFields = this._$('input,select,textarea');
        
        that.options.editOptions = {};

        allFields.each(function(){
            if(this.type=="checkbox"){
                that.options.editOptions[this.name] = this.checked;
            }else if(!($(this).val()=='' || ($(this).is('select') && $(this)[0].selectedIndex==0))) {
                that.options.editOptions[this.name] = $(this).val();
            }
        });
console.log(this.options.editOptions);        
    },

    /**
     * @private
     * @memberof Widgets.UI.HBaseOpts
     * @description from UI to editOptions
     */
    _applyChanges: function(){

        this._getEditOptions();    
        this._contextOnClose = this.options.editOptions;
        this.close(true);
    }
    
});
