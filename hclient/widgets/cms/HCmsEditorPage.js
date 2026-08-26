/*
* HCmsEditorPage.js - web page structure editor
* 
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2025 Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @version     7.0
*/

/* global editCMS_SelectElement, HCmsEditorMargin,HCmsConfigCardinal,HCmsConfigWidget,HCmsConfigGroup,HCmsConfig */

/*
* HCmsEditorPage.js - web page editor - page treeview+element property editor
*/
class HCmsEditorPage {

    _layout_content;   // JSON layout configuration for current page   
    _layout_container; // main-content with CMS content


    //refs to parent classes
    _container = null; //general container
    _cmsEditor = null;  //HCmsEditor
    _cmsEditorElement = null;  //instance of edit element class HCmsConfig or its descendats
    _cmsEditorMargin = null; //instance of HCmsEditorMargin
    
    //interface elements
    _panel_treePage;     // panel with treeview for current page 
    _panel_propertyView; // panel with selected element properties
    _toolbar_Page;       // buttons to apply/cancel changes

    tinymce;
    layoutMgr; //instance form website frame

    //interface flags and states
    currentElementId = null;
    page_was_modified = false;
    delay_onmove = 0;
    __timeout = 0;
    lockDefaultEdit = false;

    
  constructor(_container, _editor) {
    
    this._container = _container;
    this._cmsEditor = _editor;

  }
  
