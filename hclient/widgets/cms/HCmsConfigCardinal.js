/*
* HCmsConfigCardinal.js - configuration for Heurist Cardinal widget based on jQuery
* 
* @project     Heurist academic knowledge management system
* @package CMS
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

/* global HCmsConfig */

class HCmsConfigCardinal extends HCmsConfig {
    
  optionsEditor;  

  /**
  * Inits inteface controls
  */
  initControls(){

      let that = this;    

      //adds options dialogue
      if(this.optionsEditor==null){
          //hide 
          this.container.find('.btn-html-edit').parent().hide();
          this.container.find('input[data-type="element-id"]').parent().hide();
          this.container.find('input[data-type="element-name"]').prev().text('Label');

          this.optionsEditor = $('<div id="cardinalForm" style="font-size:1em;border:1px solid lightgray;border-radius:4px;margin:2px 4px;"></div>');
          this.container.find('#properties_form').prepend(this.optionsEditor);

          this.optionsEditor.empty().load(window.hWin.HAPI4.baseURL
              +'hclient/widgets/cms/HCmsConfigCardinal.html',
              ()=>that.initControls());
          return;    
      }

      super.initControls();  

      let cont = this.container;
      let l_cfg = this.l_cfg;

      //load options values to form
      for(let i=0; i<l_cfg.children.length; i++){
          let lpane = l_cfg.children[i];
          let pane = lpane.type;

          if(lpane.options){
              let keys = Object.keys(lpane.options); 
              for(let k=0; k<keys.length; k++){
                  let key = keys[k];
                  let ele = cont.find('[data-type="cardinal"][data-pane="'+pane+'"][name="'+key+'"]');
                  if(ele.length>0){
                      const val = lpane.options[key];   
                      if(ele.attr('type')=='checkbox'){
                          ele.attr('checked', (val=='true' || val===true));
                      }else {
                          ele.val(val);    
                      }
                  }
              }//for
          }
      }//for
      
      //Listeners for selects    
      cont.find('#properties_form select[data-type="cardinal"]').each((i,selObj)=>{
            selObj = window.hWin.HEURIST4.ui.initHSelect(selObj);
            selObj.on('change', ()=>that.onChangeOptions(true));
      });
      

  }
  
  /*
  * 
  */  
  onChangeOptions(recreateCardinal){

      let cont = this.container;
      let l_cfg = this.l_cfg;

      for(let i=0; i<l_cfg.children.length; i++){
          let lpane = l_cfg.children[i];
          let pane = lpane.type;

          l_cfg.children[i].options = {}; //reset

          $.each(cont.find('[data-type="cardinal"][data-pane="'+pane+'"]'), function(k, item){
              item = $(item);
              let name = item.attr('name');
              let val = item.val();
              if(item.attr('type')=='checkbox'){
                  val = item.is(':checked'); 
                  l_cfg.children[i].options[name] = val;    
              }else if(val!=''){
                  l_cfg.children[i].options[name] = val;    
              }
          });
      }//for

      if(recreateCardinal){
        this.cmsEditor.getHapi().layoutMgr.layoutInitCardinal(l_cfg, $(this.element));
        this.element = this.cmsEditor.findInWebSite('.cms-element[data-hid="'+l_cfg.key+'"]');
      }

      this.onContentChange( true );
  }
  
  /*
  *
  */
  getCfgFromUI(){
      super.getCfgFromUI();
      this.onChangeOptions(false);
  }
  
  /*
  *
  */
  revertChanges(){
      super.revertChanges();
      //recreate widget
      this.cmsEditor.getHapi().layoutMgr.layoutInitCardinal(this.l_cfg, this.element);
      //layoutMgr.layoutInitFromJSON(this.l_cfg, this.element, {}, false);
  }
  
}