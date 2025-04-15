/*
* HCmsEditorMargins.js - editor for header or footer
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

/* global editCMS_SiteMenu */

/*
* HCmsEditorMargin.js - editor for header or footer
*/
class HCmsEditorMargin {

  cmsEditor;
  main_callback;
  siteId;

  constructor(options) {

      this.cmsEditor = options.cmsEditor;
      this.container = options.container;
      this.main_callback = options.callback;
      
      let that = this;
      this.container.empty().load(window.hWin.HAPI4.baseURL
          +'hclient/widgets/cms/HCmsEditorMargin.html',
          ()=>that.#initControls());
  }

  /**
  * Inits inteface controls
  */
  #initControls(){

      let that = this;
      let cont = this.container;

      //        this._editor_panel.find('.btn-website-homepage').on('click', ()=>that.#editHomePage()); //load home page content

      cont.find('select[name="position"]').hSelect({change:function(event){
console.log( $(event.target).val() );
            

      }});
      
      cont.find('select[name="template"]').each((i,sel)=>{
        window.hWin.HEURIST4.ui.createTemplateSelector(  
                        $(sel), [],  //[{key:'',title:'default'}], 
                           '',   //that.options.editOptions[sel.name], 
                           {cms:'header', extraOptions: {menu_parent: cont}, 
                            eventHandlers:{onSelectMenu:function(event){
                                //replace header element
                                that.cmsEditor.webSite.loadMargin( $(event.target).val() );
                            } }  });
                        }); 
                        
      cont.find('div.btn-html-edit').button().on('click', function(){

      });

      //save entire page (in background)
      cont.find('.btn-save-page').button().css('border-radius','4px').on('click', function(){
          //TBD __saveWidgetConfig();
          //TBD _getCfgFromUI();
          let l_cfg = '';
          that.main_callback.call(this, l_cfg, 'save_close'); //save and close
      });

      cont.find('.btn-save-element').button().css('border-radius','4px').on('click', function(){
          //TBD __saveWidgetConfig();
          //5. save in layout cfg
          //TBD _getCfgFromUI();
          let l_cfg = '';
          that.main_callback.call(this, l_cfg, 'save'); //save only
          window.hWin.HEURIST4.util.setDisabled(cont.find('.btn-save-element'), true);
      });
      cont.find('.btn-cancel').css('border-radius','4px').button().on('click', function(){
          //6. restore old settings 
          //TBD element.removeAttr('style');
          //TBD if(element_cfg.css) element.css(element_cfg.css);
          that.main_callback.call(this, null);
      });

      window.hWin.HEURIST4.util.setDisabled(cont.find('.btn-save-page'), false);
      window.hWin.HEURIST4.util.setDisabled(cont.find('.btn-save-element'), true);

  }

}