  //
  // Hides popup menu in tree
  //
  #hideMenuInTree(){
      let ele = this._panel_treePage.find('.lid-actionmenu');
      ele.hide(); //menu icon
      ele.find('span[data-action]').hide(); //popup menu
  }        

  
  //
  // Inits inline rich text editor
  //
  #initTinyMCE( key ){

        let that = this;
        if(!Object.hasOwn(window.hWin.HAPI4.dbSettings, 'TinyMCE_formats')){ // retrieve custom formatting

            window.hWin.HAPI4.SystemMgr.get_tinymce_formats({a: 'get_tinymce_formats'}, function(response){

                if(response.status != window.hWin.ResponseStatus.OK){

                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    window.hWin.HAPI4.dbSettings['TinyMCE_formats'] = {};
                }else if(!window.hWin.HEURIST4.util.isObject(response.data)){
                    window.hWin.HAPI4.dbSettings['TinyMCE_formats'] = {};
                }else{
                    window.hWin.HAPI4.dbSettings['TinyMCE_formats'] = response.data;
                }

                that.#initTinyMCE(key);
            });

            return;
        }

        this.tinymce = this._cmsEditor.getTinymce();
        this.detachTinyMCE(false);
        if(!this.tinymce){
            return;
        }
        
        let selector = '.tinymce-body';
        if(key>0){
            selector = selector + '[data-hid='+key+']';
        }

        let custom_formatting = window.hWin.HAPI4.dbSettings.TinyMCE_formats;

        let style_formats = Object.hasOwn(custom_formatting, 'style_formats') && custom_formatting.style_formats.length > 0 
                                ? [ { title: 'Custom styles', items: custom_formatting.style_formats } ] : [];

        if(Object.hasOwn(custom_formatting, 'block_formats') && custom_formatting.block_formats.length > 0){
            style_formats.push({ title: 'Custom blocks', items: custom_formatting.block_formats });
        }
        let inlineConfig = {
            selector: selector,
            menubar: false,
            inline: true,
            
            branding: false,
            elementpath: false,
            
            relative_urls : true,
            remove_script_host: false,
            //document_base_url : window.hWin.HAPI4.baseURL,
            urlconverter_callback : 'tinymceURLConverter',

            entity_encoding:'raw',
            inline_styles: true,
            content_style: "body {font-family: Helvetica,Arial,sans-serif;} " + custom_formatting.content_style,

            plugins: [
                'advlist autolink lists link image media preview', //anchor charmap print 
                'searchreplace visualblocks code fullscreen',
                'media table  paste help noneditable '   //contextmenu textcolor - in core for v5
            ],      

            toolbar: ['styleselect | fontselect fontsizeselect | bold italic forecolor backcolor customClear customHRtag | customHeuristRecordAddLink customHeuristMedia link | align | bullist numlist outdent indent | table | help' ],  

            content_css: [
                '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i'
            ],
            
            //valid_elements: 'p[style],strong,em,span[style],a[href],ul,ol,li',
            //valid_styles: {'*': 'font-size,font-family,color,text-decoration,text-align'},
            powerpaste_word_import: 'clean',
            powerpaste_html_import: 'clean',

            default_link_target: "_blank", // default to new tab

            formats: custom_formatting.formats,
            style_formats_merge: true,
            style_formats: style_formats,

            image_caption: true,
            
            setup:function(editor) {

                // ----- Event handlers -----
                editor.on('change', function(e) {
                    if(that.tinymce.activeEditor && that.tinymce.activeEditor.targetElm){
                        let key = $(that.tinymce.activeEditor.targetElm).attr('data-hid');
                        //update in _layout_content
                        let l_cfg = that.layoutMgr.layoutContentFindElement(that._layout_content, key);
                        if(l_cfg){
                            let newContent = that.tinymce.activeEditor.getContent();
                            that.page_was_modified = (that.page_was_modified || l_cfg.content!=newContent);

                            let lang = $(that.tinymce.activeEditor.targetElm).attr('data-lang');
                            if(lang==that._cmsEditor.default_language || lang=='def' || window.hWin.HEURIST4.util.isempty(lang)){
                                lang = '';
                            }
                            
                            //update in HCmsConfig
                            if(that._cmsEditorElement){
                                that._cmsEditorElement.updateContent(newContent, lang);
                            }else{
                                l_cfg['content'+lang] = newContent;
                            }
                            
                        }else{
                            that.page_was_modified = false;
                        }
                        that.#onPageChange();
                       
                    }
                });

                editor.on('click', function (e) {
                    //adjust tinymce toolbar
                    let $toolbar = $(that._cmsEditor.findInWebSite('.tox-toolbar-dock-transition'));
                    if($toolbar.length > 0 && $toolbar.width() < 400){
                        $toolbar.css('width', '400px');
                    }

                    let $link_btn = $toolbar.find('.tox-tbtn[title="Insert/edit link"]');
                    if($link_btn.length > 0 && $link_btn.find('.tox-tbtn__select-label').length == 0){
                        let html = '<span class="tox-tbtn__select-label">URL</span>';
                        $link_btn.append(html);
                    }

                    $toolbar.find('.tox-split-button[title="Background color"]').attr('title', 'Highlight text');
                });
                    
                editor.on('focus', function (e) {
                    
                    if(!that._panel_treePage.is(':visible')) return;

                        //on editor activation - hides popup menu, overlay and remove "active" class
                        that.hideOverlayAll();

                        //highlights editing element in tree
                        let key = $(that.tinymce.activeEditor.targetElm).attr('data-hid');
                        let node = $.ui.fancytree.getTree( that._panel_treePage ).getNodeByKey(key);
                        that._panel_treePage.find('.fancytree-active').removeClass('fancytree-active');
                        $(node.li).find('.fancytree-node:first').addClass('fancytree-active');
                    

                    $(editor.bodyElement).css('padding-left', '5px'); // add space between content and body outline
                });
                editor.on('blur', function (e) { 
                    $(editor.bodyElement).css('padding-left', ''); // remove space
                });

                // ----- Custom buttons -----
                // Insert Heurist media
                editor.ui.registry.addButton('customHeuristMedia', {
                    icon: 'image',
                    text: 'Add Media',
                    onAction: function (_) {  //since v5 onAction in v4 onclick
                        that.#addHeuristMedia();
                    }
                });
                
                // Insert Add Heurist record link
                editor.ui.registry.addButton('customHeuristRecordAddLink', {
                    icon: 'comment-add',
                    text: 'Add Rec',
                    onAction: function (_) {  //since v5 onAction in v4 onclick
                        that.#addHeuristRecordAddLink();
                    }
                });
                
                // Insert horizontal rule
                editor.ui.registry.addButton('customHRtag', {
                    text: '&lt;hr&gt;',
                    onAction: function (_) {  //since v5 onAction in v4 onclick
                        that.tinymce.activeEditor.insertContent( '<hr>' );
                    }
                });
                // Clear text formatting - to replace the original icon
                editor.ui.registry.addIcon('clear-formatting', `<img style="padding-left: 5px;" src="${window.hWin.HAPI4.baseURL}hclient/assets/clear_formatting.svg" />`)
                editor.ui.registry.addButton('customClear', {
                    text: '',
                    icon: 'clear-formatting',
                    tooltip: 'Clear formatting',
                    onAction: function (_) {
                        that.tinymce.activeEditor.execCommand('RemoveFormat');
                    }
                });
                
            },
            
            paste_preprocess: function(plugin, args){

                let content = args.content;

                if(content.indexOf('<img') === 0){
                    // Tell user to use the 'Insert media' tool instead
                    args.content = '';

                    let msg = 'Please use the "Add media" tool located within the toolbar to added images';
                    window.hWin.HEURIST4.msg.showMsgFlash(msg, 3000);
                }else if(content.search(/https?|ftps?|mailto/) == 0){
                    // Trigger 'Insert link' dialog
                    
                    let href = args.content;
                    href = href.replaceAll(/&amp;/g, '&');

                    const org_href = href;
                    args.content = '';

                    href += `_${window.hWin.HEURIST4.util.random()}`;

                    that.tinymce.activeEditor.execCommand('mceInsertLink', false, href);

                    let $link = $(that.tinymce.activeEditor.selection.getNode());
                    if(!$link.is('a')){
                        $link = $link.find(`a[href="${href}"]`);
                    }
                    if($link.length == 0){
                        $link = $(that.tinymce.activeEditor.contentDocument).find(`a[href="${href}"]`);
                    }

                    $link.attr({
                        'href': org_href,
                        'data-mce-href': org_href,
                        'target': '_blank'
                    }).text(org_href);
                }
            }

        };
      
        
        this.tinymce.init(inlineConfig);
        //try{}catch(e){
        //    console.log('Can not init tinymce. Selector: "'.selector.'". Found:'.$(selector).length);
        //}

        // Correct image and embedded urls
        this._layout_container.find('img, embed').each(function(i,ele){window.hWin.HEURIST4.util.restoreRelativeURL(ele);});
      
  }
  
  //
  // Add Heurist Record link
  //
  #addHeuristRecordAddLink(){
      
      let that = this;

      window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd',{
          title: 'Select type and other parameters for new record',
          height: 520, width: 540,
          get_params_only: true,
          onClose: function(context){
              if(context && !window.hWin.HEURIST4.util.isempty(context.RecAddLink)){

                  that.tinymce.activeEditor.execCommand('mceLink');

                  setTimeout(()=>{
                          const dlg = $('.tox-dialog__body-content');
                          //dig down to the first input text field (being the URL)
                          const urlTextField = dlg.find('.tox-control-wrap .tox-textfield');                    

                          urlTextField.val(context.RecAddLink+'&guest_data=1');

                      },500);    
              }
          },
          default_palette_class: this._cmsEditor.default_palette_class
          }
      );    


  }

  //
  // Browses for heurist uploaded/registered files/resources and add player link
  //         
  #addHeuristMedia(){

      let that = this;
      
      let popup_options = {
          isdialog: true,
          select_mode: 'select_single',
          edit_addrecordfirst: false, //show editor atonce
          selectOnSave: true,
          select_return_mode:'recordset', //ids or recordset(for files)
          filter_group_selected:null,
          //filter_groups: this.configMode.filter_group,
          onselect:function(event, data){

              if(data){

                  if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                      let recordset = data.selection;
                      let record = recordset.getFirstRecord();

                      //always add media as reference to production version of heurist code (not dev version)
                      //let thumbURL = window.hWin.HAPI4.baseURL_pro+'?db='+window.hWin.HAPI4.database
                      //+"&thumb="+recordset.fld(record,'ulf_ObfuscatedFileID');

                      let playerTag = recordset.fld(record,'ulf_PlayerTag');

                      let $dlg;
                      let msg = 'Enter a caption below (optional):<br><br>'
                      + '<textarea rows="6" cols="65" id="figcap"></textarea>';

                      let btns = {};
                      btns['Insert media'] = () => {
                          let caption = $dlg.find('#figcap').val();

                          if(!window.hWin.HEURIST4.util.isempty(caption)){
                              playerTag = '<figure>'+ playerTag +'<figcaption>'+ caption +'</figcaption></figure>';   
                          }

                          that.tinymce.activeEditor.insertContent( playerTag );
                          $dlg.dialog('close');
                      };

                      $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, 
                          {title: 'Adding caption to media', yes: 'Insert media'}, 
                          { default_palette_class: 'ui-heurist-populate', appendTo: 'body' }
                      );
                  }

              }//data

          }
      };//popup_options        

      window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles', popup_options);
  }

       
  //
  // Closes _cmsEditorElement
  //
  hidePropertyView(){
      
      this._cmsEditorElement = null;
      
      this.#initTinyMCE();

      this._layout_container.find('div[data-hid]').removeClass('cms-element-editing headline marching-ants marching');                        

      this._panel_treePage.find('span.fancytree-title').css({'font-style':'normal', 'text-decoration':'none'});
      this._panel_treePage.find('.fancytree-node').removeClass('fancytree-active');

      this.#hideMenuInTree();

      this._panel_propertyView.hide();
      this._cmsEditor.shrinkEditorPanel(); 

      this.#onPageChange();
      this._panel_treePage[0].style.removeProperty('height');
  }
    
  //
  // If page has been modified shows save/cancel buttons for tree or _cmsEditorElement
  //
  #onPageChange(){

      if(this.page_was_modified){

          if(this._cmsEditorElement==null){
              //show toolbar with Save/Discard
              this._toolbar_Page.show();
          }else{
              //activate save buttons
              this._cmsEditorElement.onContentChange( true );
          }

      }else{
          this._toolbar_Page.hide();
      }
  }     
  
 //
 // loads page structure into a treeview
 //
 #initTreePage( treeData ){
      
        let that = this;
        
        if(!Array.isArray(treeData)){
            treeData = [treeData];
        }
        
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
                    }
                    if(data.node.key>0){  //opens property editor
                        that.#layoutEditElement(data.node.key);
                    }
                
                }
                
            }
            //,activate: function(event, data) { }
        };

        //TO CHECK - it is possible to drop to group/container elements only
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
            this._panel_treePage = this._container.find('.treePage').addClass('tree-rts');
            this._panel_treePage.fancytree(fancytree_options);
                                
            //add 3 buttons save,save+close,cancel                                
            $('<div class="toolbarPage" style="padding:10px;font-size:0.9em;text-align:center;">'
                                    +'<button title="Discard all changed and restore old version of page" class="btn-page-restore">Discard</button>'
                                    + '<button title="Save changes for current page" class="btn-page-save ui-button-action">Save</button>'
                                    //+ '<button title="Exit/Close content editor" class="bnt-cms-exit">Close</button>'
                                +'</div>').appendTo(this._panel_treePage);
            

            //button panel below treeview - discard and save
            //if property editor is visible this panel is hidden                                 
            this._toolbar_Page = this._panel_treePage.find('.toolbarPage');
                                
            this._toolbar_Page.find('.btn-page-save').button().css({'border-radius':'4px','margin-right':'5px'})
                        .on('click',()=>that.#saveLayoutCfg())
            this._toolbar_Page.find('.btn-page-restore').button().css({'border-radius':'4px','margin-right':'5px'})
                        .on('click',()=>{
                                that.page_was_modified = false;
                                that._cmsEditor.loadPageContent()   
                        });  //reload this page
                        
            //this._panel_treePage.find('.bnt-cms-exit').button().css({'border-radius':'4px'}).on('click', this.#closeCMS);


        }
        this._toolbar_Page.hide();
        if(this._panel_propertyView) this._panel_propertyView.hide();
        this._panel_treePage[0].style.removeProperty('height');
        this._cmsEditor.shrinkEditorPanel(); 
    }
    
