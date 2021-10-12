/*
* editCMS.js - loads websiteRecord.php in edit mode
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

var editCMS_instance2 = null;

//
// options: record_id, content, container
//
function editCMS2(){

    var _className = "EditCMS2";

    var _lockDefaultEdit = false;
    
    var _layout_treeview, 
        _layout_content, //JSON to init
        _layout_container;

    var is_edit_widget_open; //??
        
    var RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'],
    RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
    DT_CMS_TOP_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOP_MENU'],
    DT_CMS_MENU  = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'],
    //     DT_CMS_THEME = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_THEME'],
    DT_NAME       = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
    DT_CMS_HEADER = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER'],
    DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'];

    var body = $(this.document).find('body');
    var dim = {h:body.innerHeight(), w:body.innerWidth()};
    dim.h = (window.hWin?window.hWin.innerHeight:window.innerHeight);
    
    
    function _startCMS(_options){
        
        options = _options;
        //
        // add edit layout - top most ent_wrapper becomes 
        //
        if($(this.document).find('.editStructure').length==0){
            
                var new_ele = $('<div class="ui-layout-center"></div>');//.prependTo(body);
                                             
                //body.children().shift();
                body.children().appendTo(new_ele);
                
                new_ele.appendTo(body);
            
                $('<div class="ui-layout-east"><div class="editStructure treeview_with_header" style="background:red">'      
                
                        +'<div style="padding:10px 20px 4px 0px;border-bottom:1px solid lightgray">' //instruction and close button
                            +'<span style="display:inline-block;width:32px;height:32px;font-size:32px;cursor:pointer" class="closeTreePanel ui-icon ui-icon-carat-2-e"/>'
                            
                            +'<span style="font-style:italic;display:inline-block">Drag to reposition<br>'
                            +'Select or <span class="ui-icon ui-icon-gear" style="font-size: small;"/> to modify</span>&nbsp;&nbsp;&nbsp;'
                            //+'<button style="vertical-align:top;margin-top:4px;" class="closeRtsEditor"/>'
                        +'</div>'
                        +'<div class="treeView" style="margin-left:-27px;margin-right:-10px;"/>' //treeview
                
                                             +'</div></div>').appendTo(body);
            
            
            
                    var layout_opts =  {
                        applyDefaultStyles: true,
                        maskContents:       true,  //alows resize over iframe
                        //togglerContent_open:    '&nbsp;',
                        //togglerContent_closed:  '&nbsp;',
                        east:{
                            size: 340, //@todo this.usrPreferences.structure_width,
                            maxWidth:800,
                            minWidth:340,
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
                                var tog = body.find('.ui-layout-toggler-east');
                                tog.removeClass('prominent-cardinal-toggler');
                                tog.find('.heurist-helper2').remove();
                            },
                            onclose_end : function( ){ 
                                var tog = body.find('.ui-layout-toggler-east');
                                tog.addClass('prominent-cardinal-toggler');
                                $('<span class="heurist-helper2" style="font-size:9px;">Move Delete</span>').appendTo(tog);
                            },
                            togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-e"></div>',
                            togglerContent_closed:  '<div class="ui-icon ui-icon-carat-2-w"></div>',
                        },
                        center:{
                            minWidth:400,
                            contentSelector: '.ent_wrapper', //@todo !!!! for particule template ent_wrapper can be missed
                            //pane_name, pane_element, pane_state, pane_options, layout_name
                            onresize_end : function(){
                                //that.handleTabsResize();                            
                            }    
                        }
                    };

                    body.layout(layout_opts); //.addClass('ui-heurist-bg-light')
        }
        
        
        body.layout().show('east', true );
        
        //load content for current page from DT_EXTENDED_DESCRIPTION
    /*    
        _layout_content =  [{name:'Page', type:'group',
            children:[
                {name:'Text', type:'text', css:{}, content:"<p>Hello World!</p>"}, 
                {name:'Text2', type:'text', css:{}, content:"<p>Goodbye Cruel World!</p>"}, 
            ] 
        }];


        _layout_content =  [{name:'Cardinal', type:'cardinal',
            children:[
                {name:'Center', type:'center',
                    children:[ {name:'Map', type:'text', css:{}, content:"<p>Center!</p>"} ]}, 
                {name:'North', type:'north', size:80,
                    children:[  {name:'Header', type:'text', css:{}, content:"<p>Header!</p>"} ]}, 
                {name:'West', type:'west', 
                    children:[ 
                        {name:'Search', type:'text', css:{}, content:"<p>This is place for search widget!</p>"}
                    ]},
                {name:'East', type:'east', 
                    children:[ 
                        {name:'TabControl', type:'tabs',
                               children:[ 
                                    {name:'Tab1', type:'text', css:{}, content:"<p>Content for tab #1</p>"},
                                    {name:'Accordion', type:'accordion', css:{}, 
                                        children:[
                                            {name:'Tab2.Acc1', type:'text', css:{}, content:"<p>Accordion panel 1</p>"}, 
                                            {name:'Tab2.Acc2', type:'text', css:{}, content:"<p>Accordion panel 2</p>"}, 
                                            {name:'Tab2.Acc3', type:'text', css:{}, content:"<p>Accordion panel 3</p>"}, 
                                            {name:'Tab2.Acc4', type:'text', css:{}, content:"<p>Accordion panel 4</p>"}, 
                                        ] 
                                    },
                                    {name:'Tab3', type:'group',
                                        children:[
                                            {name:'Tab3.Text1', type:'text', css:{}, content:"<p>Hello World on Tab3!</p>"}, 
                                            {name:'Tab3.Text2', type:'text', css:{}, content:"<p>Goodbye Cruel World on Tab3!</p>"}, 
                                        ] 
                                    }
                               ]}
                    ]}
            ] 
        }];

        _layout_content =  [{name:'Cardinal', type:'cardinal',
            children:[
                {name:'Center', type:'center',
                    children:[ {appid:'heurist_resultList', name:'Results', css:{}, options:{}} ]}, 
                {name:'North', type:'north', size:80,
                    children:[ {appid:'heurist_Search', name:'Filter', css:{}, options:{}} ]}, 
                {name:'West', type:'west', 
                    children:[ 
                        {name:'Search', type:'text', css:{}, content:"<p>This is place for search widget!</p>"}
                    ]},
                {name:'East', type:'east', 
                    children:[ 
                        {name:'TabControl', type:'tabs',
                               children:[ 
                                    {name:'Tab1', type:'text', css:{}, content:"<p>Content for tab #1</p>"},
        
        
                                    {name:'Accordion', type:'accordion', css:{}, 
                                        children:[
                                            {name:'Tab2.Acc1', type:'text', css:{}, content:"<p>Accordion panel 1</p>"}, 
                                            {name:'Tab2.Acc2', type:'text', css:{}, content:"<p>Accordion panel 2</p>"}, 
                                            {name:'Tab2.Acc3', type:'text', css:{}, content:"<p>Accordion panel 3</p>"}, 
                                            {name:'Tab2.Acc4', type:'text', css:{}, content:"<p>Accordion panel 4</p>"}, 
                                        ] 
                                    },
                                    {name:'Tab3', type:'group',
                                        children:[
                                            {name:'Tab3.Text1', type:'text', css:{}, content:"<p>Hello World on Tab3!</p>"}, 
                                            {name:'Tab3.Text2', type:'text', css:{}, content:"<p>Goodbye Cruel World on Tab3!</p>"}, 
                                        ] 
                                    }
                               ]}
                    ]}
            ] 
        }];
    */
        
        if(!options){
            options.record_id = 0;
            options.container = '#main-content';
            options.content = null;
        }
        
        _layout_container = body.find(options.container);
        
        if(options.content){
           _layout_content = options.content;
           _initPage();
            
        }else if (options.record_id>0 ){
            //load by page_record_id
                var surl = window.hWin.HAPI4.baseURL+'?db='
                    +window.hWin.HAPI4.database+'&field='+DT_EXTENDED_DESCRIPTION+'&recid='+options.record_id;
                $.get( surl,  
                function(res){
                    options.content = res;
                    _layout_content = res;
                    _initPage();
                });
            
        }
    }    
    
    //
    // 1. close control panel
    // 2. reload content
    //
    function _closeCMS(){
        
        // 1. close control panel
        body.layout().hide('east'); // .show('east', false );
        
        //2. reload content
        layoutMgr.setEditMode(false);
        layoutMgr.layoutInit(_layout_content, _layout_container);
    }
    
    //
    //
    //
    function _initPage(){
        
        layoutMgr.setEditMode(true);
        var res = layoutMgr.layoutInit(_layout_content, _layout_container);
        
        if(res===false){
            window.hWin.HEURIST4.msg.showMsgFlash('Old format. Edit in Heurist interface');
            //clear treeview
            _initTreeView([]);
        }else{
            _layout_content = res;
            _initTreeView(_layout_content);
        }
        
    }

    //
    // 1) init editor
    // 2) init hover toolbar - DnD,Edit Properties,Insert Sibling
    //
    function _initTinyMCE(){
        
        tinymce.remove('.tinymce-body'); //detach
        _layout_container.find('.lid-actionmenu').remove();

        var inlineConfig = {
            selector: '.tinymce-body',
            menubar: false,
            inline: true,
            
            branding: false,
            elementpath: false,

            relative_urls : false,
            remove_script_host : false,
            convert_urls : true,            

            entity_encoding:'raw',
            inline_styles: true,
            content_style: "body {font-family: Helvetica,Arial,sans-serif;}",
            
            plugins: [
                'advlist autolink lists link image media preview', //anchor charmap print 
                'searchreplace visualblocks code fullscreen',
                'media table  paste help noneditable '   //contextmenu textcolor - in core for v5
            ],      

            toolbar: ['formatselect | bold italic forecolor backcolor  | customHeuristMedia link | align | bullist numlist outdent indent | table | removeformat | help' ],  

            content_css: [
                '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i'
            ],                    
            
            //valid_elements: 'p[style],strong,em,span[style],a[href],ul,ol,li',
            //valid_styles: {'*': 'font-size,font-family,color,text-decoration,text-align'},
            powerpaste_word_import: 'clean',
            powerpaste_html_import: 'clean',
            
            setup:function(editor) {

                editor.on('focus', function (e) {
                    //='+editor.id.substr(3)+']
                    _layout_container.find('.lid-actionmenu[data-lid]').hide();
                });
                
                editor.on('blur', function (e) {
                        var key = tinymce.activeEditor.id.substr(3);
                        //update in _layout_content
                        var ele = layoutMgr.layoutContentFindElement(_layout_content, key);
                        ele.content = tinymce.activeEditor.getContent();
                });
           
                editor.ui.registry.addButton('customHeuristMedia', {
                      icon: 'image',
                      text: 'Add Media',
                      onAction: function (_) {  //since v5 onAction in v4 onclick
                            __addHeuristMedia();
                      }
                    });
                
            }
        };
        
        tinymce.init(inlineConfig);
        
        // find all dragable elements - text and widgets
        _layout_container.find('div.editable').each(function(i,item){
            var ele_ID = $(item).attr('data-lid');
             //left:2px;top:2px;
            _defineActionIcons(item, ele_ID, 'position:absolute;padding:2px;z-index:2;');   //left:2px;top:2px;         
        });
        
    }    
    
    
    //
    // browse for heurist uploaded/registered files/resources and add player link
    //         
    function __addHeuristMedia(){

        var popup_options = {
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
                        var recordset = data.selection;
                        var record = recordset.getFirstRecord();

                        //always add media as reference to production version of heurist code (not dev version)
                        var thumbURL = window.hWin.HAPI4.baseURL_pro+'?db='+window.hWin.HAPI4.database
                        +"&thumb="+recordset.fld(record,'ulf_ObfuscatedFileID');

                        var playerTag = recordset.fld(record,'ulf_PlayerTag');

                        tinymce.activeEditor.insertContent( playerTag );
                    }

                }//data

            }
        };//popup_options        

        window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles', popup_options);
    }
    
    
    //
    // converts layout JSON content to treeview data
    //
    function _initTreeView( treeData ){
        
        if(_layout_treeview){
            
            _layout_treeview.fancytree('getTree').reload(treeData);
            
        }else{
        
        //init treeview
        var fancytree_options =
        {
            checkbox: false,
            //titlesTabbable: false,     // Add all node titles to TAB chain
            focusOnSelect:true,
            source: treeData,
            quicksearch: true,
            
            click: function(event, data){

                var ele = $(event.target);
                if(!ele.hasClass('ui-icon')){
                    if(data.node.isActive()){
                        window.hWin.HEURIST4.util.stopEvent(event);
                        ///  that._saveEditAndClose(null, 'close'); //close editor on second click
                    }
                }
            },
            activate: function(event, data) { 
                //main entry point to start edit layout element/widget - open formlet or tinymce
                /*if(data.node.key>0){

                    that.selectedRecords([data.node.key]);
                    if(!that._lockDefaultEdit){
                    
                        if(that.options.external_preview){
                            that.options.external_preview.manageRecords('saveQuickWithoutValidation',
                                function(){
                                    that.previewEditor.manageRecords('setDisabledEditForm', true)
                                    that._onActionListener(event, {action:'edit'}); //default action of selection            
                                });
                        }else{
                            that.previewEditor.manageRecords('setDisabledEditForm', true)
                            that._onActionListener(event, {action:'edit'}); //default action of selection            
                        }
                        
                    }
                }*/
            }
        };

        /*
        fancytree_options['extensions'] = ["dnd"]; //, "filter", "edit"
        fancytree_options['dnd'] = {
                autoExpandMS: 400,
                preventRecursiveMoves: true, // Prevent dropping nodes on own descendants
                preventVoidMoves: true, // Prevent moving nodes 'before self', etc.
                dragStart: function(node, data) {
                    return that._dragIsAllowed;
                },
                dragEnter: function(node, data) {
                    return (node.folder) ?true :["before", "after"];
                },
                dragDrop: function(node, data) {
                    
                    data.otherNode.moveTo(node, data.hitMode);    
                    //save treeview in database
                    that._dragIsAllowed = false;
                    
                    if(that.options.external_preview){
                        that.options.external_preview.manageRecords('saveQuickWithoutValidation',
                               function(){ that._saveRtStructureTree() });
                    }else{
                        setTimeout(function(){
                            that._saveRtStructureTree();
                        },500);
                    }
                    
                }
            }; */

            _layout_treeview = body.find('.treeView').addClass('tree-rts')
                                .fancytree(fancytree_options); //was recordList

        }
        
            _layout_treeview.fancytree('getTree').visit(function(node){
                node.setSelected(false); //reset
                node.setExpanded(true);
            });            
            _updateActionIcons(500);//it inits timyMCE also
        
    }

    
    //
    //add and init action icons
    //
    function _updateActionIcons(delay){ 
        
        if(!(delay>0)) delay = 1;

        setTimeout(function(){
            $.each( _layout_treeview.find('.fancytree-node'), function( idx, item ){
                //var ele_ID = ele.parents('li:first').find('span[data-lid]').attr('data-lid');
                var ele_ID = $(item).find('span[data-lid]').attr('data-lid');

                _defineActionIcons(item, ele_ID, 'position:absolute;right:8px;padding:2px;margin-top:0px;');
            });
            
            _initTinyMCE();
        }, delay);
    }

    //
    // for treeview on mouse over toolbar
    //
    function _defineActionIcons(item, ele_ID, style_pos){ 
           if($(item).find('.lid-actionmenu').length==0){
               
               if(!$(item).hasClass('fancytree-hide')){
                    $(item).css('display','block');   
               }

               var node = _layout_treeview.fancytree('getTree').getNodeByKey(ele_ID);
               

               if(node==null){
                   console.log('ONL '+ele_ID);
                   return;
               }
               
               var is_folder = node.folder;  //$(item).hasClass('fancytree-folder'); 
               var is_root = node.getParent().isRootNode();
               
               var actionspan = '<div class="lid-actionmenu mceNonEditable" '
                    +' style="'+style_pos+';display:none;color:black;background:#95A7B7 !important;'
                    +'font-size:9px;font-weight:normal;text-transform:none;cursor:pointer" data-lid="'+ele_ID+'">' + ele_ID
                    + '<span data-action="drag" style="background:lightgreen;padding:4px;font-size:9px;font-weight:normal" title="Drag to reposition">'
                    + (is_root?'':'<span class="ui-icon ui-icon-arrow-4" style="font-size:9px;font-weight:normal"/>Drag</span>'               
                    + '<span data-action="edit" style="background:lightgray;padding:4px;font-size:9px;font-weight:normal" title="Edit properties">')
                    +'<span class="ui-icon ui-icon-pencil" style="font-size:9px;font-weight:normal"/>Edit</span>';               
                   
               //hide element for cardinal and delete for its panes                     
               if(node.data.type!='cardinal'){
                   actionspan += '<span data-action="element" style="background:#ECF1FB;padding:4px"><span class="ui-icon ui-icon-plus" title="Add a new element/widget" style="font-size:9px;font-weight:normal"/>Element</span>';
               }
               if(!(is_root || node.data.type=='north' || node.data.type=='east' || node.data.type=='west' || node.data.type=='east')){
                   actionspan += ('<span data-action="delete" style="background:red;padding:4px"><span class="ui-icon ui-icon-close" title="'
                        +'Remove element from layout" style="font-size:9px;font-weight:normal"/>Delete</span>');
               }
               
               
               actionspan += '</div>';
               actionspan = $( actionspan );
                   
               if($(item).hasClass('fancytree-node')){
                   actionspan.appendTo(item);
               }else{
                   actionspan.insertAfter(item);
                   //actionspan.appendTo(body);    
                   //actionspan.position({ my: "left top", at: "left top", of: $(item) })
               }
                   
               actionspan.find('span').click(function(event){
                    var ele = $(event.target);
                    
                    window.hWin.HEURIST4.util.stopEvent(event);
                    
                    _lockDefaultEdit = true;
                    //timeout need to activate current node    
                    setTimeout(function(){
                        _lockDefaultEdit = false;
                        
                        var ele_ID = ele.parents('.lid-actionmenu').attr('data-lid');
                        
//console.log('selected '+ele_ID);                        
//if(!(ele_ID>0)) return;

                        var action = ele.attr('data-action');
                        if(!action) action = ele.parent().attr('data-action');
                        if(action=='element'){
                           
                           //add new element or widget
                           //that.showBaseFieldEditor(-1, null);
                           _selectElementType(ele_ID);
                            
                        }else if(action=='edit'){
                            
                            //add new group/separator
                            _layoutEditElement(ele_ID);

                        }else if(action=='delete'){
                            //different actions for separator and field
                            _layoutRemoveElement(ele_ID);
                            
                        }
                    },100); 
                    
                    return false;
               });
               
               /*
               $('<span class="ui-icon ui-icon-pencil"></span>')                                                                
               .click(function(event){ 
               //tree.contextmenu("open", $(event.target) ); 
               
               ).appendTo(actionspan);
               */

               //hide icons on mouse exit
               function _onmouseexit(event){
                       var node;
                       if($(event.target).hasClass('editable')){
                          node =  $(event.target);
                          _layout_container.find('.lid-actionmenu[data-lid='+node.attr('data-lid')+']').hide();
                       }else{
                       
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
                               var ele = node.find('.lid-actionmenu'); //$(event.target).children('.lid-actionmenu');
                               ele.hide();//css('visibility','hidden');
                               //remove heighlight
                               _layout_container.find('div[data-lid]').removeClass('ui-state-active');
                           }
                       }
               }               
               
               $(item).hover(
                   function(event){
                       var node;
                       if($(event.target).hasClass('editable')){
                          node =  $(event.target);
                          
                          _layout_container.find('.lid-actionmenu').hide(); //find other
                          var ele = _layout_container.find('.lid-actionmenu[data-lid='+node.attr('data-lid')+']');

                          ele.show();
                          var pos = node.position();
                          ele.css({top:pos.top+'px',left:pos.left});
                          
                       }else {
                       
                           if($(event.target).hasClass('fancytree-node')){
                              node =  $(event.target);
                           }else{
                              node = $(event.target).parents('.fancytree-node:first');
                           }
                           if(node){
                               node = $(node).find('.lid-actionmenu');
                               node.css('display','inline-block');//.css('visibility','visible');
                           }
                       }
                       if(node){
                           //highlight in preview
                           var ele_ID = $(node).attr('data-lid');
                           _layout_container.find('div[data-lid]').removeClass('ui-state-active');
                           if(ele_ID>0)
                           _layout_container.find('div[data-lid="'+ele_ID+'"]').addClass('ui-state-active');
                       }
                       
                   }
               );               
               $(item).mouseleave(
                   _onmouseexit
               );
           }
    }
    
    //
    // remove element
    // it prevents deletion of non-empty group
    //
    function _layoutRemoveElement(ele_id){

        var tree = _layout_treeview.fancytree('getTree');
        var node = tree.getNodeByKey(ele_id);
        var parentnode = node.getParent();
        var parent_container, parent_children, parent_element;
        
        
        if(parentnode.isRootNode()){
            //can not remove root element
            window.hWin.HEURIST4.msg.showMsgFlash('It is not possible remove root element');
            return;    
            
            //parent_children = _layout_content; 
            //parent_container = _layout_container;
        }else{
            
            if(parentnode.folder && parentnode.countChildren()==1){
                window.hWin.HEURIST4.msg.showMsgFlash('It is not possible remove the last element in group. Remove the entire group');
                return;    
            }
            
            //remove child
            parent_element = layoutMgr.layoutContentFindElement(_layout_content, parentnode.key);
            parent_children = parent_element.children;
            parent_container = _layout_container.find('div[data-lid="'+parentnode.key+'"]');
            
        }

        tinymce.remove('.tinymce-body'); //detach
        _layout_container.find('.lid-actionmenu').remove();
        
        //find index in _layout_content
        var idx = -1;
        for(var i=0; i<parent_children.length; i++){
          if(parent_children[i].key==ele_id){
              idx = i;
              break;
          }   
        }        
        //from json
        parent_children.splice(idx, 1); //remove from children

        //from tree
        node.remove();
        
        //recreate
        if(parent_element && parent_element.type=='accordion'){
            layoutMgr.layoutInitAccordion(parent_element, parent_container)
        }else if(parent_element && parent_element.type=='tabs'){
            layoutMgr.layoutInitTabs(parent_element, parent_container)
        }else{
            layoutMgr.layoutInit(parent_children, parent_container); 
        }
        
        _updateActionIcons(200); //it inits timyMCE also
        
    }
    
    //
    // Opens element/widget property editor 
    // 1. css properties
    // 2. widget properties
    //
    function _layoutEditElement(ele_id){
        
    }
    
    //
    // Add text element or widget
    // 1. Find parent element for "ele_id"
    // 2. Add json to _layout_content
    // 3. Add element to _layout_container
    // 4. Update treeview
    //
    function _layoutInsertElement(ele_id, widget_id, widget_name){
        
        var new_ele = {name:'Text', type:'text', css:{}, content:"<p>Click to edit content</p>"};
        
        if(widget_id=='group'){
            new_ele = {name:'Group', type:'group', css:{}, children:[ new_ele ]};
        }else if(widget_id=='tabs'){
            
            new_ele = {name:'TabControl', type:'tabs', css:{}, children:[ 
                {name:'Tab 1', type:'group', css:{}, children:[ new_ele ]},
                {name:'Tab 2', type:'group', css:{}, children:[ new_ele ]}
            ]};
        }else if(widget_id=='accordion'){    
            new_ele = {name:'Accordion', type:'accordion', css:{}, children:[ 
                {name:'Panel 1', type:'group', css:{}, children:[ new_ele ]}
            ]};
        }else if(widget_id=='cardinal'){    
            
            new_ele = {name:'Cardinal', type:'cardinal', children:[
            {name:'Center', type:'center', children:[ new_ele ]},
            {name:'North', type:'north', size:80, children:[ new_ele ]},
            {name:'South', type:'south', size:80, children:[ new_ele ]},
            {name:'West', type:'west', children:[ new_ele ]},
            {name:'East', type:'east', children:[ new_ele ]}
            ]};
          
        }else if(widget_id.indexOf('heurist_')===0){
            
            //btn_visible_newrecord, btn_entity_filter, search_button_label, search_input_label
            new_ele = {appid:widget_id, name:widget_name, css:{}, options:{}};
            
        /*
        }else if(widget_id=='text_media'){
            
            new_ele = {name:'Text and media', type:'text', css:{}, content:"<div></div>"};
            
        }else if(widget_id=='text_2'){
            
            new_ele = {name:'2 columns', type:'text', 
                css:{display:'flex', 'justify-content':'center', 'align-items':'stretch', 'gap':'5px'}, 
                content:'<div style="flex-basis:50%">Column 1</div><div style="flex-basis:50%">Column 2</div>'};
        */
        }
        
        var tree = _layout_treeview.fancytree('getTree');
        var parentnode = tree.getNodeByKey(ele_id);
        var parent_container, parent_children, parent_element;
        
        tinymce.remove('.tinymce-body'); //detach
        _layout_container.find('.lid-actionmenu').remove();
        
        if(parentnode.folder){
            //add child
            /*
            if(parentnode.parent){
                //insert after visible element
                var ele = layoutMgr.layoutContentFindElement(_layout_content, parentnode.parent.key);
                parent_container = body.find('div[data-lid="'+parentnode.parent.key+'"]');
                parent_children = ele.children;
            }else{
                parent_container = '#main-content';
                parent_children = _layout_content;
            }
            */
            parent_element = layoutMgr.layoutContentFindElement(_layout_content, parentnode.key);
            parent_container = _layout_container.find('div[data-lid="'+parentnode.key+'"]');
            parent_children = parent_element.children;
            
        }else{
            //add sibling
            if(parentnode.parent.isRootNode()){
                parent_element = null;
                parent_container = _layout_container;
                parent_children = _layout_content;
            }else{
                parent_element = layoutMgr.layoutContentFindElement(_layout_content, parentnode.parent.key);
                parent_container = _layout_container.find('div[data-lid="'+parentnode.parent.key+'"]');
                parent_children = parent_element.children;
            }
        }
        
        /*
        if(parentnode.folder){
            //container - add to this container
            parent_children.push(new_ele);
            layoutMgr.layoutInitKey(parent_children, parent_children.length-1);
            parentnode.addNode(new_ele);
            
        }else{
            //text element or widget - add next to this element
            var idx = -1;
            for(var i=0; i<parent_children.length; i++){
              if(parent_children[i].key==ele_id){
                  idx = i;
                  break;
              }   
            }
            if(idx>=0){
                if(idx==0){
                    parent_children.push(new_ele);
                    idx = parent_children.length-1;
                }else{
                    parent_children.splice(idx,0,new_ele);    
                }
                layoutMgr.layoutInitKey(parent_children, idx);
                parentnode.addNode(new_ele, 'after');
            }
        }
        */
        

        parent_children.push(new_ele);
        layoutMgr.layoutInitKey(parent_children, parent_children.length-1);
        
        //recreate
        if(parent_element && parent_element.type=='accordion'){
            layoutMgr.layoutInitAccordion(parent_element, parent_container)
        }else if(parent_element && parent_element.type=='tabs'){
            layoutMgr.layoutInitTabs(parent_element, parent_container)
            //layoutMgr.layoutInit(_layout_content, _layout_container);    
        }else{
            layoutMgr.layoutInit(parent_children, parent_container);
        }   

        
        //update tree
        //var chld = parentnode.getChildren();
        //chld.push(new_ele);
        
        
        
        if(parentnode.folder){
            parentnode.addChildren(new_ele);    
            //parentnode.addNode(new_ele);
        }else{
            var beforenode = parentnode.getNextSibling();
            parentnode = parentnode.getParent();
            parentnode.addChildren(new_ele, beforenode);    
            //parentnode.addNode(new_ele, 'after');
        }
        
        parentnode.visit(function(node){
            node.setExpanded(true);
        });
        
        
        _updateActionIcons(200);

/*        
        [{name:'Layout', type:'group', //or cardinal
            children:[
                {name:'Text', type:'text', css:{}, content:'<p>Text element</p>'}
            ] 
        }];        
*/        
    }

    //
    //
    //
    function _selectElementType( insert_ele_id ){

        var $dlg;

        var selected_element = null, selected_name='';

        var t_groups = {
            group:{name:'Group', description:'Container for elements'},
            accordion:{name:'Accordion', description:'Set of collapsable groups'},            
            tabs:{name:'Tabs', description:'Tab/Page control. Each page may have group of elements'},            
            cardinal:{name:'Cardinal', description:'Container for five groups or elements placed orthogonally'},                                                    
        };

        var t_elements = {
            
            heurist_Search:{name:'Filter', description:'Search field (with standard filter builder)'},
            heurist_SearchTree:{name:'Saved filters', description:'Simple &amp; facet filters, selection or tree'},            

            heurist_resultList:{name:'Standard filter result', description:'Switchable modes, action controls'},            
            heurist_resultListExt:{name:'Custom report', description:'Also use for single record view'},            
            heurist_resultListDataTable:{name:'Table format', description:'Result list as data table'},            

            heurist_Map:{name:'Map and timeline', description:'Map and timeline widgets'},            
            heurist_Graph:{name:'Network graph', description:'Visualization for records links and relationships'},            
        };

        var t_contents = {
            text:{name:'Simple Text', description:'Simple text wiht header'},
            text_media:{name:'Text with media', description:'media and text '},
            text_2:{name:'Text in 2 columns', description:'2 columns layout'},
            text_3:{name:'Text in 3 columns', description:'3 columns layout'},
            text_home_banner:{name:'Home banner', description:'Text over background image'},
        }

        var buttons= [
            {text:window.hWin.HR('Cancel'), 
                id:'btnCancel',
                css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                click: function() { 
                    $dlg.dialog( "close" );
            }},
            {text:window.hWin.HR('Insert'), 
                id:'btnDoAction',
                class:'ui-button-action',
                disabled:'disabled',
                css:{'float':'right'}, 
                click: function() { 
                    if(selected_element){
                        _layoutInsertElement(insert_ele_id, selected_element, selected_name);
                        $dlg.dialog( "close" );    
                    }
        }}];

        $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
            +"hclient/widgets/cms/editCMS_SelectElement.html?t="+(new Date().getTime()), 
            buttons, 'Select Template to insert to your Web Page', 
            {  container:'cms-add-widget-popup',
                default_palette_class: 'ui-heurist-publish',
                width: 600,
                height: 600,
                close: function(){
                    $dlg.dialog('destroy');       
                    $dlg.remove();
                },
                open: function(){
                    is_edit_widget_open = true;

                    //load list of groups and elements and init selector
                    var  sel = $dlg.find('#groups');
                    $.each(t_groups, function(key, item){
                        window.hWin.HEURIST4.ui.addoption(sel[0], key, item.name);
                    });
                    sel = $dlg.find('#elements');
                    $.each(t_elements, function(key, item){
                        window.hWin.HEURIST4.ui.addoption(sel[0], key, item.name);
                    });
                    sel = $dlg.find('#contents');
                    $.each(t_contents, function(key, item){
                        window.hWin.HEURIST4.ui.addoption(sel[0], key, item.name);
                    });
                    
                    
                    
                    $dlg.find('select').mouseover(function(e){
                        window.hWin.HEURIST4.util.setDisabled( $dlg.parents('.ui-dialog').find('#btnDoAction'), false );
                        var t_name = $(e.target).val();
                        //selected_element  = t_name;
                        var desc = '';
                        if(t_contents[t_name]){
                            desc = t_contents[t_name];    
                        }else if (t_elements[t_name]) {
                            desc = t_elements[t_name];
                        }else if (t_groups[t_name]){
                            desc = t_groups[t_name];                            
                        }
                        if(desc){
                            desc = desc.description
                        }
                        $dlg.find('.template_description').html(desc);    
                        
                    });
                    $dlg.find('select').change(function(e){
                        window.hWin.HEURIST4.util.setDisabled( $dlg.parents('.ui-dialog').find('#btnDoAction'), false );
                        var sel = e.target;
                        var t_name = $(sel).val();
                        selected_element  = t_name;
                        selected_name = sel.options[sel.selectedIndex].text;
                    });
                    
                    sel.val('text').change();
                    selected_element = 'text';

                }
        });

    }
        
    //public members
    var that = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },
        
        layoutInsertElement: function(insert_ele_id, selected_element){
            _layoutInsertElement(insert_ele_id, selected_element);
        },
        
        startCMS: function(_options){
            _startCMS( _options );    
        },
        
        closeCMS: function(){
            _closeCMS();            
        }
    }
    
    return that;
}
   
