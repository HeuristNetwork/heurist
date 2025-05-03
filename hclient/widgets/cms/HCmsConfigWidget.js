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

  show(options){
     super.show(options);
  }
  
  /**
  * Inits inteface controls
  */
  initControls(){
      
     //hide
     this.container.find('#id_and_name_form').hide();

     //adds options dialogue
     this.container.find('#properties_form').prepend(
     $('<div style="border:1px solid lightgray;border-radius:4px;margin:2px 4px;"><h3 style="display:inline-block">Widget configuration</h3>'
     +'<div id="widgetOptionsEditor"></div></div>'));
     
     this.optionsEditor = this.container.find('#widgetOptionsEditor');
     
     //load options form into container panel
     let that = this;
     let layoutMgr = this.cmsEditor.getHapi().layoutMgr;
     layoutMgr.executeWidgetMethod(this.element, this.l_cfg.appid, 'openOptionsEditor', [this.optionsEditor, (a)=>that.onChangeOptions(a)]);
      
     super.initControls();  
  }
  
  /*
  * 
  */  
  onChangeOptions(widgetOptions){
      
      if(widgetOptions){
//console.log('>>>>>', widgetOptions);            
            
            // let newOptions = $.extend($.heurist[this.l_cfg.appid].prototype.options, widgetOptions);
            // 

            //TBD clean options fro default prototype values
            let l_cfg = this.l_cfg;
            l_cfg.options = widgetOptions;
            l_cfg.dom_id = l_cfg.options.dom_id;
            l_cfg.name = l_cfg.options.name;
            l_cfg.title = '<span data-lid="'+ l_cfg.key +'">'+l_cfg.name+'</span>';

            this.onContentChange( true );

            let layoutMgr = this.cmsEditor.getHapi().layoutMgr;
            layoutMgr.executeWidgetMethod(this.element, this.l_cfg.appid, 'onCloseOptionEditor', widgetOptions);
      }
      
  }
  
  /*
  * 
  */  
  #getIdAndName(){
      console.log('CGILD getIdAndName');
  }
}