//
// Loads page structure into tree and init layout
//
initPage(pageContainer, pageRecord){
        
        this.detachTinyMCE(false);

        this._layout_container = pageContainer;
        this.layoutMgr = this._cmsEditor.getHapi().layoutMgr;


        if(this.layoutMgr){
            this.layoutMgr.setEditMode(true);
        }else {
            return;
        }

        if(!pageRecord['pageTreeData']){
            window.hWin.HEURIST4.msg.showMsgFlash('Old format. Edit in Heurist interface', 3000);
            //clear treeview
            this.#initTreePage([]);
        }else{
            this._layout_content = pageRecord['pageTreeData'];
            this.#initTreePage(this._layout_content);
        }
        
        let sTitle;
        if(this._cmsEditor.page_id==this._cmsEditor.website_id){
            sTitle = window.hWin.HR('Home Page')  
        }else{ 
            sTitle = window.hWin.HEURIST4.util.stripTags(window.hWin.HAPI4.getTranslation(pageRecord[window.hWin.DT_NAME], null));
        }
        let ele_title = this._container.find('#pageTitle')
        ele_title.attr('title',sTitle).text(sTitle);
        
        this.page_was_modified = false;
        
        //expands structure tree, updates menu in tree
        this._panel_propertyView = this._container.find('.propertyView');
        
        this._container.find('#responsiveScreen').on({change:function(event){
            
            let screenWidth = $(event.target).val();
            if(screenWidth==100){
                screenWidth = '100%';
            }
            $('#webPageFrame').width(screenWidth);
        }})

}

/*
showMarginProperties(isHeader){
    
        this.detachTinyMCE(false);

        this._layout_container = that._cmsEditor.findInWebSite(isHeader?'header':'footer');
        
        this.layoutMgr = this._cmsEditor.getHapi().layoutMgr;

        if(this.layoutMgr){
            this.layoutMgr.setEditMode(true);
        }else {
            return;
        }
        
        this._container.find('$pageTitle').text(window.hWin.HR(isHeader?'Header':'Footer'));
        
        this.page_was_modified = false;
        
        //expands structure tree, updates menu in tree
        let request = {website:this._cmsEditor.website_id, raw:1, ver:3};
        request[this.isHeader?'header':'footer'] = '';
        
        let that = this;
        window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL, request, null, (response)=>{
            
            if(response?.message){

                that._layout_content = pageRecord['pageTreeData'];
                that.#initTreePage(response?.message;);
                
            }else{
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: `Web Home Page not found (record #${that._cmsEditor.website_id})`,
                    error_title: 'Failed to load home page'
                });
            }
        });
        
        
        this._panel_propertyView = this._container.find('.propertyView');
        
        this._container.find('#responsiveScreen').on({change:function(event){
            
            let screenWidth = $(event.target).val();
            if(screenWidth==100){
                screenWidth = '100%';
            }
            $('#webPageFrame').width(screenWidth);
        }});
}
*/
//
//
//
showMarginProperties(isHeader){

    this.detachTinyMCE(false);
    this._container.find('#pageTitle').text(window.hWin.HR(isHeader?'Header':'Footer'));
    this._panel_propertyView.text('data-top', this._panel_propertyView.css('top'));
    
    this._panel_propertyView.css('top',21);
    this._panel_propertyView.fadeIn(500);
    
    let that = this;
    
    if(!this._cmsEditorMargin){
        this._cmsEditorMargin = new HCmsEditorMargin(
        {
            cmsEditor: this._cmsEditor,
            container: this._panel_propertyView, 
            isHeader: isHeader,
            onClose: function(){
                that.hideMarginProperties();            
            }
        });
    }else{
        this._cmsEditorMargin.show({isHeader: isHeader});
    }
    
}

//
//
//
hideMarginProperties(){
    if(this._panel_propertyView.is(':visible')){
        this._panel_propertyView.css('top', this._panel_propertyView.attr('data-top'));
        this.hidePropertyView();
        //restore title
        let ele_title = this._container.find('#pageTitle');
        ele_title.text(ele_title.attr('title'));
    }
}

//
//
//
initActionIcons(){
    //reset selection and expand all   
    $.ui.fancytree.getTree( this._panel_treePage ).visit(function(node){
        node.setSelected(false); //reset
        node.setExpanded(true);
    });            
    this.#updateActionIcons(300);//it inits tinyMCE also
}
 
//--------------------------------------
  
