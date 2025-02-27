/*
* HLayoutMgr.js - web page generator based on json configuration
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* HCmsEditor.js - web page editor
*/
class HCmsEditor {

    //state values    
    default_palette_class = 'ui-heurist-publish';    
    editor_pos = 'west';
    isWebPage = false;
    current_edit_mode = 'page'; //or website
    page_was_modified = false;

    //refs to helper classes
    _editCMS_SiteMenu = null;
    _editCMS_Page = null;
    
    //interface elements
    _editor_panel = null; // tab controle with to trees - website menu and page structure
    
    _toolbar_WebSite; //move to editCMS_SiteMenu
    _tabControl;
    
    _webPageFrame;
    
    //website specific values
    menuContentJSON;    // menu content as JSON
    pageContentJSON; //_layout_content; - JSON config 
                
    //
    website_id; // current website
    page_id;    // current page
    
    layout_container; // main-content with CMS content
    _ws_body;
    
    _keep_EditPanelWidth;

    
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
            
            /* TBD add tinymce and codemirror
            if(typeof tinymce === 'undefined'){
                _loadTinyMCE(function(){_startCMS(_options)});
                return;
            }
            */

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
      
        //load content of page
        this._webPageFrame = $('#webPageFrame');
        this._webPageFrame.attr('src', window.hWin.HEURIST4.ui.getCmsLink({websiteid:this.website_id,pageid:this.page_id,version:3,edit:2}));
        this._webPageFrame.on('load', function(){
            that.onWebPageLoadComplete();
        });
      
  }
  
  //
  // Init interface controls
  //  
  #initEditControls(need_callback){
      
        let that = this;

        this._editor_panel.find('.btn-website-homepage').on('click', this.#editHomePage);

        this._editor_panel.find('.btn-website-edit').on('click', this.#editHomePageRecord);
        if(!this.isWebPage){
            this._editor_panel.find('.btn-website-edit')
                         .button({classes:{'ui-button': 'ui-button-action'}})
                         .css({'padding':'5px','font-size':'smaller'});
        }

        this._editor_panel.find('.btn-website-addpage').on('click', this.#addNewRootMenu); // button({icon:'ui-icon-plus'}).

        let url = window.hWin.HEURIST4.ui.getCmsLink({websiteid:this.website_id,version:3});
        
        this._editor_panel.find('.website-url').text(url).attr('title', `Click to copy ${url} to clipboard`).on('click', function(){ // save website url to clipboard
            window.hWin.HEURIST4.util.copyStringToClipboard(`${url}`);
            window.hWin.HEURIST4.msg.showMsgFlash('Website URL saved to clipboard', 3000);
        });

        this._editor_panel.find('.btn-website-homepage').parent()
        .addClass('fancytree-node')
        .on( 'mouseenter', function(event){ 
            that._editor_panel.find('.btn-website-addpage').show();
        } )
        .on( 'mouseleave', function(event){
            that._editor_panel.find('.btn-website-addpage').hide();
        } );
        
        this._editor_panel.find('.bnt-website-menu').button({icon:'ui-icon-menu'}).on('click', this.#showWebSiteMenu);
        
        this._editor_panel.find('.bnt-cms-hidepanel').on('click', function(){ that._ws_body.layout().close(that.editor_pos); } );
     
        this._toolbar_WebSite = this._editor_panel.find('.toolbarWebSite');

        this._tabControl = this._editor_panel.find('#tabsEditCMS');

        this._tabControl.tabs({
            activate: function( event, ui ){
                that.switchMode();
                //ui.newTab
            },
            beforeActivate: function( event, ui ){

                if(that.current_edit_mode=='page' && that.warningOnExit(function(){ that.switchMode( 'website' ) })) {
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
  // called from editCMS_SiteMenu - load different page
  //
  loadPageContent(page_id){
      this._webPageFrame[0].contentWindow.HAPI4.actionHandler.executeActionById('data-heurist-pageid', {page_id:page_id});;
  }

  //
  //
  //
  onLoadPageContent(record){
    
      this.layout_container = this._webPageFrame[0].contentDocument.getElementsByTagName('main');
      if(!this.layout_container){
          this.layout_container = this._webPageFrame[0].contentDocument.getElementsById('main-content');
      }

      this.layout_container = $(this.layout_container);
      
      if(!this._editCMS_Page){
        this._editCMS_Page = new HCmsEditorPage(this._editor_panel, this);    
      }
    
      //console.log('>>>', record);
      
      this.page_id = record['rec_ID'];
      
      //this._editCMS_Page.initPage(record);
  }
  
  //
  // This is event for initial loading in iframe
  //
  onWebPageLoadComplete(){
      
      //menu as json tree
      this.menuContentJSON = this._webPageFrame[0].contentWindow.menuContentJSON;
      
  }
  
 
  #editHomePage(){
      
  }
  
  #editHomePageRecord(){
      
  }
  
  #addNewRootMenu(){
      
  }
  
  #showWebSiteMenu(){
      
  }
  
  #updateActionIcons(){
      
  }

  /**
  *  
  */
  switchMode(mode, init_tinymce){

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
                                
            //TBD this.#hidePropertyView();
            
            this._toolbar_WebSite.hide();
           
/* TBD           
            if(init_tinymce!==false){
                $.ui.fancytree.getTree( this._panel_treePage ).visit(function(node){
                    node.setSelected(false); //reset
                    node.setExpanded(true);
                });            
                this.#updateActionIcons(500);//it inits tinyMCE also
            } //_initTinyMCE
*/            
            
        }else{

            this._tabControl.find('li[aria-controls="treeWebSite"]')
                .removeClass('ui-state-active') //ui-tabs-active 
                .addClass('ui-cms-mainmenu');
                                
            //TBD this.#hidePropertyView();
            //TBD this._toolbar_Page.hide();
            
            this._toolbar_WebSite.show();

            //remove highlights
            this.layout_container.find('.lid-actionmenu').hide();
            this.layout_container.find('div[data-hid]').removeClass('cms-element-active');                        
            this.layout_container.find('.cms-element-overlay').css('visibility','hidden');            
/*TBD            
            
            if(tinymce) tinymce.remove('.tinymce-body');
*/            
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
 
  warningOnExit(){
      
  }
  
  resetModified(){
    this.page_was_modified = false;    
  }
  
    
}