/*
* HCmsEditorMargins.js - editor for header or footer
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

/*
* HCmsEditorMargin.js - editor for header or footer
*/
class HCmsEditorMargin {

  cmsEditor;
  onClose;
  siteId;
  isHeader;
  
  recordHome;
  oldContent;
  newContent;
  
  DT_CONTENT;

  constructor(options) {

      this.cmsEditor = options.cmsEditor;
      this.container = options.container;
      this.onClose = options.onClose;
      this.isHeader = options.isHeader;
      
      this.siteId = this.cmsEditor.website_id;
      
      this.DT_CONTENT = window.hWin.HAPI4.sysinfo['dbconst'][this.isHeader?'DT_CMS_HEADER':'DT_CMS_FOOTER'];
      
      let that = this;
      this.container.empty().load(window.hWin.HAPI4.baseURL
          +'hclient/widgets/cms/HCmsEditorMargin.html',
          ()=>that.#loadHomeRecord());
  }

  /*
  * Loads record values for raw header,footer,title,background
  */
  #loadHomeRecord(){
      
        const server_request = {
                        q: 'ids:'+this.siteId,
                        restapi: 1,
                        columns: [this.DT_CONTENT],
                        zip: 1,
                        format:'json'};
                        
        let that = this;
                        
        //perform search see record_output.php       
        window.hWin.HAPI4.RecordMgr.search_new(server_request,
            function(response){
              
                if(window.hWin.HEURIST4.util.isJSON(response)) {
                    
                    
                    let record = response['records'];
                    if(record && record.length>0){
                        record = record[0];
                        that.recordHome = record['details'];
                        
                        that.oldContent = window.hWin.HAPI4.getTranslation(that.recordHome[that.DT_CONTENT], null);
                        that.newContent = that.oldContent;
                        
                        that.#initControls();
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: `Web Home Page not found (record #${that.siteId})`,
                            error_title: 'Failed to load home page'
                        });
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
      
  }
  
  /*
  * Save given header field for home record and reloads the header/footer
  */  
  #updateFieldInHomeRecord(fieldId, newValue){
      
        let request = {a: 'addreplace',
                        recIDs: this.siteId,
                        dtyID: fieldId,
                        rVal: newValue,
                        needSplit: false};
        
        window.hWin.HEURIST4.msg.bringCoverallToFront();
        
        let that = this;
        
        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                if(response.status != window.hWin.ResponseStatus.OK){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }
                
        });         
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
                        $(sel), [{key:'',title:'select...'}], 
                           '',   //that.options.editOptions[sel.name], 
                           {cms:that.isHeader?'header':'footer', extraOptions: {menu_parent: cont}, 
                            eventHandlers:{onSelectMenu:function(event){
                                //get new content and then reload header/footer
                                that.#getTemplateContent($(event.target).val(), function(response){
                                        if(response?.message){
                                            that.newContent = response.message;
                                            that.cmsEditor.webSite.reloadMargin( that.isHeader, that.newContent );
                                            that.#onContentChanged();
                                        }
                                });
                            } }  });
                        }); 
                        
      let codeEditor = cont.find('#codemirror-container').HCmsCodeEditor({title:this.isHeader?'Page Header':'Page Footer',
                                                                          onClose:(context)=>that.onCodeEditorApply(context)});
      
      cont.find('div.btn-html-edit').button().on('click', function(){
                if(!that.newContent){
                    that.#getTemplateContent('', (response)=>codeEditor.HCmsCodeEditor('show', response?.message));
                }else{
                    codeEditor.HCmsCodeEditor('show', that.newContent);
                }
      });

      //save entire page (in background)
      cont.find('.btn-save-and-close').button().css('border-radius','4px').on('click', function(){
          that.#updateFieldInHomeRecord(that.DT_CONTENT, that.newContent);
          that.onClose.call();
      });

      cont.find('.btn-save-only').button().css('border-radius','4px').on('click', function(){
          that.#updateFieldInHomeRecord(that.DT_CONTENT, that.newContent);
          that.oldContent = that.newContent;
          that.#onContentChanged();
      });
      cont.find('.btn-cancel').css('border-radius','4px').button().on('click', function(){
          //restore old settings 
          that.newContent = that.oldContent;
          this.cmsEditor.webSite.reloadMargin( this.isHeader );
          that.onClose.call();
      });


  }
 
  /*
  *
  */
  #onContentChanged(){
      const isNotChanged = (this.newContent==this.oldContent);
      window.hWin.HEURIST4.util.setDisabled(this.container.find('.btn-save-only'), isNotChanged);
      window.hWin.HEURIST4.util.setDisabled(this.container.find('.btn-save-and-close'), isNotChanged);
  }  
 
  /*
  * Load raw template and update header/footer on template selection
  */  
  #getTemplateContent(templateName, callback){
      
        let request = {website:this.siteId, raw:1, ver:3};
        request[this.isHeader?'header':'footer'] = templateName;
        
        let that = this;
        
        window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL, request, null, callback);
  }
                                
  /*
  * On code editor exit
  */  
  onCodeEditorApply(newContent){
      if(!newContent){
          return;
      }
      this.newContent = newContent;
      this.cmsEditor.webSite.reloadMargin( this.isHeader, newContent );
      //this.#updateFieldInHomeRecord(window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER'], newContent);
  }

}