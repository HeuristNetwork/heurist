/**
* HRecordListOpts - form to modify HRecordList options
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

import '../HBase/HBaseOpts.js';

$.widget( 'heurist.HRecordListOpts', $.heurist.HBaseOpts, {
    
    // default options
    options: {
        resourcePath: 'hclient/widgets/HRecordList/HRecordListOpts',
    },
    
    _fillControls: function(){
        
        this._super();
        
        this._$('#placeholderInitDef').text(this.options.editOptions.placeholderInitDef);
        
        this._$('#placeholderEmptyDef').text(this.options.editOptions.placeholderEmptyDef);
        
        let helpURL = window.hWin.HRes( 'HRecordList.htm' );
        this._$('a.widgetHelpLink').attr('href',helpURL)
        
        //templateCard, templateView
        
        const templateCard = [{key:'',title:'build-in renderer'}, 
         {key:'def/HRecordListCard', title:'Card'}, 
         {key:'def/HRecordListMin',  title:'Icon+title (minimal)'}, 
         {key:'def/HRecordListRow',  title:'Table row'},
         {key:'',title:'<hr>',disabled:true}];
        
        //templateCard
        let that = this;
        this._$('select[name^="template"]').each((i,sel)=>{
        window.hWin.HEURIST4.ui.createTemplateSelector(  
                        $(sel), templateCard, 
                           that.options.editOptions[sel.name],  //$select3.attr('data-template')
                           {extraOptions: {menu_parent:  that.jqDialog??$('#treePage') }});  // or bsModal bsOffcanvas
                        }); 
        
        let btn = this._$('button[name="btnTemplateCardEdit"]').button({showLabel:false, icon:'ui-icon-pencil'});
        this._on(btn, {click: ()=>this._onTemplateEdit()});                                

    },

    //
    // Show popup with template editor
    //
    _onTemplateEdit: function() {
        
        let _currentTemplate = this._$('select[name="templateCard"]').val()
        
        if(!_currentTemplate) return;
        
        //if(_currentTemplate.indexOf('def/')==0){
        //    _currentTemplate = 'def/'+_currentTemplate.substring(4);
        //}
        
        let that = this;
        let popupDialogOptions = {path: 'widgets/report/', 
                    keep_instance: false, 
                    template: _currentTemplate,
                    is_snippet_editor: true,
                    onClose: function(is_update_list){
                        if(is_update_list){
                            //that._updateTemplatesList();
                        }
                    }
        };
        window.hWin.HEURIST4.ui.showRecordActionDialog('reportEditor', popupDialogOptions);
    },
        
    
});
