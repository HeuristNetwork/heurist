/*
* HCmsConfig.js - base configuration for CSM element
* It containes name, border/bg/margin and direct edit
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

/* global HCmsEditor */

class HCmsConfig {

  container; //element for form
  cmsEditor; //reference to parent editor
  onClose;
  siteId;

  element;
  element_cfg; //current cfg
  l_cfg; //copy of json config
  
  codeEditor; //HCmsCodeEditor
  isChanged = false;

  allAffectedBsClasses = ['border','bg-','text-','rounded','shadow','m-','ms-','me-','mt-','mb-','p-','ps-','pe-','pt-','pb-',
                            'container','row','col','justify-content','align-items','g-'];
  allStyleBsClasses = ['border','bg-','text-','rounded','shadow','m-','ms-','me-','mt-','mb-','p-','ps-','pe-','pt-','pb-'];
  
  hasUserStyles = false;
  
  cssPaddingMarginBorder = [
          'padding','padding-left','padding-top','padding-bottom','padding-right',
          'margin','margin-left','margin-top','margin-bottom','margin-right',
          'border','border-width','border-color','border-style','border-radius'];
  
  constructor(options) {
      
      this.cmsEditor = options.cmsEditor;
      this.container = options.container;
      this.onClose = options.onClose;
      this.siteId = this.cmsEditor.website_id;
      this.isChanged = options.alreadyModified;

      this.element_cfg = options.element_cfg
      this.l_cfg = window.hWin.HEURIST4.util.cloneJSON(options.element_cfg);
  
      this.show( options );      
  }
  
  show(options){
      
      //element = this.layoutMgr.layoutContentFindElement(this._layout_content, this.element_cfg.key);
      this.element = this.cmsEditor.findInWebSite('div[data-hid="'+this.element_cfg.key+'"]'); //element in main-content    
      $(this.element).removeClass('marching-ants marching');
      
      let that = this;
      this.container.empty().load(window.hWin.HAPI4.baseURL
          +'hclient/widgets/cms/HCmsConfig.html',
          ()=>that.initControls());
  }
  