//
// add and init action icons for page structure treeview
//
#updateActionIcons(delay){ 

        let that = this;

        if(delay>0){
            setTimeout( ()=>that.#updateActionIcons(), delay );    
            return;
        }
        
        this.#initTinyMCE();
        
        $.each( this._panel_treePage.find('.fancytree-node'), function( idx, item ){
            
            let ele_ID = $(item).find('span[data-lid]').attr('data-lid');

            that.#defineActionIcons(item, ele_ID, 'position:absolute;right:8px;margin-top:1px;');
        });

        // find all dragable elements - text and widgets
        this._layout_container.find('div.brick').each(function(i, item){   //
            let ele_ID = $(item).attr('data-hid');
            
            that.#defineActionIcons(item, ele_ID, 'position:absolute;z-index:999;');   //left:2px;top:2px;         
        });
}

    //
    // for treeview on mouse over toolbar
    // item - either fancytree node or div.editable in container
    // ele_ID - element key 
    //
    #defineActionIcons (item, ele_ID, style_pos){ 
        
        let that = this;
        ele_ID = ''+ele_ID;
        
        let is_intreeview = $(item).hasClass('fancytree-node');
        if(is_intreeview){
            $(item).find('.lid-actionmenu').remove();
        }else{
            $(item).parent().find(`.lid-actionmenu[data-lid=${ele_ID}]`).remove();
        }
        
            let node = $.ui.fancytree.getTree( this._panel_treePage ).getNodeByKey(ele_ID);

            if(node==null){
                return;
            }
            if(is_intreeview && !$(item).hasClass('fancytree-hide')){       
                $(item).css('display','block');   
            }

            let is_last_root = node.getParent().isRootNode() && node.getParent().countChildren(false) == 1;
            let is_cardinal = (node.type=='north' || node.type=='south' || 
                node.type=='east' || node.type=='west' || node.type=='center');
                
            let actionspan = '<div class="lid-actionmenu mceNonEditable" '
            +' style="'+style_pos+';width:auto;display:none;z-index:999;color:black;background: rgba(201, 194, 249, 1) !important;'
            +'font-size:'+(is_intreeview?'12px;right:13px':'16px')
            +';font-weight:normal;text-transform:none;cursor:pointer" data-lid="'+ele_ID+'">' 
            //+ ele_ID
            + (is_intreeview?'<span class="ui-icon ui-icon-menu" style="width:20px"></span>'
                            :'<span class="ui-icon ui-icon-gear" style="width:30px;height: 30px;font-size: 26px;margin-top: 0px;" title="Edit style and properties 2"></span>')
            
            // hide drag in menu
            //+ ('<span data-action="drag" style="display:block;padding:4px" title="Drag to reposition">' //
            //        + '<span class="ui-icon ui-icon-arrow-4" style="font-weight:normal"></span>Drag</span>')
                                   
            + '<span data-action="edit" style="display:block;padding:4px" title="Edit style and properties 3">'
            +'<span class="ui-icon ui-icon-pencil"></span>Style</span>';               
            
            //hide element for cardinal and delete for its panes                     
            if(node.type!='cardinal'){
                actionspan += '<span data-action="element" style="display:block;padding:4px" title="Add a new element/widget"><span class="ui-icon ui-icon-plus"></span>Insert</span>';
            }
            if(!is_cardinal){
                actionspan += ('<span data-action="delete" style="display:block;padding:4px" title="Remove element from layout"><span class="ui-icon ui-icon-close" title="'
                    +'Remove element from layout"></span>Delete</span>');
            }else if(is_last_root){ // display delete, but block action
                actionspan += ('<span data-action="none" style="display:block;padding:4px" title="Cannot have an empty tree">'
                    +'<span class="ui-icon ui-icon-delete"></span>Delete</span>');
            }

