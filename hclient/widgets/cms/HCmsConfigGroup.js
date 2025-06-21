/*
* HCmsConfigGroup.js - configuration for container/group eleemnt
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

/* global HCmsConfig, HCmsEditor */

class HCmsConfigGroup extends HCmsConfig {
   
   groupEditor = null; 
   
   allContainerBsClasses = ['container','row','col','justify-content','align-items','g-'];
   
  /**
  * Inits inteface controls
  */
  initControls(){
     
     let that = this;
     if(this.groupEditor==null){
         //hide 
         this.container.find('.btn-html-edit').parent().hide();

         //adds options dialogue
         $('<div id="groupEditor" style="font-size:1em;"></div>').insertBefore(this.container.find('#properties_form'));
         
         this.groupEditor = this.container.find('#groupEditor');
         
         this.groupEditor.empty().load(window.hWin.HAPI4.baseURL
              +'hclient/widgets/cms/HCmsConfigGroup.html',
              ()=>that.initControls());
              return;    
     }
      
     super.initControls();  
     
     let cont = this.container;
     let l_cfg = this.l_cfg;

     let etype = l_cfg.type;
     if(l_cfg.type=='group' && l_cfg.css?.display=='flex'){
         etype = 'flex';
     }
     
     //init controls in groupEditor
     if(l_cfg.isPage){
         //cont.find('.group-layout').hide();
         let containerClass = HCmsEditor.getBsClasses(l_cfg.bsClasses, 'container');
         cont.find('#containerType').val(containerClass);
         cont.find('#groupType').val('group');
     }else{
         //cont.find('.page-layout').hide();
         cont.find('#containerType').parent().hide();
     }
     
     let selGoupType = cont.find('#groupType');
     selGoupType.val(etype);
     selGoupType = window.hWin.HEURIST4.ui.initHSelect(selGoupType);
     selGoupType.on({change:()=>that.#initControlsForLayoutType()});
     
     this.#initControlsForLayoutType()
         
     //Listeners for selects    
     cont.find('.group-layout select').each((i,selObj)=>{
         if(!$(selObj).hSelect('instance')){
            selObj = window.hWin.HEURIST4.ui.initHSelect(selObj);
         }
         $(selObj).on('change', ()=>that.#onChangeOptions());
     });
     
     cont.find('.group-layout input').on('change', ()=>that.#onChangeOptions());
     
     if(l_cfg.isPage || etype=='tabs' || etype=='accordion'){
        cont.find('input[data-type="element-id"]').parent().hide();
     }    
     
  }
  
  /*
  * 
  */  
  #cleanGridFlexItems(){
        let cont = this.container;
        cont.find('div[data-gridcol]').each(function(idx, item){
           if($(item).attr('data-gridcol')>=0) $(item).remove();
        });
        cont.find('div[data-flexitem]').each(function(idx, item){
           if($(item).attr('data-flexitem')>=0) $(item).remove();
        });
  }
  
  /*
  * 
  */  
  #initControlsForLayoutType(){
  
     let that = this;
     let cont = this.container;
     let l_cfg = this.l_cfg;

     let etype = cont.find('#groupType').val();

     cont.find('.props').hide();
     if(!window.hWin.HEURIST4.util.isempty(etype)){
         cont.find('.props.'+etype).show();
     }

     this.#cleanGridFlexItems();

     if(!(l_cfg.children && l_cfg.children.length>0)) return;

     if(etype=='grid'){

         let val = [...l_cfg.bsClasses.matchAll(/(justify-content-)([a-z]+)/g)];
         if(val.length==1 && val[0].length==3){
             cont.find('#grid-justify-content').val(val[0][2]);
         }
         val = [...l_cfg.bsClasses.matchAll(/(align-items-)([a-z]+)/g)];
         if(val.length==1 && val[0].length==3){
             cont.find('#grid-align-items').val(val[0][2]);
         }
         val = [...l_cfg.bsClasses.matchAll(/(g-)(\d)/g)];
         if(val.length==1 && val[0].length==3 && val[0][2]>0){
             cont.find('#grid-gap').val(val[0][2]);
         }

         let item_ele = cont.find('div[data-gridcol]');
         item_ele.splice(1);
         let item_last = item_ele;

         for(let i=0; i<l_cfg.children.length; i++){

             let child = l_cfg.children[i];

             let item = item_ele.clone().insertAfter(item_last);
             item.attr('data-gridcol',i).show();
             let lbl = item.find('.header_narrow');

             lbl.text(child.name);

             let inputColWidth = item.find('select[name="grid-col-width"]');
             let inputCol = item.find('input[name="grid-col"]');

             let colClass = HCmsEditor.getBsClasses(child.bsClasses, 'col');
             if(colClass==''){
                 colClass = 'col';    
                 child.bsClasses = (child.bsClasses+' col').trim();
             } 

             //remove possible flex settings
             if(child.css?.flex){
                 delete child.css.flex;
             }
             if(child.css?.display){
                 delete child.css.display;
             }

             let val = colClass;
             val = val.split('-');
             // col,col-auto or col-{1~12}
             if(val.length==2 && parseInt(val[1])>0 && parseInt(val[1])<13){
                 inputColWidth.val('proportion');
                 inputCol.show().val(val[1]);   
             }else{
                 inputColWidth.val(val.length==2?'auto':'');   
                 inputCol.hide();
             }

             function __onColWidthChange(e){
                 let item = $(e.target).parent();
                 let val = item.find('select[name="grid-col-width"]').val();
                 let inputCol = item.find('input[name="grid-col"]');

                 if(val=='proportion'){
                     inputCol.show();
                     val = 'col-'+inputCol.val();
                 }else{
                     inputCol.hide();
                     val = 'col'+(val=='auto'?'-auto':'');
                 }

                 let k = item.attr('data-gridcol');

                 l_cfg.children[k].bsClasses = (HCmsEditor.removeBsClasses(l_cfg.children[k].bsClasses, 'col') + ' ' + val).trim();

                 let child_ele = that.cmsEditor.findInWebSite('.cms-element[data-hid="'+l_cfg.children[k].key+'"]');

                 HCmsEditor.replaceBsClasses(child_ele, 'col', val);
             }

             //inputColWidth.hSelect({change: __onColWidthChange});
             inputColWidth = window.hWin.HEURIST4.ui.initHSelect(inputColWidth);
             inputColWidth.on('change', __onColWidthChange);
             inputCol.on('change', __onColWidthChange);

             item_last = item;
         }//for

     }else
         if(etype=='flex'){

             //4a.add list of children with flex-grow and flex-basis
             let item_ele = cont.find('div[data-flexitem]');
             item_ele.splice(1);
             let item_last = item_ele;

             for(let i=0; i<l_cfg.children.length; i++){

                 let child = l_cfg.children[i];

                 let item = item_ele.clone().insertAfter(item_last);
                 item.attr('data-flexitem',i).show();
                 let lbl = item.find('.header_narrow');
                 lbl.text((i+1)+'. '+lbl.text());

                 let val = (child.css)?child.css['flex']:null;
                 if(val){
                     val = val.split(' '); //grow shrink basis
                 }else{
                     val = [0,1,'auto'];
                 }
                 if(val[0]) item.find('input[data-type="flex-grow"]').val(val[0]);
                 if(val.length==3 && val[2]) item.find('input[data-type="flex-basis"]').val(val[2]);

                 item.find('input').on('change', function(e){
                     let item = $(e.target).parent();
                     let k = item.attr('data-flexitem');

                     if(!l_cfg.children[k].css) l_cfg.children[k].css = {};

                     l_cfg.children[k].css['flex'] = item.find('input[data-type="flex-grow"]').val()
                     +' 1 '+ item.find('input[data-type="flex-basis"]').val();

                     /*l_cfg.children[k].css['border'] = '1px dotted gray';
                     l_cfg.children[k].css['border-radius'] = '4px';
                     l_cfg.children[k].css['margin'] = '4px';*/

                     let child_ele = that.cmsEditor.findInWebSite('.cms-element[data-hid="'+l_cfg.children[k].key+'"]');
                     $(child_ele).removeAttr('style');
                     $(child_ele).css(l_cfg.children[k].css);
                 });

                 item_last = item;
             }//for

         }else if(etype=='tabs'){
                 cont.find('#nav_type').val(l_cfg.options?.nav_type??'jQuery');
                 cont.find('#nav_dir').val(l_cfg.options?.nav_dir??'nav-row');
         }else if(etype=='accordion'){
                 cont.find('#acc_type').val(l_cfg.options?.acc_type??'jQuery');
                 cont.find('#acc_collapse').attr('checked',l_cfg.options?.acc_collapse);
         }

  }
  
  /*
  * 
  */  
  #onChangeOptions(){

      let that = this;
      let cont = this.container;
      let l_cfg = this.l_cfg;
      let css = {};

      let groupType = cont.find('#groupType').val();
      let recreateGroup = false;

      let bsClasses = HCmsEditor.getBsClasses(l_cfg.bsClasses, 'col'); //in case this element is col for parent
      bsClasses = bsClasses.split(' ');

      if(l_cfg.isPage){
          bsClasses.push(cont.find('#containerType').val());

          if(bsClasses.length>0){
              HCmsEditor.replaceBsClasses(this.element, 'container', bsClasses);
          }

      }else{

          if(groupType=='grid'){
              bsClasses.push('row');
              cont.find('.grid-select').each(function(i,item){
                  if($(item).val()){
                      bsClasses.push($(item).attr('name')+'-'+$(item).val());
                  }
              });
          }else if(groupType=='tabs'){

              const ntype = cont.find('#nav_type').val();
              const nvert = ntype=='nav-jquery'?'nav-row':cont.find('#nav_dir').val();

              recreateGroup = (l_cfg.options?.nav_type != ntype || l_cfg.options?.nav_dir != nvert);

              l_cfg.options = {}; //reset
              l_cfg.options.nav_type = ntype;
              l_cfg.options.nav_dir = nvert;

          }else if(groupType=='accordion'){

              const acc_type = cont.find('#acc_type').val();
              const acc_collapse = cont.find('#acc_collapse').is(':checked');

              recreateGroup = (l_cfg.options?.acc_type != acc_type || l_cfg.options?.acc_collapse != acc_collapse);

              l_cfg.options = {}; //reset
              l_cfg.options.acc_type = acc_type;
              l_cfg.options.acc_collapse = acc_collapse;

          }else if(groupType=='flex'){
              css['display'] = 'flex';

              cont.find('select.flex-select').each(function(i,item){
                  if($(item).val()){
                      css[$(item).attr('id')] = $(item).val();       
                  }
              });
          }

          if(l_cfg.options && !(groupType=='accordion' || groupType=='tabs')){
              delete l_cfg.options;
          }

          //set classes/css for children
          if(groupType!='grid' && l_cfg.type=='grid'){
              //remove grid classes for container and children
              if(l_cfg.bsClasses){
                  bsClasses = HCmsEditor.removeBsClasses(bsClasses, this.allContainerBsClasses);
              }
              for(let i=0; i<l_cfg.children.length; i++){
                  l_cfg.children[i].bsClasses = HCmsEditor.removeBsClasses(l_cfg.children[i].bsClasses, 'col');
              }
          }else if(groupType!='flex'){
              //remove flex css for children if container is not flex
              for(let i=0; i<l_cfg.children.length; i++){
                  if(l_cfg.children[i].css?.flex){
                      l_cfg.children[i].css.flex = null;
                      delete l_cfg.children[i].css['flex'];
                  }
              }
          }
      }

      if(l_cfg.css){
          let old_css = l_cfg.css;
          //remove these flex parameters from css and assign new ones obtained from form
          let params = ['display','flex-direction','flex-wrap','justify-content','align-items','align-content'];
          for(let i=0; i<params.length; i++){
              let prm = params[i];
              if (old_css[prm] && (prm.indexOf('margin')<0 || old_css[prm]!='auto')){ //drop old value
                  old_css[prm] = null;
                  delete old_css[prm];
              };
          }
          css = $.extend(old_css, css);
      }
      
      if(bsClasses.length>0){
            HCmsEditor.replaceBsClasses(this.element, this.allContainerBsClasses, bsClasses);
            l_cfg.bsClasses = HCmsEditor.getBsClassesAsString(this.element, this.allAffectedBsClasses);
      }
      l_cfg.css = css;
      this.assignCssTextArea();

      if(recreateGroup || (!l_cfg.isPage && groupType!=l_cfg.type)){
          //l_cfg.uiLibrary='bootstrap';

          l_cfg.type = (groupType=='flex')?'group':groupType; //special case for flex to be compatible with v2
          
          let groupInitMethod;
          if(groupType=='accordion'){
              groupInitMethod = 'layoutInitAccordion';
          }else if(groupType=='tabs'){
              groupInitMethod = 'layoutInitTabs';
          }else{
              groupInitMethod = 'layoutInitGroup';
          }

          this.element = this.cmsEditor.getHapi().layoutMgr[groupInitMethod](l_cfg, $(this.element));
          this.element.addClass('cms-element-editing headline');// marching-ants marching
          this.element = this.element[0];

      }else{
          $(this.element).removeAttr('style');
          $(this.element).css(css); //assign changed css at once
      }
      
      this.onContentChange( true );
      
  }
  
  /*
  *
  */
  revertChanges(){
      
      super.revertChanges();
      
      //recreate widget
      if(this.l_cfg.isPage){
           HCmsEditor.replaceBsClasses(this.element, this.allContainerBsClasses, this.l_cfg.bsClasses);
      }else{
           //recreate group
           this.cmsEditor.getHapi().layoutMgr.layoutInitFromJSON(this.l_cfg, this.element, {}, false);
      }
  }
  
  /**
  * 
  */
  setCssToUI(){
      super.setCssToUI();

      //assign flex css parameters
      let params = ['display','flex-direction','flex-wrap','justify-content','align-items','align-content'];
      for(let i=0; i<params.length; i++){
            let prm = params[i];
            if (this.l_cfg.css[prm]) this.container.find('#'+prm).val(this.l_cfg.css[prm]);
      }
      
  }
  
  
}