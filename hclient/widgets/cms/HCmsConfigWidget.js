/*
* HCmsConfigWidget.js - configuration for Heurist widget. Besides css/classes 
* coonfigutation forms it loads HBaseWidgetOpts - form with widget options
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

/* global HCmsConfig */

class HCmsConfigWidget extends HCmsConfig {
    
  optionsEditor;  
  
  optionEditorForOldWidget;

  show(options){
     super.show(options);
  }
  
  /**
  * Inits inteface controls
  */
  initControls(){
      

     //adds options dialogue
     this.container.find('#properties_form').prepend(
     $('<div style="border:1px solid lightgray;border-radius:4px;margin:2px 4px;"><h3 style="display:inline-block">Widget configuration</h3>'
     +'<div id="widgetOptionsEditor"></div></div>'));
     
     this.optionsEditor = this.container.find('#widgetOptionsEditor');

     super.initControls();  
     
     let that = this;
     
     if(this.l_cfg.appid?.indexOf('HRecord')===0 || this.l_cfg.appid?.indexOf('HMenu')===0){
        //hide
        this.container.find('#id_and_name_form').hide();
        //load options form into container panel
        let layoutMgr = this.cmsEditor.getHapi().layoutMgr;
        layoutMgr.executeWidgetMethod(this.element, this.l_cfg.appid, 'openOptionsEditor', [this.optionsEditor, (a)=>that.onChangeOptions(a)]);
     }else{
        this.container.find('.btn-html-edit').parent().hide();
        this.container.find('input[data-type="element-id"]').parent().hide();
        this.container.find('input[data-type="element-name"]').prev().text('Widget label');
 
        const dom_id = window.hWin.HEURIST4.util.stripTags(this.container.find('input[data-type="element-id"]').val());
        if(dom_id!=this.l_cfg.options.widget_id){
            this.l_cfg.options.widget_id = dom_id;
        }

        this.optionEditorForOldWidget = editCMS_WidgetCfg(this.l_cfg, $(this.element), this.optionsEditor, null, function(){

            const new_cfg = that.optionEditorForOldWidget.getValues();
            if(JSON.stringify(that.l_cfg.options) != JSON.stringify(new_cfg)){
                    that.onChangeOptions(new_cfg)
            }
        });
     }
      
  }
  
  /*
  * 
  */  
  onChangeOptions(widgetOptions){
      
      if(widgetOptions){

            //TBD clean options fro default prototype values
            let l_cfg = this.l_cfg;
            l_cfg.options = widgetOptions;
            l_cfg.dom_id = l_cfg.options.dom_id;
            l_cfg.name = l_cfg.options.name;

            this.onContentChange( true );

            let layoutMgr = this.cmsEditor.getHapi().layoutMgr;
            if(this.l_cfg.appid?.indexOf('HRecord')===0){
                //apply new options
                layoutMgr.executeWidgetMethod(this.element, this.l_cfg.appid, 'onCloseOptionEditor', widgetOptions);
            }else{
                //recreate widget                
                //TBD
            }
      }
  }
  
  /*
  *
  */
  getCfgFromUI(){
      super.getCfgFromUI();
      this.onChangeOptions(false);

      if(this.optionEditorForOldWidget){
          let new_cfg = this.optionEditorForOldWidget.getValues();
          this.l_cfg.options = new_cfg;

          if(new_cfg.widget_id){
              this.l_cfg.dom_id = new_cfg.widget_id;
              this.container.find('input[data-type="element-id"]').val(this.l_cfg.dom_id);
          }
      }

  }
  
  /*
  *
  */
  revertChanges(){
      super.revertChanges();
      //recreate widget
      this.cmsEditor.getHapi().layoutMgr.layoutInitFromJSON(this.l_cfg, this.element, {}, false);
  }
  
}