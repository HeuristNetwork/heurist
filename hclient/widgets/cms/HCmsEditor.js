/*
* HCmsEditor.js - web page editor
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

/* global editCMS_SiteMenu */

/*
* HCmsEditor.js - web page editor
*/
class HCmsEditor {

    //state values    
    default_palette_class = 'ui-heurist-publish';    
    editor_pos = 'west';
    isWebPage = false;
    current_edit_mode = 'page'; //or website

    //refs to helper classes
    _editCMS_SiteMenu = null;
    _cmsEditorPage = null;
    
    //interface elements
    _editor_panel = null; // tab control with two trees - website menu and page structure
    
    _toolbar_WebSite; //move to editCMS_SiteMenu
    _tabControl;
    
    _webPageFrame;
    
    //website specific values
    menuContentJSON; // menu content as JSON
                
    //
    webSite;
    website_id; // current website
    page_id;    // current page
    current_language = 'def';
    default_language = 'def';

    mainMenu; //main menu widget    
    layout_container; // main-content with CMS content
    _ws_body;
    
    _keep_EditPanelWidth = 0;

  constructor(_options, _container) {
    
    this.website_id = _options.website_id;
    this.page_id = _options.page_id;
      
    this._ws_body = _container?$(_container):$('body');  
    
    this.#initInterface();
  }
  
  /**
  * Inits my editor layout - editor on the left and website in iframe in the center
  */
  #initInterface(){
      
        let that = this;
      
        if(!this._editor_panel){ //$(this.document).find('.editStructure').length==0
        
            this._editor_panel = this._ws_body.find('#tabsEditCMS'); 
            
            window.onbeforeunload = this.onBeforeUnload;
            
                this._editor_panel = $('div.ui-layout-west');
            
                    let layout_opts =  {
                        applyDefaultStyles: true,
                        maskContents:       true,  //alows resize over iframe
                        //togglerContent_open:    '&nbsp;',
                        //togglerContent_closed:  '&nbsp;',
                        center:{
                            minWidth:400,
                            contentSelector: '.heurist-website', //@todo !!!! for particule template heurist-website can be missed
                            //pane_name, pane_element, pane_state, pane_options, layout_name
                            onresize_end : function(){
                                //that.handleTabsResize();                            
                            }    
                        }
                    };
                    
                    layout_opts[this.editor_pos] = {
                        size: 230, //@todo this.usrPreferences.structure_width,
                        maxWidth:800,
                        minWidth:230,
                        spacing_open:6,
                        spacing_closed:40,  
                        togglerAlign_open:'center',
                        //togglerAlign_closed:'top',
                        togglerAlign_closed:16,   //top position   
                        togglerLength_closed:80,  //height of toggler button
                        initHidden: false, //!this.options.edit_structure,   //show structure list at once 
                        initClosed: false, //!this.options.edit_structure && (this.usrPreferences.structure_closed!=0),
                        slidable:false,  //otherwise it will be over center and autoclose
                        contentSelector: '.editStructure',   
                        onopen_start : function( ){ 
                            let tog = that._ws_body.find('.ui-layout-toggler-'+that.editor_pos);
                            tog.removeClass('prominent-cardinal-toggler togglerVertical');
                            tog.find('.heurist-helper2.'+that.editor_pos+'TogglerVertical').hide();
                        },
                        onclose_end : function( ){ 
                            let tog = that._ws_body.find('.ui-layout-toggler-'+that.editor_pos);
                            tog.addClass('prominent-cardinal-toggler togglerVertical');

                            if(tog.find('.heurist-helper2.'+this.editor_pos+'TogglerVertical').length > 0){
                                tog.find('.heurist-helper2.'+this.editor_pos+'TogglerVertical').show();
                            }else{

                                let margin = (this.editor_pos=='west') ? 'margin-top:270px;' : '';
                                $('<span class="heurist-helper2 '+this.editor_pos+'TogglerVertical" style="width:270px;'+margin+'">Menu structure and page content</span>').appendTo(tog);
                            }
                        },
                        onresize_end: function(){
                            let width = that._ws_body.layout().state['west']['outerWidth'] <= 215 ? '60%' : '70%';
                            that._editor_panel.find('a.website-url').css('width', width);
                        },
                        togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-'+(this.editor_pos=='west'?'w':'e')+'"></div>',
                        togglerContent_closed:  '<div class="ui-icon ui-icon-carat-2-'+(this.editor_pos=='west'?'e':'w')+'"></div>',
                    };

                    this._ws_body.layout(layout_opts); //.addClass('ui-heurist-bg-light')

                    let tog = this._ws_body.find('.ui-layout-toggler-'+this.editor_pos);
                    tog.addClass('prominent-cardinal-toggler togglerVertical');

                    if(tog.find('.heurist-helper2.'+this.editor_pos+'TogglerVertical').length > 0){
                        tog.find('.heurist-helper2.'+this.editor_pos+'TogglerVertical').show();
                    }else{

                        let margin = (this.editor_pos=='west') ? 'margin-top:270px;' : '';
                        $('<span class="heurist-helper2 '+this.editor_pos+'TogglerVertical" style="width:270px;'+margin+'">Menu structure and page content</span>').appendTo(tog);
                    }
                    
            this.#initEditControls();
           
        }//editor panel is already inited
        
