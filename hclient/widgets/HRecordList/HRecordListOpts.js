/**
 * @file HRecordListOpts.js
 * @brief form to modify HRecordList options
 * @fileOverview
 * @project     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @since       7.0
 */
import '../HBase/HBaseOpts.js';

/**
 * @class HRecordListOpts
 * @augments {HBaseOpts}
 * @memberof Widgets.UI
 * @description form to modify HRecordList options
 * @param {object} options - Configuration options for the widget.
 */
$.widget( 'heurist.HRecordListOpts', $.heurist.HBaseOpts, {
    
    /**
     * @memberof Widgets.UI.HRecordListOpts
     * @type {object}
     * @property {string} resourcePath - The path to the widget's resources.
     */
    options: {
        resourcePath: 'hclient/widgets/HRecordList/HRecordListOpts',
    },
    
    /**
     * @private
     * @memberof Widgets.UI.HRecordListOpts
     * @description Fills the controls.
     */
    _fillControls: function(){
        
        this._super();
        
        this._$('#placeholderInitDef').text(this.options.editOptions.placeholderInitDef);
        
        this._$('#placeholderEmptyDef').text(this.options.editOptions.placeholderEmptyDef);
        
        let helpURL = window.hWin.HRes( 'HRecordList.htm' );
        this._$('a.widgetHelpLink').attr('href',helpURL)
        
        this._updateTemplatesList('templateCard', this.options.editOptions['templateCard']);
        this._updateTemplatesList('templateView', this.options.editOptions['templateView']);
        
        let btn = this._$('button[data-btn-editor]').button({showLabel:false, icon:'ui-icon-pencil'});
        this._on(btn, {click: (e)=>this._onTemplateEdit(e)});                                

    },

    /**
     * @private
     * @memberof Widgets.UI.HRecordListOpts
     * @description Show popup with template editor
     * @param {Event} event - The event object.
     */
    _onTemplateEdit: function(event) {
        
        const btn = ($(event.target).is('button'))?$(event.target):$(event.target).parents('button');
        
        const templateType = btn.attr('data-btn-editor');
        
        let _currentTemplate = this._$(`select[name="${templateType}"]`).val()
        
        if(!_currentTemplate) return;
        
        let that = this;
        let popupDialogOptions = {path: 'widgets/report/', 
                    keep_instance: false, 
                    template: _currentTemplate,
                    isWidgetTemplate: true,
                    onClose: function(is_update_list){
                        if(is_update_list){
                            that._updateTemplatesList(templateType);
                        }
                    }
        };
        window.hWin.HEURIST4.ui.showRecordActionDialog('reportEditor', popupDialogOptions);
    },
    
    /**
     * @private
     * @memberof Widgets.UI.HRecordListOpts
     * @description Updates the templates list.
     * @param {string} templateType - The type of the template.
     * @param {string} currentTemplate - The current template.
     */
    _updateTemplatesList: function(templateType, currentTemplate) {
 
        const selector = this._$(`select[name="${templateType}"]`);       
        if(!currentTemplate){
            currentTemplate = selector.val()    
        }
        
        let templateCard;
        if(templateType=='templateCard'){
            templateCard = [{key:'',title:'build-in renderer'}, 
             {key:'def/HRecordListCard', title:'Card'}, 
             {key:'def/HRecordListMin',  title:'Icon+title (minimal)'}, 
             {key:'def/HRecordListRow',  title:'Table row'},
             {key:'def/HRecordListBig',  title:'Extended info'},
             {key:'',title:'<hr>',disabled:true}];
        }else{
            templateCard = [{key:'',title:'build-in renderer'}, 
             {key:'def/HRecordView', title:'Record View template'}, 
             {key:'',title:'<hr>',disabled:true}];
        }

        
        selector.empty();
        
        window.hWin.HEURIST4.ui.createTemplateSelector(  
                           selector, templateCard, 
                           currentTemplate,
                           {extraOptions: {menu_parent:  this.jqDialog??$('#treePage') }});  // or bsModal bsOffcanvas
        
    },        
    
    
});