/* TBD  translation for text element
            if(node.type=='text'){
                let stitle = 'To enable multilanguage support define more than one language for web home parameter "Languages"';
                let codes = '';
                if(website_languages!=''){
                    let langs = website_languages.split(',');
                    if(langs.length>0){
                        stitle = 'Define translation for this text element';
                        for(let i=0;i<langs.length;i++){
                            codes = codes
                            +'<span data-action="translate" data-lang="'+langs[i]
                                    +'" style="display:block;padding:4px;text-align:right">'
                            +langs[i]+'</span>';
                        }
                    }
                }
                actionspan = actionspan + '<span data-action="translate_header" style="display:block;padding:4px" title="'
                        +stitle+'"><span class="ui-icon ui-icon-translate"></span>Translate</span>'
                        +codes;
                        
            }
*/            

            actionspan += '</div>';
            actionspan = $( actionspan );

            if(is_intreeview){   //in treeview
                actionspan.appendTo(item);
                
                actionspan.find('span[data-action]').hide();
                actionspan.find('span.ui-icon-menu').on('click', function(event){
                    let ele = $(event.target);
                    window.hWin.HEURIST4.util.stopEvent(event);
                    ele.hide();
                    ele.parent().find('span[data-action]').show();
                });
                
            }else{ 

                actionspan.insertAfter(item); //in main-content

                actionspan.find('span[data-action]').hide();
                actionspan.find('span.ui-icon-gear').on('click', function(event){ // edit widget

                    let ele = $(event.target);
                    window.hWin.HEURIST4.util.stopEvent(event);
                    ele.hide();
                    
                    let is_widget = ele.parent().prev().hasClass('heurist-widget');
                    
                    if(is_widget){
                        ele.parent().find('span[data-action="edit"]').trigger('click');
                    }else{
                        if(ele.parent().hasClass('lid-actionmenu')){
                            ele.parent().show();    
                        }
                        ele.parent().find('span[data-action]').show();                        
                    }
                });

                //actionspan.appendTo(body);    
                //actionspan.position({ my: "left top", at: "left top", of: $(item) })
            }

            //
            // menu for action span
            //
            actionspan.find('span[data-action]').on('click', function(event){
                let ele = $(event.target);

                window.hWin.HEURIST4.util.stopEvent(event);

                that.lockDefaultEdit = true;
                //timeout need to activate current node    
                setTimeout(function(){
                    that.lockDefaultEdit = false;

                    let ele_ID = ele.parents('.lid-actionmenu').attr('data-lid');
                    that._layout_container.find('.lid-actionmenu[data-lid='+ele_ID+']').hide();

                    let action = ele.attr('data-action');
                    if(!action) action = ele.parent().attr('data-action');
                    if(action=='element'){

                        //add new element or widget
                        editCMS_SelectElement(function(selected_element, selected_name){
                            that.#layoutInsertElement(ele_ID, selected_element, selected_name);    
                        })

                    }else if(action=='translate'){
                       
                       //reload the only text element in different language
                       let lang = ele.attr('data-lang');
                        
                       //change or add content of specified language
                       that.#layoutTranslateElement(ele_ID, lang)
                       
/*                        
                        //define new translation - show popup to select language
                        window.hWin.HEURIST4.msg.showPrompt('<p>Select language to translate content: '
+"<select id=\'dlg-prompt-value\' class=\'text ui-corner-all\'"
+" style=\'max-width: 250px; min-width: 10em; width: 250px; margin-left:0.2em\' autocomplete=\'off\'>"
+ window.hWin.HEURIST4.ui.createLanguageSelect() //returns content for language selector
+"</select></p>",
function(value){
    if(value){
        //change or add content of specified language
        _ws_body.layout().open(options.editor_pos);    
    }            
},
'Select language for content',{default_palette_class: this._cmsEditor.default_palette_class});
*/                        
                        
                    }else if(action=='edit'){

                        //add editor panel 
                        that._cmsEditor.openEditorPanel();
                        //add element
                        that.#layoutEditElement(ele_ID);

                    }else if(action=='delete'){
                        //different actions for separator and field
                        let node = $.ui.fancytree.getTree( that._panel_treePage ).getNodeByKey(''+ele_ID);
                        $(node.li).find('.fancytree-node:first').addClass('fancytree-active');
                        window.hWin.HEURIST4.msg.showMsgDlg(
                            'Are you sure you wish to delete element "'+node.title+'"?', 
                        function(){ that.#layoutRemoveElement(ele_ID); }, 
                            {title:'Warning',yes:'Proceed',no:'Cancel'},
                            {default_palette_class: that._cmsEditor.default_palette_class});        

                    }
                    },100); 

                return false;
            });

            //hide gear icon and overlay on mouse exit
            function __onmouseexit(event){
                
                if(that._panel_propertyView.is(':visible')) return;

                let el = document.elementFromPoint(event.pageX, event.pageY);
                if($(el).hasClass('ui-icon-gear')) return;

                let node;
                if($(event.target).hasClass('brick')){ 
                    //cms element
                    
                    node =  $(event.target);

                    that._layout_container.find('.lid-actionmenu[data-lid='+node.attr('data-hid')+']').hide();
                    that._layout_container.find('div[data-lid]').removeClass('cms-element-active');
                    
                    if(!that._panel_propertyView.is(':visible'))
                        that._layout_container.find('.cms-element-overlay').css('visibility','hidden');
                    /*
                    if(that.__timeout==0){
                    __timeout = setTimeout(function(){$('.cms-element-overlay').css('visibility','hidden');},500);  
                    }
                    */ 
                }else{
                    //in tree
                    if($(event.target).is('li')){
                        node = $(event.target).find('.fancytree-node');
                    }else if($(event.target).hasClass('fancytree-node')){
                        node =  $(event.target);
                    }else{
                        //hide icon for parent 
                        node = $(event.target).parents('.fancytree-node:first');
                        if(node) node = $(node[0]);
                    }
                    if(node){
                       
                        
                        let ele = node.find('.lid-actionmenu');
                        ele.find('span[data-action]').hide();
                        ele.find('span.ui-icon-menu').show();
                        ele.hide();
                        
                       
                       $(node).removeClass('fancytree-hover');
                        
                        //remove heighlight
                        that._layout_container.find('div[data-hid]').removeClass('cms-element-active');
                        that._layout_container.find('.lid-actionmenu').hide();
                        
                        if(!that._panel_propertyView.is(':visible'))
                            that._layout_container.find('.cms-element-overlay').css('visibility','hidden');
                    }
                    
                }
            };             

            function __onmouseenter(event){

                    if(!that._panel_treePage.is(':visible') 
                    || that._panel_propertyView.is(':visible')) return;
                    
                    let node, ele_ID;

                    if(that.__timeout>0) clearTimeout(that.__timeout);
                    that.__timeout = 0;

                    if($(event.target).hasClass('.lid-actionmenu') || $(event.target).parents('div.lid-actionmenu').length>0){
                        if(that.delay_onmove>0) clearTimeout(that.delay_onmove);
                        that.delay_onmove = 0;
                        return;
                    }

                    let is_in_page = ($(event.target).hasClass('brick') || $(event.target).parents('div.brick:first').length>0);

                    if( is_in_page ){
                        //div.editable in container 
                        if($(event.target).hasClass('brick')){
                            node = $(event.target);
                        }else{
                            node = $(event.target).parents('div.brick:first');
                        } 

                        //tinymce is active - do not show toolbar
                        if(that._layout_container.find('div.mce-edit-focus').length>0){  //node.hasClass('mce-edit-focus')){
                            return;   
                        }

                        //show action menu button
                        let ele_id = node.attr('data-hid');
                        that._layout_container.find('.lid-actionmenu[data-lid!='+ele_id+']').hide(); //find other
                        let ele = that._layout_container.find('.lid-actionmenu[data-lid='+ele_id+']');

                        let parent = node.parents('div.ui-layout-pane:first');
                        if(parent.length==0 || parent.parents('div[data-hid]').length==0){
                            parent = that._layout_container;  
                        }
                        let pos = node.position();
                        let margin_top = parseInt(node.css('margin-top'));
                        if(!(margin_top>0)) margin_top = 2;
                        let margin_left = parseInt(node.css('margin-left'));
                        if(!(margin_left>0)) margin_left = 2;
                        
                        ele.find('span[data-action]').hide();  
                        ele.find('span.ui-icon-gear').show();  
                        ele.css({
                            top:(pos.top<0?0:pos.top)+ margin_top +'px',
                            left:(pos.left<0?0:pos.left)+margin_left+'px'});
                        ele.show();
                        
                        ele_ID = $(node).attr('data-hid');

                    }else {
                        //node in treeview


                        if($(event.target).hasClass('fancytree-node')){
                            node =  $(event.target);
                        }else{
                            node = $(event.target).parents('.fancytree-node:first');
                        }
                        if(node){
                            $(node).addClass('fancytree-hover');
                            
                            node = $(node).find('.lid-actionmenu');
                            node.css('display','inline-block');
                        }
                        ele_ID = $(node).attr('data-lid');
                    }

                    if(ele_ID>0){
                        
                        if(is_in_page){
                            //highlight in treeview                                        
                            node = $.ui.fancytree.getTree( that._panel_treePage ).getNodeByKey(ele_ID);
                            if(node) node.setActive(true);

                            that._layout_container.find('.cms-element[data-hid]').removeClass('cms-element-active'); //remove from all
                            that._layout_container.find('.cms-element[data-hid='+ele_ID+']').addClass('cms-element-active');

                        }else                            
                        {   
                            //highlight in preview/page
                            //separate overlay div - visible when mouse over tree
                            if(!that._panel_propertyView.is(':visible')){
                                that._panel_treePage.find('.fancytree-active').removeClass('fancytree-active');
                                that.#showOverlayForElement(ele_ID);
                            }
                        }

                    }

            };
                
            $(item).on( "mouseenter", __onmouseenter ).on( "mouseleave", __onmouseexit );
    }

    //
    //
    //
    #showOverlayForElement( ele_ID ){
        if(ele_ID>0){
            let cms_ele = this._layout_container.find('.cms-element[data-hid='+ele_ID+']');
            
            if(cms_ele.hasClass('cms-element-editing')) return;
            
            let pos = cms_ele.offset(); //realtive to document
            let pos2 = this._layout_container.offset();
            let overlay_ele = this._layout_container.find('.cms-element-overlay');
            if(overlay_ele.length==0){
                overlay_ele = $('<div>').addClass('cms-element-overlay').appendTo(this._layout_container); //attr('data-lid',ele_ID).insertAfter
            }
            if(pos && pos2){
                overlay_ele.attr('data-lid',ele_ID)
                .css({top:((pos.top)+'px'), //pos.left-pos2.left
                    left:((pos.left)+'px'),width:cms_ele.width(),height:cms_ele.height()});
                overlay_ele.css('visibility','visible');
            }
        }
    }

    hideOverlayAll(){
        if(this._layout_container){
            this._layout_container.find('.lid-actionmenu').hide();
            //this._layout_container.find('div[data-hid]').removeClass('cms-element-active');  
            this._layout_container.find('.cms-element-overlay').css('visibility','hidden');
            this._layout_container.find('div[data-hid]').removeClass('cms-element-active cms-element-editing headline marching-ants marching');                
        }
    }

    //
    // detach tinymce editor
    //        
    detachTinyMCE(removeActionMenu){
        if(this.tinymce) this.tinymce.remove('.tinymce-body'); //detach
        if(removeActionMenu!=false && this._layout_container) this._layout_container.find('.lid-actionmenu').remove();
    }

    //
    // remove element
    // it prevents deletion of non-empty group
    //
    #layoutRemoveElement(ele_id){

        let tree = $.ui.fancytree.getTree( this._panel_treePage );
        let node = tree.getNodeByKey(''+ele_id);
        let parentnode = node.getParent();
        let parent_container, parent_children, parent_element;
        
        if(parentnode.isRootNode() && parentnode.countChildren(false) == 1){
            //cannot remove root element
            window.hWin.HEURIST4.msg.showMsgFlash('It is not possible to remove the last root element');
            return;    
            
        }else if(parentnode.isRootNode()){
            parent_children = this._layout_content;
            parent_container = this._layout_container;
        }else{

            //remove child
            parent_element = this.layoutMgr.layoutContentFindElement(this._layout_content, parentnode.key);
            parent_children = parent_element?parent_element.children:[];
            parent_container = this._layout_container.find('.cms-element[data-hid='+parentnode.key+']');
            
        }

        this.detachTinyMCE();
        //find index in _layout_content
        let idx = -1;
        for(let i=0; i<parent_children.length; i++){
          if(parent_children[i].key==ele_id){
              idx = i;
              break;
          }   
        }        
        //from json
        if(idx>=0){
            parent_children.splice(idx, 1); //remove from children
        }
        //from tree
        node.remove();
        
        //recreate parent element
        this.layoutMgr.setEditMode(true);
        if(parent_element && parent_element.type=='accordion'){
            this.layoutMgr.layoutInitAccordion(parent_element, parent_container)
        }else if(parent_element && parent_element.type=='tabs'){
            this.layoutMgr.layoutInitTabs(parent_element, parent_container)
        }else{
            this.layoutMgr.layoutInit(parent_children, parent_container, 
                        {rec_ID:this._cmsEditor.website_id, lang:this._cmsEditor.current_language}, true, false); 
        }
        
        this.page_was_modified = true;
        this.#onPageChange();
        
        this.#updateActionIcons(200); //it inits tinyMCE also
        
    }
    
    //
    // Reflects changes in tree
    //
    #layoutChangeParent(ele_id){

        this.detachTinyMCE();
        
        let affected_element = this.layoutMgr.layoutContentFindElement(this._layout_content, ele_id);

        let oldparent = this.layoutMgr.layoutContentFindParent(this._layout_content, ele_id);
        let parent_children;
        
        //remove from old parent -----------
        if(oldparent=='root'){
            parent_children = this._layout_content;
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
        let tree = $.ui.fancytree.getTree( this._panel_treePage );
        let node = tree.getNodeByKey(''+ele_id);
        let prevnode = node.getPrevSibling();
        let parentnode = node.getParent();
        let parent_element = this.layoutMgr.layoutContentFindElement(this._layout_content, parentnode.key);
        parent_children = parent_element ? parent_element.children : this._layout_content;
        
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
        this.layoutMgr.layoutInit(this._layout_content, this._layout_container, 
                {rec_ID:this._cmsEditor.website_id, lang:this._cmsEditor.current_language}, true, true);
        this.#updateActionIcons(200); //it inits tinyMCE also
        
        this.page_was_modified = true;
        this.#onPageChange();
    }

    //
    // switch to different language version or create new one
    //
    #layoutTranslateElement(ele_id, lang_id){
        
        let affected_ele = this._layout_container.find('.cms-element[data-hid="'+ele_id+'"]');
        let lang = window.hWin.HAPI4.getLangCode3(lang_id, 'def');
        
        //need switch
        if(affected_ele.attr('data-lang')==lang || (this._cmsEditor.current_language==lang && !affected_ele.attr('data-lang'))){
            return;
        }

        let affected_cfg = this.layoutMgr.layoutContentFindElement(this._layout_content, ele_id);

        let content = 'content';
        if(this._cmsEditor.default_language!=lang && lang!='def' && !window.hWin.HEURIST4.util.isempty(lang)){
            content = content + lang;
            if(!affected_cfg[content]){ //if not found -  add new content
                affected_cfg[content] = 'Translate content to '+lang+'!' + affected_cfg['content'];
            }
        }
                       
        affected_ele.html(affected_cfg[content]);
        affected_ele.attr('data-lang', lang);
                       
        //_ws_body.layout().open(options.editor_pos);    
    }

    
    //
    // Opens element/widget property editor  (HCmsConfig)
    // 1. css properties
    // 2  flexbox properties
    // 3. widget properties
    //
    #layoutEditElement(ele_id){
        
        let that = this;

        if(this._cmsEditorElement){ //already opened - save previous
            
            if(this._layout_container.find('div.cms-element-editing').attr('data-hid')==ele_id) return; //same
            
            //save previous element
            if(this.warningOnExit(function(){that.#layoutEditElement(ele_id);})){
                return;  
            } 
            
            this._layout_container.find('div[data-hid]').removeClass('cms-element-editing headline marching-ants marching');                        
        }

        this.currentElementId = ele_id;
      
        //1. show div with properties over treeview
        let h = this._panel_treePage.find('ul.fancytree-container').height() + 10;

        h = h < 175 ? h : 175;
        h = h < 80 ? 80 : h; 
        this._panel_treePage.css('height',h+'px');
        this._panel_propertyView.css('top',(h+45)+'px');
        this._toolbar_Page.hide();

        this._panel_propertyView.fadeIn(500);

        this._cmsEditor.expandEditorPanel(); //expand width of editor panel at least 450px
        
        //scroll tree that selected element will be visible
        let node = $.ui.fancytree.getTree( this._panel_treePage ).getNodeByKey(ele_id);
        this._panel_treePage.animate({scrollTop: $(node.li).offset().top}, 1);
        this._panel_treePage.find('span.fancytree-title').css({'font-style':'normal','text-decoration':'none'});
        $(node.li).find('.fancytree-node').removeClass('fancytree-active');
        $(node.li).find('span.fancytree-title:first').css({'font-style':'italic','text-decoration':'underline'}); //
        $(node.li).find('.fancytree-node:first').addClass('fancytree-active');
        
        const isRoot = node.getParent().isRootNode();
        
        this.#hideMenuInTree();
        
        this._layout_container.find('.cms-element-overlay').css('visibility','hidden'); //hide overlay above editing element
        this._layout_container.find('div[data-hid]').removeClass('cms-element-active');                        
        
        let ele = this._layout_container.find('.cms-element[data-hid="'+ele_id+'"]').addClass('cms-element-editing');

        if(!ele.css('background-image') || ele.css('background-image')=='none'){
            ele.addClass('headline marching-ants marching');
        }
        
        let element_cfg = this.layoutMgr.layoutContentFindElement(this._layout_content, ele_id);  //json
        
        if(!element_cfg){
            //element not found
            return;
        }
        
        let is_cardinal = (element_cfg.type=='north' || element_cfg.type=='south' || 
                element_cfg.type=='east' || element_cfg.type=='west' || element_cfg.type=='center');
            
        if(is_cardinal){
             //find parent
             const node = $.ui.fancytree.getTree( this._panel_treePage ).getNodeByKey(''+ele_id);
             const parentnode = node.getParent();
             ele_id = parentnode.key;
             element_cfg = this.layoutMgr.layoutContentFindElement(this._layout_content, ele_id);
        }
        
        //show overlay for editing element
        this.#showOverlayForElement( ele_id );
        
        this.#initTinyMCE( ele_id );

        //
        // mode - 0       take values from this._cmsEditorElement without saving in db
        //        'save'  save entire page in db
        //
        if(isRoot){ element_cfg.isPage = true; }
        
                function __onSaveElementConfig(new_cfg, mode){

                    //save
                    if(new_cfg){
                        
                        that.layoutMgr.layoutContentSaveElement(that._layout_content, new_cfg); //replace element to new one

                        //update treeview                    
                        let node = $.ui.fancytree.getTree( that._panel_treePage ).getNodeByKey(''+new_cfg.key);
                        if(node){
                            node.setTitle(new_cfg.title);
                            that.#defineActionIcons($(node.li).find('span.fancytree-node:first'), new_cfg.key, 
                                    'position:absolute;right:8px;padding:2px;margin-top:0px;');
                        }       
                        if(new_cfg.type=='cardinal'){ //????
                            //recreate cardinal layout
                            that.layoutMgr.setEditMode(true);
                            that.layoutMgr.layoutInitCardinal(new_cfg, that._layout_container);
                        }
                        
                        //save page
                        if(mode!='cancel'){
                            that.#saveLayoutCfg(); 
                        }
                    }
                    
                    if(mode!='save'){
                        //close element config
                        that.hidePropertyView();

                        // find all dragable elements - text and widgets
                        that._layout_container.find('div.brick').each(function(i, item){   //
                            let ele_ID = $(item).attr('data-hid');
                            
                            that.#defineActionIcons(item, ele_ID, 'position:absolute;z-index:999;');   //left:2px;top:2px;         
                        });
                        
                    }
                }
                
                let props = {
                            cmsEditor: this._cmsEditor,
                            container: this._panel_propertyView,
                            onClose: __onSaveElementConfig,
                            element_cfg: element_cfg,
                            alreadyModified: this.page_was_modified
                    };
                
                if(element_cfg.type=='cardinal'){
                    
                    this._cmsEditorElement = new HCmsConfigCardinal(props);
                    
                }else if(element_cfg.appid){
                    
                    this._cmsEditorElement = new HCmsConfigWidget(props);

                }else if(element_cfg.folder){

                    this._cmsEditorElement = new HCmsConfigGroup(props);

                }else{
                    //default
                    this._cmsEditorElement = new HCmsConfig(props);
                }
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
          
        }else if(widget_type.indexOf('heurist_')===0 || widget_type.indexOf('HRecord')===0  || widget_type.indexOf('HMenu')===0){
            
            
            let _options = {};
            if(widget_type=='heurist_Map2'){
                _options = {runtimeMode:"website"};
            }
            
            //btn_visible_newrecord, btn_entity_filter, search_button_label, search_input_label
            new_ele = {appid:widget_type, name:widget_name, css:{}, options:_options};
            
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
        else if(widget_type=='text_2'){  //text 2 columns
            
            new_ele = {name:'2 columns', type:'group', css:{display:'flex', 'justify-content':'center'},
                children:[]
            };
            
            let child = {name:'Column 1', type:'text', css:{flex:'1 1 auto'}, content:"<p>Lorem ipsum dolor sit amet ...</p>"};
            new_ele.children.push(child);
            
            child = window.hWin.HEURIST4.util.cloneJSON(child);
            child.name = 'Column 2';
            new_ele.children.push(child);
            
        }
        else if(widget_type=='text_3'){ //text 3 columns
            
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
            
            const pageTemplate = widget_type.substring(8); // remove 'new_tpl_'
            
            this._cmsEditor.addNewPage(pageTemplate)

            return;
        }
        else if(widget_type.indexOf('tpl_')==0){
            
            this.#prepareTemplate(ele_id, widget_type);
            return;
        }
        
        this.#layoutInsertElement_continue(ele_id, new_ele);
    }       
    
    /**
    * Addition of template
    * 1. getTemplateContent - loads RAW template
    * 2. Converts html to json (if required)
    * 3. 
    * 
    */
    
    //
    // Add new elements (defined in new_element_json)
    //    
    #layoutInsertElement_continue(ele_id, newElementContent){
        
        let tree = $.ui.fancytree.getTree( this._panel_treePage );
        let parentnode = tree.getNodeByKey(ele_id);
        let parent_container, parent_children, parent_element;

        this.detachTinyMCE();

        //detect paremt element    
        if(parentnode.folder){
            //add child
            parent_element = this.layoutMgr.layoutContentFindElement(this._layout_content, parentnode.key);
            parent_container = this._layout_container.find('.cms-element[data-hid='+parentnode.key+']');
            parent_children = parent_element.children;

        }else{
            //add sibling
            if(parentnode.parent.isRootNode()){
                parent_element = null;
                parent_container = this._layout_container;
                parent_children = this._layout_content;
            }else{
                parent_element = this.layoutMgr.layoutContentFindElement(this._layout_content, parentnode.parent.key);
                parent_container = this._layout_container.find('.cms-element[data-hid='+parentnode.parent.key+']');
                parent_children = parent_element.children;
            }
        }
        
        let new_element_json = window.hWin.HEURIST4.util.isJSON(newElementContent);
        if (new_element_json === false){
            
            let new_element = $(newElementContent);
            parent_container.append(new_element);
            new_element_json = this.layoutMgr.convertHTMLtoJSON(new_element, 0);
            
        }
        
        if(Array.isArray(new_element_json) && new_element_json.length==1){
            new_element_json = new_element_json[0];
        }

        //add to configuration
        parent_children.push(new_element_json);
        
        //assign unique keys
        this.layoutMgr.layoutInitKey(parent_children, parent_children.length-1);
        
        //recreate elements
        this.layoutMgr.setEditMode(true);
        if(parent_element && parent_element.type=='accordion'){
            this.layoutMgr.layoutInitAccordion(parent_element, parent_container)
        }else if(parent_element && parent_element.type=='tabs'){
            this.layoutMgr.layoutInitTabs(parent_element, parent_container)
        }else{
           this.layoutMgr.layoutInit(parent_children, parent_container, 
                    {rec_ID:this._cmsEditor.website_id, lang:this._cmsEditor.current_language}, true, false);
        }   


        //update tree
        if(parentnode.folder){
            parentnode.addChildren(new_element_json);    
           
        }else{
            let beforenode = parentnode.getNextSibling();
            parentnode = parentnode.getParent();
            parentnode.addChildren(new_element_json, beforenode);    
           
        }

        let that = this;
        setTimeout(function(){
            parentnode.visit(function(node){
                node.setExpanded(true);
            });
            that.#updateActionIcons(200);
            },300);

        this.page_was_modified = true;
        if(this._cmsEditorElement==null) this._toolbar_Page.show();
    }

    //
    // Insert template as new element at the end of current page
    //
    #prepareTemplate(ele_id, templateName){
        
        if(templateName.indexOf('tpl_')==0){
            templateName = templateName.substring(4);
        }

        // new bootstrap templates        
        if(templateName=='landing' || templateName=='about'){
        
            let request = {website:this._cmsEditor.website_id, raw:1, ver:3, webtemplate:templateName};
            let that = this;
            
            window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL, request, null, (response)=>{
            
                if(response?.message){
                    that.#layoutInsertElement_continue( ele_id, response?.message );
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: `Template ${templateName} not found`,
                        error_title: 'Failed to load template'
                    });
                }
            });
            
            return;
        }
        
        // 1. load template files
        let sURL = window.hWin.HAPI4.baseURL+'hclient/widgets/cms/templates/snippets/'+templateName+'.json';
        
        let that = this;

        // 2. Loads template json
        $.getJSON(sURL, 
        function( new_element_json ){
            
            if(templateName=='default'){
                new_element_json = new_element_json.children[0];
            }else if(templateName=='blog'){
                this.layoutMgr.prepareTemplate(new_element_json, function(updated_json){
                    that.#layoutInsertElement_continue( ele_id, updated_json );
                });
                return;
            }
            
            that.#layoutInsertElement_continue( ele_id, new_element_json );
        }); //on template json load
        
    }
    
    
 //
 //  Saves page configuration (this._layout_content) into RT_CMS_MENU record 
 //
 #saveLayoutCfg( callback ){
        
        if(!window.hWin.HEURIST4.util.isPositiveInt(this._cmsEditor.page_id)){
            return;
        }
        
        window.hWin.HEURIST4.msg.bringCoverallToFront();
       
        
        if(this._cmsEditor.webSite.version!=3 && window.hWin.DT_VERSION>0){

            this._cmsEditor.webSite.version = 3;
            let request = {a: 'addreplace',
                            recIDs: this._cmsEditor.page_id,
                            dtyID: window.hWin.DT_VERSION,
                            insert_new_values: 1,
                            rVal: 3};
            
            window.hWin.HAPI4.RecordMgr.batch_details(request, response=>{this.#saveLayoutCfg( callback )});
            return;
        }
               
        
        let newval = window.hWin.HEURIST4.util.cloneJSON(this._layout_content);
        
        // it removes keys and titles,  extracts "content" into separates set of values
        // each content:lang value will be saved in separate detail
        function __cleanLayout(items){
            
            if(Array.isArray(items))
            for(let i=0; i<items.length; i++){
                if(Object.hasOwn(items[i],'key')) delete items[i].key;
                if(Object.hasOwn(items[i],'title')) delete items[i].title;
                if(Object.hasOwn(items[i],'folder')) delete items[i].folder;
                if(window.hWin.HEURIST4.util.isempty(items[i].css)){
                    delete items[i].css;
                }
                if(window.hWin.HEURIST4.util.isempty(items[i].bsClasses)){
                    delete items[i].bsClasses;
                }
                
                if(items[i].children){
                    __cleanLayout(items[i].children);    
                }
            }
        }
        
        if(!Array.isArray(newval)){
            newval = [newval];
        }
        __cleanLayout(newval);

        //TEST this.layoutMgr.convertJSONtoHTML(newval);
        
        // if page consists one group and one text without css - save only content of this text
        // it allows edit content in standard record edit
        /*
        let newname = newval[0].name;
        if(newval[0].children && newval[0].children.length==1 && newval[0].children[0].type=='text'){
            newval = newval[0].children[0].content;
        }else{
            newval = JSON.stringify(newval);    
        }*/

        newval = JSON.stringify(newval);
        
        let request = {a: 'addreplace',
                        recIDs: this._cmsEditor.page_id,
                        dtyID: window.hWin.DT_EXTENDED_DESCRIPTION,
                        rVal: newval,
                        needSplit: true};
        
        let that = this;
        
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
                        that.page_was_modified = false;
                        that.#onPageChange();
                        //not used page_cache[that._cmsEditor.page_id][window.hWin.DT_EXTENDED_DESCRIPTION] = newval; //update in cache
                        
                        if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call(this);
                    }
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });         
 }
  
 //
 // callback - function to continue, otherwaise it returns true
 //  
 warningOnExit( callback ){
      
      let that = this;

        //at first check if element editor is active
        if(this._cmsEditorElement && this._cmsEditorElement.warningOnExit(function(action){
            if(action=='save'){
                that.page_was_modified = true;
                that.#saveLayoutCfg(callback);
            }else if(action=='discard'){
                //discard changes
                that.page_was_modified = false;
                if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call(that);
            }else{
                //cancel
                if(that.currentElementId){
                    //highlight in treeview                                        
                    let node = $.ui.fancytree.getTree( that._panel_treePage ).getNodeByKey(that.currentElementId);
                    if(node) node.setActive(true);
                    that._layout_container.find('.cms-element[data-hid]').removeClass('cms-element-active'); //remove from all
                    that._layout_container.find('.cms-element[data-hid='+that.currentElementId+']').addClass('cms-element-active');
                }
            }
        })) return true;
        
        if(that.page_was_modified){
            //show small dialogue - save/discard/cancel
            
            let $dlg;
            let _buttons = [
                {text:window.hWin.HR('Save'), 
                    click: function(){that.#saveLayoutCfg(callback);$dlg.dialog('close');}
                },
                {text:window.hWin.HR('Discard changes'),  //Leave unchanged
                    click: function(){
                        that._toolbar_Page.hide();
                        that.page_was_modified = false; 
                        $dlg.dialog('close'); 
                        that._cmsEditor.loadPageContent(); //reload page
                        if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call(that);
                    }
                },
                {text:window.hWin.HR('Cancel'), 
                    click: function(){
                        $dlg.dialog('close');
                    }
                }
            ];            
            
            let sMsg = '"'+ this._container.find('#pageTitle').text() +'" '+window.hWin.HR('page has been modified');
            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg, _buttons, {title:window.hWin.HR('Page changed')}, 
                            {appendTo: 'body', default_palette_class:this._cmsEditor.default_palette_class});

            return true;     
        }else{
            return false;     
        }
      
 }

}