        this._ws_body.layout().show(this.editor_pos, true );
      
        //load the entire website
        this.loadWebSite();
      
  }

  //
  // Init interface controls
  //  
  #initEditControls(need_callback){
      
        let that = this;

        this._editor_panel.find('.btn-website-homepage').on('click', ()=>that.#editHomePage()); //load home page content
        
        this._editor_panel.find('.btn-website-header').on('click', ()=>that.onMarginEdit(true));
        this._editor_panel.find('.btn-website-footer').on('click', ()=>that.onMarginEdit(false));

        
        this._editor_panel.find('.btn-website-edit').on('click', ()=>that.#editHomePageRecord()); //open record edit
        
        if(!this.isWebPage){
            this._editor_panel.find('.btn-website-edit')
                         .button({classes:{'ui-button': 'ui-button-action'}})
                         .css({'padding':'5px','font-size':'smaller'});
        }


        this._editor_panel.find('.btn-website-addpage').on('click', ()=>that.#addNewRootMenu());

        let url = window.hWin.HEURIST4.ui.getCmsLink({websiteid:this.website_id});
        
        this._editor_panel.find('.website-url').text(url).attr('title', `Click to copy ${url} to clipboard`).on('click', function(){ // save website url to clipboard
            window.hWin.HEURIST4.util.copyStringToClipboard(`${url}`);
            window.hWin.HEURIST4.msg.showMsgFlash('Website URL saved to clipboard', 3000);
        });

        this._editor_panel.find('.btn-website-homepage').parent()
        //.addClass('fancytree-node')
        .on( 'mouseenter', function(event){ 
            that._editor_panel.find('.btn-website-addpage').show();
        } )
        .on( 'mouseleave', function(event){
            that._editor_panel.find('.btn-website-addpage').hide();
        } );
        
        this._editor_panel.find('.bnt-cms-hidepanel').on('click', function(){ that._ws_body.layout().close(that.editor_pos); } );
     
        this._toolbar_WebSite = this._editor_panel.find('.toolbarWebSite');

        this._tabControl = this._editor_panel.find('#tabsEditCMS');

        this._tabControl.tabs({
            activate: function( event, ui ){
                that.switchMode();
                //ui.newTab
            },
            beforeActivate: function( event, ui ){

                if(that.current_edit_mode=='page' && that._cmsEditorPage.warningOnExit(function(){ that.switchMode( 'website' ) })) {
                    return false;  
                }else{
                    return true;
                }
            }
        });
        
        this._tabControl.addClass('ui-heurist-publish');
        this._tabControl.find('.ui-tabs-nav')[0].style.setProperty('background', 'none', 'important');
        this._tabControl.find('.ui-tabs-nav')[0].style.setProperty('padding', '0px', 'important');
        
        if(this.isWebPage){
            this._tabControl.find('.ui-tabs-tab[aria-controls="treeWebSite"]').hide();
        }
  }
  
  
  //
  // Reload the entire website
  //  
  loadWebSite(new_page_id){
      
        if(new_page_id>0 && new_page_id!=this.page_id){
            this.page_id = new_page_id;
        }
      
        this._webPageFrame = $('#webPageFrame');
        const pageURL = window.hWin.HEURIST4.ui.getCmsLink({websiteid:this.website_id,pageid:this.page_id,version:3,mode:'edit',edit:2});
        this._webPageFrame.attr('src', pageURL);
  }
  
  //
  // This is event for (first)initial loading in iframe
  // For particular page - onLoadPageContent
  //
  onWebSiteLoad()
  {
      //website menu as json tree
      if(this._webPageFrame[0].contentWindow){
        this.webSite = this._webPageFrame[0].contentWindow.webSite;
        this.menuContentJSON = this.webSite.siteMenu;
        this.website_id = this.webSite.siteId;
        this.page_id = this.webSite.pageId;
      }else{
        this.menuContentJSON = [];
        this.website_id = 0;
        this.page_id = 0;
      }
      
      if(this._editCMS_SiteMenu){
          //refresh website structure tree
          this._editCMS_SiteMenu.initControls();
      }
      
      this.mainMenu = null;
  }  
  
  
  //
  // called from editCMS_SiteMenu - load different page into target element
  //
  loadPageContent(pageId){
      
    if(this.warningOnExit()){
        return;
    }
      
    if(!window.hWin.HEURIST4.util.isPositiveInt(pageId)){
        pageId = this.pageId;
    }

    if(this._webPageFrame[0].contentWindow.webSite){
        this._webPageFrame[0].contentWindow.webSite.loadPage( {pageId:pageId} );
    }
    //this._webPageFrame[0].contentWindow.HAPI4.actionHandler.executeActionById('data-heurist-pageid', {pageId:pageId}); 
    //, callback:(rec)=>this.onLoadPageContent(rec)}
  }

  //
  //  Event handler on page load completed
  //
  onLoadPageContent(record){

      let that = this;

      /*
      if(!this.mainMenu){
          this.mainMenu = this._webPageFrame[0].contentDocument.getElementById('main-menu');
          if(this.mainMenu && $(this.mainMenu).HMenu('instance')){
              $(this.mainMenu).HMenu('option','onBeforeAction', ()=>{
                  return that.warningOnExit();
              });
          }
      }
      */

      //TBD change to webSite.getContainer
      this.layout_container = this._webPageFrame[0].contentDocument.getElementsByTagName('main');
      if(!this.layout_container){
          this.layout_container = this._webPageFrame[0].contentDocument.getElementById('main-content');
      }

      this.layout_container = $(this.layout_container);

      if(!this._cmsEditorPage){
          this._cmsEditorPage = new HCmsEditorPage(this._editor_panel, this);    
      }

      this.page_id = record['rec_ID'];

      if(this._editCMS_SiteMenu) this._editCMS_SiteMenu.highlightCurrentPage();

      //swtich to page tab automatically
      this.layout_container.on('click',function(event){
          if(that.current_edit_mode!='page'){
              //switch to page mode                
              that.switchMode('page');
          }
      });
      
      //swtich to page tab automatically
      let header = this._webPageFrame[0].contentDocument.getElementsByTagName('header'); //TBD or main-header
      let footer = this._webPageFrame[0].contentDocument.getElementsByTagName('footer'); 
      
      //$(header).on('click',()=>that.onMarginEdit(true));
      //$(footer).on('click',()=>that.onMarginEdit(false));
      
      this.layout_container.on('click',function(event){
          if(that.current_edit_mode!='page'){
              //switch to page mode                
              that.switchMode('page');
          }else{
              that._cmsEditorPage.hideMarginProperties();
          }
      });

      this._cmsEditorPage.initPage(this.layout_container, record);
      this.switchMode('page');
  }
  
  /*
  * Start edit header or footer by click in SiteMenu
  */
  onMarginEdit(isHeader){
      this._cmsEditorPage.showMarginProperties(isHeader);
      this.switchMode('page');
  }
  
  //
  // Returns tinymce object from webpage iframe
  //  
  getTinymce(){
      return this._webPageFrame[0].contentWindow.tinymce;
  }
  
  //
  // Returns html element from webpage iframe
  //  
  findInWebSite(selector){
      const matches = this._webPageFrame[0].contentDocument.querySelectorAll(selector);
      return matches.length>0 ?matches[0] :null;
  }

  //
  // Returns HAPI object from webpage iframe
  //  
  getHapi(){
      return this._webPageFrame[0].contentWindow.HAPI4;
  }
  
  //
  // loads home page content
  //  
  #editHomePage(){
      let that = this;  
      if(this.warningOnExit( ()=>that.#editHomePage() )) return;                           
      //reload content of page
      this.loadPageContent( this.website_id );
  }
  
  /*
  * Returns true if action is blocked
  */
  warningOnExit(callback){
      return (this._cmsEditorPage && this._cmsEditorPage.warningOnExit( callback ));                           
  }
  
  //
  // edit home page record (record editor)
  //
  #editHomePageRecord(){

      let that = this;
      if(this.warningOnExit( ()=>that.#editHomePageRecord() )) return;                           
      
      //if(!_editCMS_SiteMenu && !isWebPage)
      //    _editCMS_SiteMenu = editCMS_SiteMenu( _panel_treeWebSite, that );

      //edit menu item
      window.hWin.HEURIST4.ui.openRecordEdit(this.website_id, null,
          {selectOnSave:true, 
              edit_obstacle: false, 
              onClose: function(){ 

              },
              onselect:function(event, data){
                  if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                      //reload entire site
                      that.loadWebSite(that.website_id);
                  }
      }});
  }
  
  /**
  * Add new root menu/page
  */
  #addNewRootMenu(){

        if(this._editCMS_SiteMenu){
          //refresh website structure tree
          this._editCMS_SiteMenu.selectMenuRecord(this.website_id);
        }
  }
  
  //#showWebSiteMenu(){}

  //
  // Open editor panel
  //
  openEditorPanel(){
        this._ws_body.layout().open(this.editor_pos);
  }
  
  //
  // Set width of editor panel min 450px
  //
  expandEditorPanel(){
      if(this._ws_body.layout().state['west']['outerWidth']<450){
          this._keep_EditPanelWidth = this._ws_body.layout().state['west']['outerWidth'];
          this._ws_body.layout().sizePane('west', 450);    
      }
  }

  shrinkEditorPanel(){
      if(this._keep_EditPanelWidth>0){
          this._ws_body.layout().sizePane('west', this._keep_EditPanelWidth);    
      }
      this._keep_EditPanelWidth = 0;
  }


  /**
  *  
  */
  switchMode(mode){

        if(!mode){
            if(this._tabControl.tabs('option','active')==0){
                mode='website';           
            }else{
                mode='page';
            }
        }else{
            let activePage = (mode=='page')?1:0;
            if(this._tabControl.tabs('option','active')!=activePage){
                this._tabControl.tabs({active:activePage});
                return;    
            }
        }
        
        this.current_edit_mode = mode;
        
        if(mode=='page'){

            this._tabControl.find('li[aria-controls="treeWebSite"]')
                .removeClass('ui-cms-mainmenu');
                                
            //this.#hidePropertyView();
            
            this._toolbar_WebSite.hide();
            
            this._cmsEditorPage.initActionIcons();
            
        }else{

            this._tabControl.find('li[aria-controls="treeWebSite"]')
                .removeClass('ui-state-active') //ui-tabs-active 
                .addClass('ui-cms-mainmenu');
                                
            
            //remove highlights
            if(this._cmsEditorPage){
                this._cmsEditorPage.hidePropertyView();
                //TBD this._toolbar_Page.hide();
                this._cmsEditorPage.hideOverlayAll();
                this._cmsEditorPage.detachTinyMCE(false);
            }
            this._toolbar_WebSite.show();


            //load website menu treeview
            if(!this._editCMS_SiteMenu)
            this._editCMS_SiteMenu = editCMS_SiteMenu( this._editor_panel.find('.treeWebSite'), this );
           
        }
  }
       
  //
  //
  //
  onBeforeUnload(){
      
  }    
  
  /*
  * Utility for working with classes
  */
  //
  // replace classes with given prefix with new classes
  //
  static replaceBsClasses(element, removeWithPrefix, newClasses){

      let classes = Array.from(element.classList);

      classes = HCmsEditor.removeBsClasses(classes, removeWithPrefix); //returns array
      /*        
      const bsClasses = Array.isArray(removeWithPrefix)?removeWithPrefix:[removeWithPrefix];
      classes = classes.filter(function(value) {
      const res = bsClasses.some(substr => value.startsWith(substr));
      return !res;
      });        
      */

      if(Array.isArray(newClasses)){
          classes = classes.concat(newClasses);    
      }else if(newClasses!='' && newClasses!=null){
          classes.push(newClasses);
      }
      element.classList = classes.join(' ');
      
      return element.classList;
  }    

  //
  // returns only classes started with the given prefix
  //
  static getBsClasses(classes, withPrefix){
      return HCmsEditor.getOrRemoveClasses(classes, withPrefix, false);
  }

  //
  // Remove classes with given previx
  //
  static removeBsClasses(classes, withPrefix){
      return HCmsEditor.getOrRemoveClasses(classes, withPrefix, true);
  }

  //
  //
  //
  static getOrRemoveClasses(classes, withPrefix, isRemove){

      const isArray = Array.isArray(classes);

      if(window.hWin.HEURIST4.util.isempty(classes)){
          return isArray?[]:'';   
      }

      if(!isArray){
          classes = classes.split(' ');    
      }

      const bsClasses = Array.isArray(withPrefix)?withPrefix:[withPrefix];

      //return all with prefixes
      classes = classes.filter(function(value) {
          let res = bsClasses.some(substr => value.startsWith(substr));
          if(isRemove){
              res = !res;
          }
          return res;
      });        

      return isArray?classes:classes.join(' ');
  }
  
}