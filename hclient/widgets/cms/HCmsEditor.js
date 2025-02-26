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
    _edit_Element = null;  //instance of edit element class editCMS_ElementCfg
    _editCMS_SiteMenu = null;
    
    //interface elements
    _editor_panel = null;
    _panel_treePage;     // panel with treeview for current page 
    _panel_treeWebSite;  // panel with tree menu - website structure
    _panel_propertyView; // panel with selected element properties
    _toolbar_WebSite;
    _toolbar_Page;
    _tabControl;
    
    _webPageFrame;
    
    //website specific values
    menuContentJSON;    // menu content as JSON
    pageContentJSON; //_layout_content; - JSON config 
                
    //
    website_id; // current website
    page_id;    // current page
    
    _layout_container; // main-content with CMS content
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
     
        this._panel_propertyView = this._editor_panel.find('.propertyView');
        this._panel_treeWebSite = this._editor_panel.find('.treeWebSite');
        
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
  //
  //
  onWebPageLoadComplete(){
      
      //menu as json tree
      this.menuContentJSON = this._webPageFrame[0].contentWindow.menuContentJSON;
      
      this._layout_container = this._webPageFrame[0].contentDocument.getElementsByTagName('main');
      if(!this._layout_container){
          this._layout_container = this._webPageFrame[0].contentDocument.getElementsById('main-content');
      }
console.log(this._layout_container);      
      this._layout_container = $(this._layout_container);
      
      //this.#initPage()
      this.#initTreePage([]);
      
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
  
    //
    // Opens element/widget property editor  (editCMS_ElementCfg/WidgetCfg)
    // 1. css properties
    // 2  flexbox properties
    // 3. widget properties
    //
  #layoutEditElement(ele_id){
      
        return;

        if(_edit_Element){ //already opened - save previous
            
            if(this._layout_container.find('div.cms-element-editing').attr('data-hid')==ele_id) return; //same
            
            //save previous element
            if(_edit_Element.warningOnExit(function(){that.#layoutEditElement(ele_id);})) return;
            
            this._layout_container.find('div[data-hid]').removeClass('cms-element-editing headline marching-ants marching');                        
        }

      
        //1. show div with properties over treeview
        let h = _panel_treePage.find('ul.fancytree-container').height() + 10;

        h = (h<175)?h:175; 
        _panel_treePage.css('height',h+'px');
        _panel_propertyView.css('top',(h+20)+'px');
        _editor_panel.find('.page_tree').hide();
        _toolbar_Page.hide();
        
        _panel_propertyView.fadeIn(500);
        if(_ws_body.layout().state['west']['outerWidth']<450){
            _keep_EditPanelWidth = _ws_body.layout().state['west']['outerWidth'];
            _ws_body.layout().sizePane('west', 450);    
        }

        //scroll tree that selected element will be visible
        let node = $.ui.fancytree.getTree( _panel_treePage ).getNodeByKey(ele_id);
        let top1 = $(node.li).position().top;
        _panel_treePage.animate({scrollTop: $(node.li).offset().top}, 1);
        _panel_treePage.find('span.fancytree-title').css({'font-style':'normal','text-decoration':'none'});
        $(node.li).find('.fancytree-node').removeClass('fancytree-active');
        $(node.li).find('span.fancytree-title:first').css({'font-style':'italic','text-decoration':'underline'}); //
        $(node.li).find('.fancytree-node:first').addClass('fancytree-active');
        
        _hideMenuInTree();
        
        this._layout_container.find('.cms-element-overlay').css('visibility','hidden'); //hide overlay above editing element
        this._layout_container.find('div[data-hid]').removeClass('cms-element-active');                        
        
        let ele = this._layout_container.find('div[data-hid="'+ele_id+'"]').addClass('cms-element-editing');

        if(!ele.css('background-image') || ele.css('background-image')=='none'){
            ele.addClass('headline marching-ants marching');
        }
        
        let element_cfg = window.hWin.layoutMgr.layoutContentFindElement(_layout_content, ele_id);  //json
        
        let is_cardinal = (element_cfg.type=='north' || element_cfg.type=='south' || 
                element_cfg.type=='east' || element_cfg.type=='west' || element_cfg.type=='center');
            
        if(is_cardinal){
             //find parent
             const node = $.ui.fancytree.getTree( _panel_treePage ).getNodeByKey(''+ele_id);
             const parentnode = node.getParent();
             ele_id = parentnode.key;
             element_cfg = window.hWin.layoutMgr.layoutContentFindElement(_layout_content, ele_id);
        }
        
        //show overlay for editing element
        _showOverlayForElement( ele_id );
        
        _initTinyMCE( ele_id );

        //
        // mode - 0       take values from _edit_Element without saving in db
        //        'save'  save entire page in db
        //
        _edit_Element = editCMS_ElementCfg(element_cfg, _layout_content, this._layout_container, this._panel_propertyView, function(new_cfg, mode){

                    //save
                    if(new_cfg){
                        
                        window.hWin.layoutMgr.layoutContentSaveElement(_layout_content, new_cfg); //replace element to new one

                        //update treeview                    
                        let node = $.ui.fancytree.getTree( _panel_treePage ).getNodeByKey(''+new_cfg.key);
                        node.setTitle(new_cfg.title);
                        _defineActionIcons($(node.li).find('span.fancytree-node:first'), new_cfg.key, 
                                    'position:absolute;right:8px;padding:2px;margin-top:0px;');
                               
                        if(new_cfg.type=='cardinal'){
                            //recreate cardinal layout
                            window.hWin.layoutMgr.layoutInitCardinal(new_cfg, this._layout_container);
                        }
                        
                        //save page
                        that.#saveLayoutCfg(); 
                        page_was_modified = false;
                        
                        _onPageChange();
                    }
                    
                    if(mode!='save'){
                        //close element config
                        _hidePropertyView();
                    }

                    // find all dragable elements - text and widgets
                    this._layout_container.find('div.brick').each(function(i, item){   //
                        let ele_ID = $(item).attr('data-hid');
                        
                        _defineActionIcons(item, ele_ID, 'position:absolute;z-index:999;');   //left:2px;top:2px;         
                    });

                    
                }, page_was_modified );
    }
    
    
    //
    // Add text element or widget
    // 1. Find parent element for "ele_id"
    // 2. Add json to _layout_content
    // 3. Add element to _layout_container
    // 4. Update treeview
    //
    // @todo - store templates as json text 
    #layoutInsertElement(ele_id, widget_type, widget_name){
        
        return;
        
       
        
        let new_ele = {name:'Text', type:'text', css:{'border':'1px dotted gray','border-radius':'4px','margin':'4px'}, content:"<p>Lorem ipsum dolor sit amet ...</p>"};
        
        if(widget_type=='group'){
            new_ele = {name:'Group', type:'group', css:{'border':'1px dotted gray','border-radius':'4px','margin':'4px'}, children:[ new_ele ]};
        }else if(widget_type=='tabs'){
            
            new_ele = {name:'TabControl', type:'tabs', css:{}, children:[ 
                {name:'Tab 1', type:'group', css:{}, children:[ new_ele ]},
                {name:'Tab 2', type:'group', css:{}, children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]}
            ]};
        }else if(widget_type=='accordion'){    
            new_ele = {name:'Accordion', type:'accordion', css:{}, children:[ 
                {name:'Panel 1', type:'group', css:{}, children:[ new_ele ]}
            ]};
        }else if(widget_type=='cardinal'){    
            
            new_ele = {name:'Cardinal', type:'cardinal', css:{position:'relative',
                        'min-height':'300px','min-width':'300px',
                        'height':'500px','width':'800px',flex:'0 1 auto'},  //,'width':'100%'
            children:[
            {name:'Center', type:'center', children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
            {name:'North', type:'north', options:{size:80}, children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
            {name:'South', type:'south', options:{size:80}, children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
            {name:'West', type:'west', children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
            {name:'East', type:'east', children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]}
            ]};
          
        }else if(widget_type.indexOf('heurist_')===0){
            
            //btn_visible_newrecord, btn_entity_filter, search_button_label, search_input_label
            new_ele = {appid:widget_type, name:widget_name, css:{}, options:{}};
            
        }
        else if(widget_type=='group_2'){
            
            new_ele = {name:'2 columns', type:'group', css:{display:'flex', 'justify-content':'center'},
                children:[
                    {name:'Column 1', type:'group', css:{flex:'1 1 auto'}, children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
                    {name:'Column 2', type:'group', css:{flex:'1 1 auto'}, children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]}
                ]
            };
            
        }
        else if(widget_type=='text_media'){
            
            new_ele = {name:'Media and text', type:'group', css:{display:'flex', 'justify-content':'center'},
                children:[
                {name:'Media', type:'text', css:{flex:'0 1 auto'}, 
                    content:"<p><img src=\""+window.hWin.HAPI4.sysinfo.referenceServerURL+"hclient/assets/v6/logo.png\" width=\"300\"</p>"},
                {name:'Text', type:'text', css:{flex:'1 1 auto'}, 
                    content:"<p>Lorem ipsum dolor sit amet ...</p>"}
                ]
            };
            
        }
        else if(widget_type=='text_banner'){

            let imgs = [
 'https://images.unsplash.com/photo-1524623243236-187b50e18f9f?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1228&q=80',
 'https://images.unsplash.com/photo-1494500764479-0c8f2919a3d8?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1170&q=80',
 //'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?ixlib=rb-1.2.1&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1171&q=80',
 'https://images.unsplash.com/photo-1529998274859-64a3872a3706?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1170&q=80',
 'https://images.unsplash.com/40/whtXWmDGTTuddi1ncK5v_IMG_0097.jpg?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1171&q=80'];
 
            let k = Math.floor(Math.random() * 4); //select one of 4 banners in example set
            
            new_ele = {name:'Banner', type:'group', 
                    css:{display:'flex', 'justify-content':'center', 'align-items': 'center', 'min-height':'300px',
                    'background-image': 'url('+imgs[k]+')', 'bg-image': imgs[k], 
                    'background-size':'auto', 'background-repeat': 'no-repeat',
                    'background-position': 'center'},
                children:[
                    new_ele
                ]
            };
            
            
        }
        else if(widget_type=='text_2'){
            
            new_ele = {name:'2 columns', type:'group', css:{display:'flex', 'justify-content':'center'},
                children:[]
            };
            
            let child = {name:'Column 1', type:'text', css:{flex:'1 1 auto'}, content:"<p>Lorem ipsum dolor sit amet ...</p>"};
            new_ele.children.push(child);
            
            child = window.hWin.HEURIST4.util.cloneJSON(child);
            child.name = 'Column 2';
            new_ele.children.push(child);
            
        }
        else if(widget_type=='text_3'){
            
            new_ele = {name:'3 columns', type:'group', css:{display:'flex', 'justify-content':'center'},
                children:[]
            };
            
            let child = {name:'Column 1', type:'text', css:{flex:'1 1 auto'}, content:"<p>Lorem ipsum dolor sit amet ...</p>"};
            new_ele.children.push(child);
            child = window.hWin.HEURIST4.util.cloneJSON(child);
            child.name = 'Column 2';
            new_ele.children.push(child);
            child = window.hWin.HEURIST4.util.cloneJSON(child);
            child.name = 'Column 3';
            new_ele.children.push(child);

        }
        else if(widget_type.indexOf('new_tpl_')==0){

            if(!this._editCMS_SiteMenu)
            this._editCMS_SiteMenu = editCMS_SiteMenu( this._panel_treeWebSite, this );

            widget_type = widget_type.substring(8); // remove 'new_tpl_'

            // Get parent page id
            let parent_page_id = this._editCMS_SiteMenu.getParentPage(window.hWin.current_page_id);
            parent_page_id = (parent_page_id == null || parent_page_id <= 0) ? window.hWin.current_page_id : parent_page_id;

            this._editCMS_SiteMenu.createMenuRecord(parent_page_id, widget_name, widget_type);
            return;
        }
        else if(widget_type.indexOf('tpl_')==0){
            
            _prepareTemplate(ele_id, widget_type);
            return;
        }
        
        _layoutInsertElement_continue(ele_id, new_ele);
    }       
    
    //
    //
    //    
    #layoutInsertElement_continue(ele_id, new_element_json){

        let tree = $.ui.fancytree.getTree( _panel_treePage );
        let parentnode = tree.getNodeByKey(ele_id);
        let parent_container, parent_children, parent_element;

        tinymce.remove('.tinymce-body'); //detach
        this._layout_container.find('.lid-actionmenu').remove();

        if(parentnode.folder){
            //add child

            parent_element = window.hWin.layoutMgr.layoutContentFindElement(_layout_content, parentnode.key);
            parent_container = this._layout_container.find('div[data-hid='+parentnode.key+']');
            parent_children = parent_element.children;

        }else{
            //add sibling
            if(parentnode.parent.isRootNode()){
                parent_element = null;
                parent_container = this._layout_container;
                parent_children = _layout_content;
            }else{
                parent_element = window.hWin.layoutMgr.layoutContentFindElement(_layout_content, parentnode.parent.key);
                parent_container = this._layout_container.find('div[data-hid='+parentnode.parent.key+']');
                parent_children = parent_element.children;
            }
        }

        if(Array.isArray(new_element_json) && new_element_json.length==1){
            new_element_json = new_element_json[0];
        }

        parent_children.push(new_element_json);
        window.hWin.layoutMgr.layoutInitKey(parent_children, parent_children.length-1);

        //recreate
        if(parent_element && parent_element.type=='accordion'){
            window.hWin.layoutMgr.layoutInitAccordion(parent_element, parent_container)
        }else if(parent_element && parent_element.type=='tabs'){
            window.hWin.layoutMgr.layoutInitTabs(parent_element, parent_container)
            //window.hWin.layoutMgr.layoutInit(_layout_content, this._layout_container);    
        }else{
            window.hWin.layoutMgr.layoutInit(parent_children, parent_container, {rec_ID:home_page_record_id, lang:current_language});
        }   


        //update tree
        if(parentnode.folder){
            parentnode.addChildren(new_element_json);    
           
        }else{
            let beforenode = parentnode.getNextSibling();
            parentnode = parentnode.getParent();
            parentnode.addChildren(new_element_json, beforenode);    
           
        }

        setTimeout(function(){
            parentnode.visit(function(node){
                node.setExpanded(true);
            });
            _updateActionIcons(200);
            },300);

        page_was_modified = true;
        if(_edit_Element==null) _toolbar_Page.show();
        /*        
        [{name:'Layout', type:'group', //or cardinal
        children:[
        {name:'Text', type:'text', css:{}, content:'<p>Text element</p>'}
        ] 
        }];        
        */        
    }
    
    //
    // Reflects changes in tree
    //
    #layoutChangeParent(ele_id){
        
        return;

        tinymce.remove('.tinymce-body'); //detach
        this._layout_container.find('.lid-actionmenu').remove();
        
        let affected_element = window.hWin.layoutMgr.layoutContentFindElement(_layout_content, ele_id);
        

        let oldparent = window.hWin.layoutMgr.layoutContentFindParent(_layout_content, ele_id);
        let parent_children;
        
        //remove from old parent -----------
        if(oldparent=='root'){
            parent_children = _layout_content;
        }else{
            parent_children = oldparent.children;
        }
        let idx = -1;
        for(let i=0; i<parent_children.length; i++){
          if(parent_children[i].key==ele_id){
              idx = i;
              break;
          }   
        }        
        parent_children.splice(idx, 1); //remove from children
        
        //add to new parent  ---------------
        let tree = $.ui.fancytree.getTree( _panel_treePage );
        let node = tree.getNodeByKey(''+ele_id);
        let prevnode = node.getPrevSibling();
        let parentnode = node.getParent();
        let parent_element = window.hWin.layoutMgr.layoutContentFindElement(_layout_content, parentnode.key);
        parent_children = parent_element ? parent_element.children : _layout_content;
        
        if(prevnode==null){
            idx = 0;
        }else{
            for(let i=0; i<parent_children.length; i++){
              if(parent_children[i].key==prevnode.key){
                  idx = i+1;
                  break;
              }   
            }        
        }
        if(idx==parent_children.length){
            parent_children.push(affected_element);
        }else{
            parent_children.splice(idx, 0, affected_element);    
        }
        
        //redraw page
        window.hWin.layoutMgr.layoutInit(_layout_content, this._layout_container, {rec_ID:home_page_record_id, lang:current_language});
        _updateActionIcons(200); //it inits tinyMCE also
        
        page_was_modified = true;
        _onPageChange();
    }
    
  
  //
  //
  //
  #onPageChange(){
            
        if(this.page_was_modified){

            if(this._edit_Element==null){
                //show toolbar with Save/Discard
                this._toolbar_Page.show();
            }else{
                //activate save buttons
                this._edit_Element.onContentChange();
            }
            
        }else{
             this._toolbar_Page.hide();
        }
  }     

  //
  // converts layout JSON content to treeview data
  //
  #initTreePage( treeData ){
      
        var that = this;
        
        if(this._panel_treePage){
            
            $.ui.fancytree.getTree( this._panel_treePage ).reload(treeData);
            
        }else{
        
        //init treeview
        let fancytree_options =
        {
            checkbox: false,
            //titlesTabbable: false,     // Add all node titles to TAB chain
            focusOnSelect:true,
            source: treeData,
            quicksearch: true,
            
            click: function(event, data){

                if(data.targetType=='title'){
                    if(data.node.isActive()){
                        window.hWin.HEURIST4.util.stopEvent(event);
                        ///  that._saveEditAndClose(null, 'close'); //close editor on second click
                    }
                    if(data.node.key>0){
                        that._ws_body.layout().open(options.editor_pos);
                        that.#layoutEditElement(data.node.key);
                    }
                
                }
                
            }
            //,activate: function(event, data) { }
        };

        
        fancytree_options['extensions'] = ["dnd"]; //, "filter", "edit"
        fancytree_options['dnd'] = {
                autoExpandMS: 400,
                preventRecursiveMoves: true, // Prevent dropping nodes on own descendants
                preventVoidMoves: true, // Prevent moving nodes 'before self', etc.
                dragStart: function(node, data) {

                    let is_last_root = node.getParent().isRootNode() && node.getParent().countChildren(false) == 1;
                    let is_cardinal = (node.type=='north' || node.type=='south' || 
                               node.type=='east' || node.type=='west' || node.type=='center');
                    
                    return !(is_last_root || is_cardinal);
                },
                dragEnter: function(node, data) {
                    if(node.type=='cardinal'){
                        return false;
                    }else{
                        return (node.folder) ?true :["before", "after"];
                    }
                },
                dragDrop: function(node, data) {
                    // data.otherNode - dragging node
                    // node - target
                   
                    let is_cardinal = (node.type=='north' || node.type=='south' || 
                               node.type=='east' || node.type=='west' || node.type=='center');
                    let hitMode = (is_cardinal)?'child' :data.hitMode;                    
                    
                    data.otherNode.moveTo(node, hitMode);    
                    //change layout content and redraw page
                    that.#layoutChangeParent(data.otherNode.key);
                }
            };

            //create tree
            this._panel_treePage = this._editor_panel.find('.treePage').addClass('tree-rts')
                                .fancytree(fancytree_options); //was recordList
                                
            $('<div class="toolbarPage" style="padding:10px;font-size:0.9em;text-align:center;">'
                                    +'<button title="Discard all changed and restore old version of page" class="btn-page-restore">Discard</button>'
                                    + '<button title="Save changes for current page" class="btn-page-save ui-button-action">Save</button>'
                                    + '<button title="Exit/Close content editor" class="bnt-cms-exit">Close</button>'
                                +'</div>').appendTo(this._panel_treePage);
            
            this._toolbar_Page = this._editor_panel.find('.toolbarPage').hide();
                                
                    this._panel_treePage.find('.btn-page-save').button().css({'border-radius':'4px','margin-right':'5px'})
                            .on('click', this.#saveLayoutCfg);
            
/*TBD                            
                    this._panel_treePage.find('.btn-page-restore').button().css({'border-radius':'4px','margin-right':'5px'}).on('click',
                        function(){
                            that.#startCMS({record_id:options.record_id, container:'#main-content', content:null});
                        }
                    );
                    this._panel_treePage.find('.bnt-cms-exit').button().css({'border-radius':'4px'}).on('click', this.#closeCMS); //{icon:'ui-icon-close'}
*/                    

        }
        
        this.switchMode(this.current_edit_mode);
        
    }
    
    //
    //  Save page configuration (_layout_content) into RT_CMS_MENU record 
    //
    #saveLayoutCfg( callback ){
        
        return;
        
        if(!(options.record_id>0)) return;
        
        window.hWin.HEURIST4.msg.bringCoverallToFront();
        
        let newval = window.hWin.HEURIST4.util.cloneJSON(_layout_content);
        let contents = [];
        
        //@todo remove keys and titles,  extract "content" into separate set of values
        // each content:lang value will be saved in separate detail
        function __cleanLayout(items){
            
            for(let i=0; i<items.length; i++){
                items[i].key = null;
                delete items[i].key;
                items[i].title = null;
                delete items[i].title;
                
                if(items[i].children){
                    __cleanLayout(items[i].children);    
                }
            }
        }
        __cleanLayout(newval);

        let newname = newval[0].name;
        
        // if page consist one group and one text without css - save only content of this text
        // it allows edit content in standard record edit
        /*if(newval[0].children && newval[0].children.length==1 && newval[0].children[0].type=='text'){
            newval = newval[0].children[0].content;
        }else{
            newval = JSON.stringify(newval);    
        }*/
        
        newval = JSON.stringify(newval);
        
        let request = {a: 'addreplace',
                        recIDs: options.record_id,
                        dtyID: DT_EXTENDED_DESCRIPTION,
                        rVal: newval,
                        needSplit: true};
        
        
        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                if(response.status == window.hWin.ResponseStatus.OK){
                    if(response.data.errors==1){
                        let errs = response.data.errors_list;
                        let errMsg = errs[Object.keys(errs)[0]];
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: errMsg,
                            error_title: 'Failed to save configuration'
                        });
                    }else
                    if(response.data.noaccess==1){
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: 'It appears you do not have enough rights (logout/in to refresh) to edit this record',
                            status: window.hWin.ResponseStatus.REQUEST_DENIED
                        });
                    }else{
                        _toolbar_Page.hide();
                        page_was_modified = false;
                        page_cache[options.record_id][DT_EXTENDED_DESCRIPTION] = newval; //update in cache
                        
                        

                        /* 2022-01-04 IJ does not want direct name of web page title
                        if(_editCMS_SiteMenu && newname!=page_cache[options.record_id][DT_NAME]) {
                            body.find('.treePageHeader > h2').text( newname ); 
                            _editCMS_SiteMenu.renameMenuEntry(options.record_id, newname);
                        }
                        */
                        
                        if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call(this);
                    }
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });         
    }
    

  //
  //
  //
  #hideMenuInTree(){
        let ele = this._panel_treePage.find('.lid-actionmenu');
        ele.hide(); //menu icon
        ele.find('span[data-action]').hide(); //popup menu
  }        
    
  //
  //
  //
  #hidePropertyView (){
        
        this._edit_Element = null;
        //TBD this.#initTinyMCE();
    
        this._layout_container.find('div[data-hid]').removeClass('cms-element-editing headline marching-ants marching');                        
        
        this._panel_treePage.find('span.fancytree-title').css({'font-style':'normal', 'text-decoration':'none'});
        this._panel_treePage.find('.fancytree-node').removeClass('fancytree-active');
        
        this.#hideMenuInTree();

        this._panel_propertyView.hide();

        //restore tree
        if(this._keep_EditPanelWidth>0){
            this._ws_body.layout().sizePane('west', this._keep_EditPanelWidth);    
        }
        this._keep_EditPanelWidth = 0;

        this._editor_panel.find('.page_tree').show();
        
        this.#onPageChange();
        
        this._panel_treePage[0].style.removeProperty('height');
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
                                
            this.#hidePropertyView();
            
            this._toolbar_WebSite.hide();
        
           
           
            if(init_tinymce!==false){
                $.ui.fancytree.getTree( this._panel_treePage ).visit(function(node){
                    node.setSelected(false); //reset
                    node.setExpanded(true);
                });            
                this.#updateActionIcons(500);//it inits tinyMCE also
            } //_initTinyMCE
            
        }else{

            this._tabControl.find('li[aria-controls="treeWebSite"]')
                .removeClass('ui-state-active') //ui-tabs-active 
                .addClass('ui-cms-mainmenu');
                                
            this.#hidePropertyView();
            
            this._toolbar_Page.hide();
            this._toolbar_WebSite.show();

            //remove highlights
            this._layout_container.find('.lid-actionmenu').hide();
            this._layout_container.find('div[data-hid]').removeClass('cms-element-active');                        
            this._layout_container.find('.cms-element-overlay').css('visibility','hidden');            
/*TBD            
            
            if(tinymce) tinymce.remove('.tinymce-body');
*/            
            //load website menu treeview
            if(!this._editCMS_SiteMenu)
            this._editCMS_SiteMenu = editCMS_SiteMenu( this._panel_treeWebSite, this );
           
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