  /**
  * Inits inteface controls
  */
  initControls(){
      
      const alreadyModified = this.isChanged;
      
      let that = this;
      let cont = this.container;
      let l_cfg = this.l_cfg;
      
      if(!l_cfg.css) l_cfg.css = {};

      cont.find('#properties_form').accordion({header:'h3',heightStyle:'content',active:0,collapsible:true});
      cont.find('h3').css({padding:'1em', 'font-size': '1.1em', 'font-weight': 'bold'});
      cont.find('fieldset').css({background: 'transparent', padding: '1em'});
      
      cont.find('input[data-type="element-name"]').val(l_cfg.name);
      cont.find('input[data-type="element-id"]').val(l_cfg.dom_id); //duplication for options.widget_id
      cont.find('textarea[name="elementClasses"]').val(l_cfg.classes); //publisher's classes
      cont.find('input[data-type^="element"]').on('change',()=>that.onContentChange(true));
      
      //Listeners for inputs
      cont.find('input[data-type="css"]').on('change', (e)=>that.#getCss(e));
      cont.find('input[data-type="css"]').on('keyup', (e)=>that.#getCss(e));
      
      //Margin sync values
      cont.find('.cb_sync').parent().css({'font-size':'0.8em'});
      cont.find('.cb_sync').on('change',(e)=>that.#onMarginSync(e));
      cont.find('input[name^="bsMargin-"]').on('change',(e)=>that.#onMarginSyncVal(e));
      
      //Listeners for selects    
      cont.find('#properties_form select[data-type!="cardinal"]').each((i,selObj)=>{
            selObj = window.hWin.HEURIST4.ui.initHSelect(selObj);
            selObj.on('change', (e)=>that.#getCss(e));
      });

      //Listeneres for global border and bg checkboxes
      cont.find('input[name="background"]').on('change',(e)=>that.#getCss(e) );
      cont.find('input[name="border"]').on('change',(e)=>that.#getCss(e) );

      //init color pickers
      cont.find('input[name$="-color"]').colorpicker({
          hideButton: false, //show button right to input
          showOn: "both"});//,val:value
      cont.find('input[name$="-color"]').parent('.evo-cp-wrap').css({display:'inline-block',width:'100px'});

      //get from element
      if(!l_cfg.bsClasses){
          l_cfg.bsClasses = HCmsEditor.getBsClassesAsString(this.element, this.allAffectedBsClasses);
      }
      
      this.#setCssToUI();
      this.assignCssTextArea();

      //direct editor        
      let textAreaCss = cont.find('textarea[name="elementCss"]');
      textAreaCss.on('change',function(){

          let vals = textAreaCss.val();

          vals = vals.replace(/"/g, ' ');

          vals = vals.split(';')
          let new_css = {};
          for (let i=0; i<vals.length; i++){
              let vs = vals[i].split(':');
              if(vs && vs.length>1){ //pair
                  const key = String(vs.shift()).trim();
                  const val = vs.join(':').trim();
                  new_css[key] = val;
              }
          }

          $(that.element).removeAttr('style');
          $(that.element).css(new_css);
          that.l_cfg.css = new_css;

          that.#setCssToUI();
          that.#getCss();
          
          //that.onContentChange(true);
      });
      
      
      //direct content editor
      let btnDirectEdit = cont.find('div.btn-html-edit');
      btnDirectEdit.button().on('click', ()=>that.#showCodeEditor());
      
      let btnConvert = cont.find('div.btn-css-convert');
      btnConvert.button().on('click', ()=>that.#convertUserStyles());
      
        
      //SAVE AND CANCEL BUTTONS      
      //save entire page (in background) 
      cont.find('.btn-save-and-close').button().css('border-radius','4px').on('click', function(){
          that.getCfgFromUI();
          that.onClose.call(this, that.l_cfg, 'close');
      });

      cont.find('.btn-save-only').button().css('border-radius','4px').on('click', function(){
          that.getCfgFromUI();
          that.onClose.call(this, that.l_cfg, 'save');
          that.onContentChange( false );
      });
      cont.find('.btn-cancel').css('border-radius','4px').button().on('click', function(){
          //restore old settings for classes, style and content
          if(that.isChanged){
              that.revertChanges();
          }
          that.onClose.call();      
      });
      
      this.onContentChange( alreadyModified );
  }
  
  /*
  *
  */
  revertChanges(){
  
      this.l_cfg = window.hWin.HEURIST4.util.cloneJSON(this.element_cfg);
      this.#setCssToUI();
      
      HCmsEditor.replaceBsClasses(this.element, this.allStyleBsClasses, this.l_cfg.bsClasses);
      $(this.element).removeAttr('style');
      $(this.element).css(this.l_cfg.css); //assign changed css at once
      
      //this.#getCss();
      $(this.element).html(this.l_cfg.content);
  }
  
  //
  //
  //
  #onMarginSync(event, type){

      let isChecked;
      if(type){
          isChecked = this.container.find('.cb_sync[data-type="'+type+'"]').is(':checked');
      }else{
          type = $(event.target).attr('data-type');
          isChecked = $(event.target).is(':checked');
      }

      const namePrefix = 'bsMargin-'+(type=='margin'?'m':'p');

      if(isChecked){ //synched

          this.container.find('input[name^="'+namePrefix+'"]').attr('readonly',true);
          this.container.find('input[name="'+namePrefix+'s"]').removeAttr('readonly');

          this.#onMarginSyncVal(null, type);

      }else{
          this.container.find('input[name^="'+namePrefix+'"]').removeAttr('readonly');
      }       
  }

  //
  //
  //
  #onMarginSyncVal(event, type){
      
      let cont = this.container;

      if(!type){
          type = $(event.target).attr('name');
          type = type.indexOf('bsMargin-m')?'margin':'padding';
      }

      if(this.container.find('input.cb_sync[data-type="'+type+'"]').is(':checked')){
          type = 'bsMargin-'+(type=='margin'?'m':'p');

          let val = cont.find('input[name="'+type+'s"]').val();
          cont.find('input[name="'+type+'-t"]').val(val);
          cont.find('input[name="'+type+'-b"]').val(val);
          cont.find('input[name="'+type+'-e"]').val(val);
      }
      if(event){
          this.#getCss();
      }
  }

  /*
  *
  */
  onContentChange( isChanged ){
      this.isChanged = isChanged;
      this.container.find('.btn-cancel').button({label: window.hWin.HR(this.isChanged?'Cancel':'Close')});
      window.hWin.HEURIST4.util.setDisabled(this.container.find('.btn-save-only'), !this.isChanged);
      window.hWin.HEURIST4.util.setDisabled(this.container.find('.btn-save-and-close'), !this.isChanged);
  }  
 
  /*
  * Show HTML code editor
  */
  #showCodeEditor(){
      let that = this;
      if(!this.codeEditor){
            this.codeEditor = this.container.find('#codemirror-container').HCmsCodeEditor({title: window.hWin.HR("Edit content of element"),
                                                                          onClose:(context)=>that.onCodeEditorApply(context),
                                                                          helpContent: 'website_header_footer.htm'});
      }                                                                   
      
      //if(!that.newContent
      //not defined
            // TBD for MARGINS 
            //that.#getTemplateContent('', (response)=>codeEditor.HCmsCodeEditor('show', response?.message));
      //}
            
      this.codeEditor.HCmsCodeEditor('show', this.l_cfg.content);
  }
  
  // 
  //update from main editor
  //
  updateContent(newContent, lang){
      this.l_cfg.content = newContent;
      //this.l_cfg['content'+lang] = newContent;            
  }
   
  /*
  * On code editor exit
  */  
  onCodeEditorApply(newContent, lang){
      if(!newContent){
          return;
      }
      //replace content with new one
      if(this.l_cfg.content != newContent){
          this.onContentChange( true );                
          this.l_cfg.content = newContent;
          //this.element.innerHtml = this.l_cfg.content;
          $(this.element).html(this.l_cfg.content);
      }
/* TBD      
      if(contents==null){ //no languages defined
      }else{ //multilang
      
          let cur_lang = ce_container.attr('data-lang');
          contents[cur_lang] = newval;
          let langs = Object.keys(contents);
          for(let i=0; i<langs.length; i++){
              let lang_key = 'content'+langs[i];
              if(default_language.toUpperCase()==langs[i]){
                  lang_key = 'content';
              }
              if(l_cfg[lang_key] != contents[langs[i]]){

                  l_cfg[lang_key] = contents[langs[i]];
                  _enableSave();
                  if(current_language.toUpperCase()==langs[i]){
                      element.html(l_cfg[lang_key]);    
                  }
              }
          }
      }
*/          
      
      // TBD
      //this.cmsEditor.webSite.reloadMargin( this.isHeader, newContent );
  }
  
  /*
  * Get name and id from UI
  */  
  getIdAndName(){
      let l_cfg = this.l_cfg;
      let cont = this.container;
      l_cfg.name = window.hWin.HEURIST4.util.stripTags(cont.find('input[data-type="element-name"]').val());
      if(!l_cfg.name) l_cfg.name = 'Define name of element';
      l_cfg.dom_id = window.hWin.HEURIST4.util.stripTags(cont.find('input[data-type="element-id"]').val());
      l_cfg.title = '<span data-lid="'+l_cfg.key+'">'+l_cfg.name+'</span>';
  }

  /*
  * Prepare values for saving
  */  
  getCfgFromUI(){

      let cont = this.container;
      let l_cfg = this.l_cfg;
      this.#getCss(); //assigns l_cfg.css and l_cfg.bsClasses
      
      this.getIdAndName();

      const userClasses = cont.find('textarea[name="elementClasses"]').val();
      if(window.hWin.HEURIST4.util.isempty(userClasses)){
          if(l_cfg.classes) delete l_cfg['classes'];
      }else{
          l_cfg.classes = userClasses;    
      }

  }

  /*
  * Setter. Assigns values from l_cfg to UI
  */ 
  #setCssToUI(){

      let cont = this.container;
      let l_cfg = this.l_cfg;

      let hasBorder = false;
      let hasBackground = false;

      if(l_cfg.bsClasses){
          let borderClasses = HCmsEditor.getBsClasses(l_cfg.bsClasses, 'border');

          if(borderClasses && borderClasses.length>0){

              const size = [...borderClasses.matchAll(/(border-)(\d)/g)];
              cont.find('#bsBorder-size').val(size.length==1 && size[0].length==3 && size[0][2]>0?size[0][2]:1);

              if(l_cfg.css['--bs-border-style'] && l_cfg.css['--bs-border-style']!='none'){
                  cont.find('#bsBorder-style').val(l_cfg.css['--bs-border-style']);
              }else{
                  cont.find('#bsBorder-style').val('solid');
              }

              cont.find('#bsBorder-color').val('default');
              if(l_cfg.css['--bs-border-color']){
                  cont.find('input[name="border-color"]').val(l_cfg.css['--bs-border-color']);
                  //cont.find('input[name="border-color"]').parent().show();
              }else{
                  const clr = [...borderClasses.matchAll(/(border-)([a-z]+)/g)];
                  if(clr.length==1 && clr[0].length==3){
                      cont.find('#bsBorder-color').val(clr[0][2]);
                      //cont.find('input[name="border-color"]').parent().hide();
                  }
              }

              //cont.find('#bsBorder-color').hSelect('refresh')
              hasBorder = cont.find('#bsBorder-style').val()!='none';
          }

          const roundedClass = HCmsEditor.getBsClasses(l_cfg.bsClasses, 'rounded') || 'rounded-0';
          hasBorder = hasBorder || roundedClass!='rounded-0';
          cont.find('#bsBorder-radius').val(roundedClass.substring(8));

          const shadowClass = HCmsEditor.getBsClasses(l_cfg.bsClasses, 'shadow') || 'none';
          hasBorder = hasBorder || shadowClass!='none';
          cont.find('#bsBorder-shadow').val(shadowClass);

          const bgColorClass = HCmsEditor.getBsClasses(l_cfg.bsClasses, 'bg-');
          hasBackground = (bgColorClass!='');
          cont.find('select[name="bgColor"]').val(bgColorClass);

          const textColorClass = HCmsEditor.getBsClasses(l_cfg.bsClasses, 'text-');
          hasBackground = hasBackground || (textColorClass!='');
          cont.find('select[name="textColor"]').val(textColorClass);

          function __setMargins(type, classes){
              let marginClasses = HCmsEditor.getBsClasses(l_cfg.bsClasses, classes);
              if(marginClasses=='') return;

              let isSync = false;
              marginClasses = marginClasses.split(' ');
              for(const cls of marginClasses){
                  let [key, val] = cls.split('-');
                  if(key=='m' || key=='p'){
                      key = key+'s';
                      isSync = true;
                  }
                  cont.find('input[name="bsMargin-'+key+'"]').val(val);
              }
              cont.find('input.cb_sync[data-type="'+type+'"]').prop('checked', isSync);
          }


          __setMargins('margin', ['m-','ms-','me-','mt-','mb-']);
          __setMargins('padding', ['p-','ps-','pe-','pt-','pb-']);
          this.#onMarginSync(null, 'margin');
          this.#onMarginSync(null, 'padding');

          cont.find('select[data-type="bs"]').hSelect('refresh');
      }


      //init file picker
      cont.find('input[name="bg-image"]')
      .on('click', this.#selecHeuristMedia);
      cont.find('#btn-background-image').button()
      .css({'font-size':'0.7em'})
      .on('click', this.#selecHeuristMedia);

      cont.find('#btn-background-image-clear')
      .button() //{icon:'ui-icon-close',showLabel:false})
      .css({'font-size':'0.7em'})
      .on('click', this.#clearBgImage);      

      cont.find('input[name="background"]').prop('checked', hasBackground);
      cont.find('input[name="border"]').prop('checked', hasBorder);
      
      
      this.#checkUserStyleSettings();
  }
  
  /*
  * Select uloaded image for background
  */
  #selecHeuristMedia(){
      
      let that = this;
      
        let popup_options = {
            isdialog: true,
            select_mode: 'select_single',
            edit_addrecordfirst: false, //show editor atonce
            selectOnSave: true,
            select_return_mode:'recordset', //ids or recordset(for files)
            filter_group_selected:null,
            filter_types: 'image',
            //filter_groups: this.configMode.filter_group,
            onselect:function(event, data){

                if(data){

                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                        let recordset = data.selection;
                        let record = recordset.getFirstRecord();
                        
                        let sUrl = recordset.fld(record,'ulf_ExternalFileReference');
                        if(!sUrl){
                            //always add media as reference to production version of heurist code (not dev version)
                            sUrl = window.hWin.HAPI4.baseURL_pro+'?db='+window.hWin.HAPI4.database
                            +"&file="+recordset.fld(record,'ulf_ObfuscatedFileID');
                            that.container.find('input[name="bg-image"]').val(recordset.fld(record,'ulf_OrigFileName'));
                        }else{
                            that.container.find('input[name="bg-image"]').val(sUrl);
                        }
                        
                        sUrl = 'url(\'' + sUrl + '\')';
                        that.container.find('input[name="background-image"]').val(sUrl);
                        
                        that.#getCss();

                    }

                }//data

            }
        };//popup_options        

        window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles', popup_options);
      
  }

  /*
  *
  */  
  #clearBgImage(){
        this.container.find('input[name="background-image"]').val('');
        this.container.find('input[name="bg-image"]').val('');
        
        this.#getCss();
  }

  /*
  * Getter. Get css vaues from UI and apply to element
  * isGlobalCheck - on/off switcher from UI checkbox 
  */
  #getCss( event ){
      
      let isGlobalCheck = false;
      let eleName = '';
      if(event){
            eleName = $(event.target).attr('name');
            isGlobalCheck = (eleName=='background' || eleName=='border');
      }
      
        let cont = this.container;
        let css = {};
        
console.log('getcss', this.l_cfg);
        
        let bsClasses = [];

// BACKGROUND  get values from UI -----------------

        //style - background
        let val = cont.find('input[name="background"]').is(':checked');
        if(isGlobalCheck && !val){
            css['background'] = 'none';
        }else{
            
            let hasBg = false;
            //colors for text and bg
            val = cont.find('select[name="bgColor"]').val();
            if(val!=''){
                bsClasses.push(val);
                hasBg = true;
            }
            val = cont.find('select[name="textColor"]').val();
            if(val!=''){
                bsClasses.push(val);
                hasBg = hasBg || true;
            }

            //val = cont.find('input[name="background-color"]').val();
            //if(val) css['background-color'] = val;

            val = cont.find('input[name="background-image"]').val();
            if(val){
                css['background-image'] = val;  
                css['bg-image'] = cont.find('input[name="bg-image"]').val();
                val = cont.find('select[name="background-position"]').val();
                css['background-position'] = val;  
                val = cont.find('select[name="background-repeat"]').val();
                css['background-repeat'] = val;  
                val = cont.find('select[name="background-size"]').val();
                css['background-size'] = val;  
                hasBg = hasBg || true;
            } 
            
            cont.find('input[name="background"]').prop('checked', hasBg);
        }
        
        if(!this.hasUserStyles){

// BORDER  get values from UI -----------------
        val = cont.find('input[name="border"]').is(':checked');
        if(isGlobalCheck && !val){
            css['border'] = 'none';
        }else{
            val = cont.find('#bsBorder-style').val();
            if(val!='none' && cont.find('#bsBorder-size').val()>0){
                css['--bs-border-style'] = val;
                
                bsClasses.push('border');
                val = cont.find('#bsBorder-size').val();
                if(val>0){
                    bsClasses.push('border-'+val);
                }
                val = (eleName=='border-color')?'default':cont.find('#bsBorder-color').val();
                if(val=='default'){
                    const bcrl = cont.find('input[name="border-color"]').val();
                    if(bcrl) {
                        css['--bs-border-color'] = bcrl;
                        cont.find('#bsBorder-color').val('default').hSelect('refresh');
                    }
                }else{
                    bsClasses.push('border-'+val);
                    cont.find('input[name="border-color"]').val('');
                }
            }
            val = cont.find('#bsBorder-radius').val();
            if(val!=0){
                bsClasses.push('rounded-'+val);
            }
            
            val = cont.find('#bsBorder-shadow').val();
            if(val!='none'){
                bsClasses.push(val);    
            }
            
            const hasBorder = bsClasses.length>0;
            cont.find('input[name="border"]').prop('checked', hasBorder);
        }
      
        
// MARGINS  get values from UI -----------------
        let isSync = cont.find('input.cb_sync[data-type="padding"]').is(':checked');
        if(isSync){
            const val = cont.find('input[name^="bsMargin-ps"]').val();    
            if(val>0) bsClasses.push('p-'+val);
        }else{
            cont.find('input[name^="bsMargin-p"]').each((i,item)=>{
                item = $(item);
                if(item.val()>0){
                    let cls = item.attr('name').split('-');
                    cls = cls[1]+'-'+item.val();
                    bsClasses.push(cls);
                }
            });
        }
        
        isSync = cont.find('input.cb_sync[data-type="margin"]').is(':checked');
        if(isSync){
            const val = cont.find('input[name^="bsMargin-ms"]').val();    
            if(val>0) bsClasses.push('m-'+val);
        }else{
            cont.find('input[name^="bsMargin-m"]').each((i,item)=>{
                item = $(item);
                if(item.val()>0){
                    let cls = item.attr('name').split('-');
                    cls = cls[1]+'-'+item.val();
                    bsClasses.push(cls);
                }
            });
        }
        
        }
                
//------------------------------------------------        

        function __setDim(name){
            let ele = cont.find('input[name="'+name+'"]');
            let val = ele.val();
            if( (val!='' || val!='auto') && parseInt(val)>0){
                if(!(val.indexOf('%')>0 || val.indexOf('px')>0 || val.indexOf('rem')>0)){
                    val = val + 'px';
                }
                css[name] = val;
            }
        }

        __setDim('width');
        __setDim('height');

        if(this.l_cfg.css){
            let old_css = this.l_cfg.css;
            //remove these parameters from css and assign new ones obtained from form
            let params = ['width','height',
                'color','background','background-image','bg-image','background-repeat','background-position','background-size',
                '--bs-border-style','--bs-border-color'];
            if(!this.hasUserStyles){
                params = params.concat(this.cssPaddingMarginBorder);
            }
            for(let i=0; i<params.length; i++){
                let prm = params[i];
                if (old_css[prm] && (prm.indexOf('margin')<0 || old_css[prm]!='auto')){ //drop old value
                    old_css[prm] = null;
                    delete old_css[prm];
                };
            }
            css = $.extend(old_css, css);
        }

        //update 
        //if(bsClasses.length>0){
        HCmsEditor.replaceBsClasses(this.element, this.allStyleBsClasses, bsClasses);
        this.l_cfg.bsClasses = HCmsEditor.getBsClassesAsString(this.element, this.allAffectedBsClasses);
        
        
        $(this.element).removeAttr('style');
        $(this.element).css(css); //assign changed css at once
        this.l_cfg.css = css;
console.log('new', css);        
        this.assignCssTextArea();
            
        this.onContentChange( true );
        return css;
  }//getCSS
  
  /*
   * Assigns CSS to direct edit css textarea: elementCss
   */
  assignCssTextArea(){

        let s = '';
        if(this.l_cfg.css){
            
            s = [];
            for(const [style, value] of Object.entries(this.l_cfg.css)){
                s.push(`${style}: ${value}`);
            }

            s = s.join(';\n');
            s += !window.hWin.HEURIST4.util.isempty(s) ? ';' : '';
        }
        
        this.container.find('textarea[name="elementCss"]').val(s);    
  }  
 
  
  /*
  *
  */  
  warningOnExit( callback ){
      
        if(this.container.find('.btn-save-only').attr('disabled')!='disabled'){
            
            let that = this;
            
            let $dlg;
            let _buttons = [
                {text:window.hWin.HR('Save'), 
                    click: function(){
                        that.container.find('.btn-save-only').trigger('click');
                        $dlg.dialog('close');
                        if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call(that, true);
                    }
                },
                {text:window.hWin.HR('Discard'), 
                    click: function(){
                        that.container.find('.btn-cancel').trigger('click');
                        $dlg.dialog('close');
                        if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call(that, false);
                    }
                },
                {text:window.hWin.HR('Cancel'), 
                    //TBD restore
                    click: function(){$dlg.dialog('close');}
                }
            ];            
            
            let sMsg = '"'+ window.hWin.HEURIST4.util.stripTags(this.l_cfg.name) 
                    +'" '+window.hWin.HR('element has been modified');
            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg, _buttons, {title:window.hWin.HR('Element changed')});   

            return true;     
        }else{
            return false;     
        }
  }

  /*
  * Verifies the presence of users styles
  * Hides border and margin panels
  * Shows converter button and explanation on "Direct edit" panel
  */
  #checkUserStyleSettings(){
      //1. Verifies the presence of users styles
      let params = this.cssPaddingMarginBorder;
      this.hasUserStyles = false;
      for(let i=0; i<params.length; i++){
          let prm = params[i];
          if (this.l_cfg.css[prm] && (this.l_cfg.css[prm]!='none') 
            && (prm.indexOf('margin')<0 || this.l_cfg[prm]!='auto'))
          {
              this.hasUserStyles = true;
              break;
          }
      }

      if(this.hasUserStyles){
          //2. Hides border and margin panels
          this.container.find('div[data-section="bsMargin"]').hide();
          this.container.find('div[data-section="bsBorder"]').hide();

          this.container.find('#properties_form').accordion({active:3});

          this.container.find('div.btn-css-convert').parent().show();
         
      }else{
          this.container.find('div[data-section="bsMargin"]').show();
          this.container.find('div[data-section="bsBorder"]').show();
          this.container.find('div.btn-css-convert').parent().hide();
      }
  }
  
  /*
  *
  */
  #convertUserStyles(){
      
      const res = HCmsEditor.convertToBootstrapClasses(this.l_cfg.css);

      HCmsEditor.replaceBsClasses(this.element, this.allStyleBsClasses, res.bsClasses);
      this.l_cfg.bsClasses = HCmsEditor.getBsClassesAsString(this.element, this.allAffectedBsClasses);

      if(res.css){
          //adds --bs-border-color and --bs-border-style
          this.l_cfg.css = $.extend(this.l_cfg.css, res.css);
      }

      //remove styles that will be converted to bootstrap classes      
      let params = this.cssPaddingMarginBorder;
      for(let i=0; i<params.length; i++){
          let prm = params[i];
          if (this.l_cfg.css[prm]
              && (prm.indexOf('margin')<0 || this.l_cfg[prm]!='auto'))
          {
              delete this.l_cfg.css[prm];
          }
      }

      console.log('convert to ',res.bsClasses); 
      this.#setCssToUI();
      this.#getCss();
      //this.assignCssTextArea();
      //this.onContentChange((true);
  }

  
}