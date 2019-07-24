/*
* editCMS.js - edit map symbol properties 
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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

function editCMS(home_page_record_id, main_callback){
        
    var RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'],
     RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
     RT_CMS_PAGE = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_PAGE'],
     DT_CMS_TOP_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOP_MENU'],
     DT_CMS_MENU  = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'],
     DT_CMS_PAGE  = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGE'],
//     DT_CMS_THEME = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_THEME'],
     DT_NAME      = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
     
     if(!(RT_CMS_HOME>0 && RT_CMS_MENU>0 && RT_CMS_PAGE>0 && DT_CMS_TOP_MENU>0 && DT_CMS_MENU>0 && DT_CMS_PAGE>0)){
        var $dlg2 = window.hWin.HEURIST4.msg.showMsgDlg('You will need record types '
        +'99-51, 99-52 and 99-53 which aew available as part of Heurist_Reference_Set. '
        +'Click "Import" to get these definitions',
                    {'Import':function(){
                        var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                        $dlg2.dialog('close');
        
                        //import recctype 51 (CMS Home page) from Heurist_Core_Definitions        
                        window.hWin.HEURIST4.msg.bringCoverallToFront();
                        window.hWin.HEURIST4.msg.showMsgFlash('Import definitions', 10000);
                        
                        window.hWin.HAPI4.SystemMgr.import_definitions(2, 51,
                            function(response){    
                                window.hWin.HEURIST4.msg.sendCoverallToBack(); 
                                var $dlg2 = window.hWin.HEURIST4.msg.getMsgFlashDlg();
                                if($dlg2.dialog('instance')) $dlg2.dialog('close');

                                if(response.status == window.hWin.ResponseStatus.OK){
                                    editCMS(home_page_record_id, main_callback);
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);     
                                }
                            });
                        
                    },
                    'Cancel':function(){
                            var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                            $dlg2.dialog('close');}
                    },
                            'Definitions required');
        
        
        
        return;
     }
    var edit_dialog = null;
    
    var edit_buttons = [
        {text:window.hWin.HR('Close'), 
            id:'btnRecCancel',
            css:{'float':'right'}, 
            click: function() { 
                edit_dialog.dialog('close'); 
        }}
    ];

    var body = $(this.document).find('body');
    var dim = {h:body.innerHeight(), w:body.innerWidth()};
    dim.h = (window.hWin?window.hWin.innerHeight:window.innerHeight);
    //
    //
    //
/*    
    var dlg_opts = {
        autoOpen: true,
        height: dim.h*0.95,
        width:  dim.w*0.95,
        modal:  true,
        title: window.hWin.HR('Define Website'),
        resizeStop: function( event, ui ) {//fix bug
            //that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
        },
        close: function( event, ui ){
             //popup_dlg.dialog('destroy');   
        },

        buttons: edit_buttons
    };                
    
    var popup_dlg = $('#heurist-dialog-editCMS');
    
    if(popup_dlg.length>0){
        popup_dlg.empty();
    }else{
        popup_dlg = $('<div id="heurist-dialog-editCMS">')
            .appendTo( $(window.hWin.document).find('body') );
    }
    edit_dialog = popup_dlg.dialog(dlg_opts);
    popup_dlg.load(window.hWin.HAPI4.baseURL+'hclient/widgets/editing/editCMS.html', 
    );
*/    
    
    

    var edit_dialog = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
        +'hclient/widgets/editing/editCMS.html',
                edit_buttons,window.hWin.HR('Define Website'),
                {open:onDialogInit, width:dim.w*0.95, height:dim.h*0.95, isPopupDlg:true, close:function(){
                    edit_dialog.empty();//dilaog('destroy');
                }});


    var layout_opts =  {
        applyDefaultStyles: true,
        togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-w"></div>',
        togglerContent_closed:  '<div class="ui-icon ui-icon-triangle-1-e"></div>',
        west:{
            size: 400, //this.usrPreferences.summary_width
            minWidth:360,
            maxWidth:600,
            spacing_open:6,
            spacing_closed:16,  
            togglerAlign_open:'center',
            togglerAlign_closed:'top',
            togglerLength_closed:16,  //makes it square
            initClosed:false, //(this.usrPreferences.summary_closed==true || this.usrPreferences.summary_closed=='true'),
            slidable:false,   //otherwise it will be over center and autoclose
            contentSelector: '.cms'    
        },
        center:{
            minWidth:400,
            contentSelector: '.preview'    
        }
    };    


    function onDialogInit(){

            if(home_page_record_id<0){
                //create new set of records - website template
                window.hWin.HEURIST4.msg.bringCoverallToFront(edit_dialog.parents('.ui-dialog')); 
                window.hWin.HEURIST4.msg.showMsgFlash('Creating the set of website records', 10000);

                var session_id = Math.round((new Date()).getTime()/1000); //for progress

                var request = { action: 'import_records',
                    filename: 'websiteStarterRecords.hml',
                    //session: session_id,
                    id: window.hWin.HEURIST4.util.random()
                };

                window.hWin.HAPI4.doImportAction(request, function( response ){
                    
                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                    if(response.status == window.hWin.ResponseStatus.OK){
                        $('#spanRecCount2').text(response.data.count_imported);

                        _initWebSiteEditor( { q:"ids:"+response.data.ids.join(',') } );

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        edit_dialog.dialog('close');
                    }
                });

            }else{
                _initWebSiteEditor();
            }
        }


    //
    //
    //
    function _editHomePageRecord()
    {
        window.hWin.HEURIST4.ui.openRecordEdit(home_page_record_id, null,
            {selectOnSave:true, edit_obstacle: false, onselect: 
                function(event, res){
                    if(res && window.hWin.HEURIST4.util.isRecordSet(res.selection)){
                        //refresh everything
                        _initWebSiteEditor();
                    }
            }}
        );
    }

    //  
    // search all website records: home, menu, pages
    // create menu tree data
    //
    function _initWebSiteEditor(request){
        
            if(window.hWin.HEURIST4.util.isnull(request))
            {
                request = {q:(home_page_record_id>0 ?{"ids":home_page_record_id}:{'t:':RT_CMS_HOME}),
                    limit: 1,
                    rules: [{"query":'t:'+RT_CMS_MENU+' linkedfrom:'+RT_CMS_HOME+'-'+DT_CMS_TOP_MENU, //top menu
                        "levels":[{"query":'t:'+RT_CMS_MENU+' linkedfrom:'+RT_CMS_MENU+'-'+DT_CMS_MENU,  //other menu
                            "levels":[{"query":'t:'+RT_CMS_MENU+' linkedfrom:'+RT_CMS_MENU+'-'+DT_CMS_MENU, 
                                "levels":[{"query":'t:'+RT_CMS_MENU+' linkedfrom:'+RT_CMS_MENU+'-'+DT_CMS_MENU}]  }]}]}]};  
                            
                            //{"query":'t:'+RT_CMS_PAGE+' linkedfrom:'+RT_CMS_MENU+'-'+DT_CMS_PAGE} pages no need
            }
            
            var orig_site_name = '';
        
            request['detail'] = 'detail';
            //perform search        
            window.hWin.HAPI4.RecordMgr.search(request,
                function(response){
                    
                    var no_access = false;
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        var resdata = new hRecordSet(response.data);
                        
                        var treedata = [];
                        
                        function __getTreeData(parent_id, menuitems){
                            
                            var resitems = [];
                            
                            if(!window.hWin.HEURIST4.util.isnull(menuitems)){   

                                 for(var m=0; m<menuitems.length; m++){
                                        
                                        var menu_rec = resdata.getById(menuitems[m]);
                                      
                                        var $res = {};  
                                        $res['key'] = menuitems[m];
                                        $res['title'] = resdata.fld(menu_rec, DT_NAME);
                                        $res['parent_id'] = parent_id; //reference to parent menu(or home)
                                        $res['page_id'] = resdata.fld(menu_rec, DT_CMS_PAGE);
                                        $res['expanded'] = true;
                                        
                                        var menuitems2 = resdata.values(menu_rec, DT_CMS_MENU);
//console.log($res);                                        
                                        $res['children'] = __getTreeData(menuitems[m], menuitems2);
                                      
                                        resitems.push($res);
                                 }
                            }
                            return resitems;
                        } //__getTreeData
                        

                        var idx, records = resdata.getRecords();
                        for(idx in records){
                            if(idx)
                            {
                                var record = records[idx];
                                if(resdata.fld(record, 'rec_RecTypeID')== RT_CMS_HOME){
                                    
                                    
                                    no_access = !(window.hWin.HAPI4.is_admin() 
                                                || window.hWin.HAPI4.is_member(resdata.fld(record,'rec_OwnerUGrpID')));
                                    
                                    home_page_record_id  = resdata.fld(record, 'rec_ID');
                                    orig_site_name = resdata.fld(record, DT_NAME);
                                    
                                    var eled = edit_dialog.find('#web_Name').val(orig_site_name)
                                    if(no_access){
                                        eled.attr('readonly', true);
                                    }else{
                                    
                                        eled.off('blur')
                                        .on({blur:function(event){
                                            var newval = $(event.target).val();
                                            if(newval.trim()!='' && newval!=orig_site_name){
                                                var request = {a: 'replace',
                                                            recIDs: home_page_record_id,
                                                            dtyID: DT_NAME,
                                                            rVal: newval};
                                                window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                                                    if(response.status == hWin.ResponseStatus.OK){
                                                        window.hWin.HEURIST4.msg.showMsgFlash('saved');
                                                    }
                                                });
                                            }
                                        }});
                                    }
                                    

                                    var btn_refresh = edit_dialog.find('#btn_refresh');
                                    if(!btn_refresh.button('instance')){
                                    
                                        /*    
                                        var themeSwitcher = edit_dialog.find("#web_Theme").themeswitcher(
                                            {imageLocation: "external/jquery-theme-switcher/images/",
                                            initialText: currentTheme.charAt(0).toUpperCase() + currentTheme.slice(1),
                                            currentTheme: currentTheme,
                                            onSelect: function(){
                                                currentTheme = this.currentTheme;
                                        }});
                                        */
                                        
                                        if(!no_access){
                                            edit_dialog.find('#btn_edit_home').button({icon:'ui-icon-pencil'}).click(function(){
                                                _editHomePageRecord();
                                            });
                                        
                                            //add new root menu
                                            edit_dialog.find('#btn_add_menu').click(function(){
                                                    selectMenuRecord(home_page_record_id, function(){
                                                        _initWebSiteEditor();
                                                    });
                                            });
                                            
                                            var preview_frame = edit_dialog.find('#web_preview');
                                            preview_frame.on({load:function(){
                                                //find elements in preview that opens home page record editor
                                                var d = $(preview_frame[0].contentWindow.document);
                                                d.find( "#btn_inline_editor2").click(function(event){
                                                        _editHomePageRecord();
                                                });
                                                //
                                                d.find( "#edit_mode").on({click:function(event){
                                                        if($(event.target).val()==1){
                                                            $('<div>').addClass('coverall-div-bare')
                                                                .css({'zIndex':9999999999,background:'rgba(0,0,0,0.6)'})
                                                                .appendTo(edit_dialog.find('.ui-layout-west'));
                                                        }else{
                                                            edit_dialog.find('.ui-layout-west').find('.coverall-div-bare').remove();
                                                        }
                                                }});
                                                
                                            }});
                                        }//no access

                                        //reload preview
                                        btn_refresh.button({icon:'ui-icon-refresh'}).click(function(){
                                            //load new content to iframe
                                            edit_dialog.find('#web_preview').attr('src', window.hWin.HAPI4.baseURL+
                                                'viewers/record/websiteRecord.php?edit=1&db='+window.hWin.HAPI4.database+'&recid='+home_page_record_id);
                                        });
                                        
                                        var url = window.hWin.HAPI4.baseURL+
                                                    '?website&db='+window.hWin.HAPI4.database+'&id='+home_page_record_id
                                                    
                                        //open preview in new tab
                                        edit_dialog.find('#btn_preview').button({icon:'ui-icon-extlink'}).click(function(){
                                                window.open(url, '_blank');
                                        });
                                        
                                        $('#web_link').html('<b>Website URL:</b><a href="'+url+'" target="_blank" style="color:blue">'+url+'</a>');
                                    }
                                    btn_refresh.click();
                                    
                                    var topmenu = resdata.values(record, DT_CMS_TOP_MENU);
                                    treedata = __getTreeData(home_page_record_id, topmenu);
                                    break;
                                }
                            }
                        }//for records
                        
                        //init treeview ---------------------
                        var tree_element = edit_dialog.find('#web_tree');
                        
                        if(tree_element.fancytree('instance')){
                            
                            var tree = tree_element.fancytree('getTree');
                            
                            tree.reload(treedata);
                            
                        }else{
                        
                            function __defineActionIcons(item){
                                var item_li = $(item.li), 
                                menu_id = item.key, 
                                page_id = item.data.page_id,
                                is_top = (item.data.parent_id==home_page_record_id);
                                
                                if($(item).find('.svs-contextmenu3').length==0){

                                    var parent_span = item_li.children('span.fancytree-node');

                                    //add,edit menu,edit page,remove
                                    var actionspan = $('<div class="svs-contextmenu3" data-parentid="'
                                          +item.data.parent_id+'" data-menuid="'+menu_id+'" data-pageid="'+page_id+'" >'
                                          //+menu_id
                                        +'<span class="ui-icon ui-icon-plus" title="Add new page/menu item"></span>'
                                        +'<span class="ui-icon ui-icon-menu" title="Edit menu record"></span>'
                                        +'<span class="ui-icon ui-icon-document" title="Edit page record"></span>'
                                        +'<span class="ui-icon ui-icon-trash" '
                                            +'" title="Remove menu entry from website (record retains)"></span>'
                                        +'</div>').appendTo(parent_span);

                                    $('<div class="svs-contextmenu4"/>').appendTo(parent_span);

                                    actionspan.find('.ui-icon').click(function(event){
                                        var ele = $(event.target);
                                        var parent_span = ele.parents('span.fancytree-node');

                                        function __in_progress(){
                                            parent_span.find('.svs-contextmenu4').show();
                                            parent_span.find('.svs-contextmenu3').hide();
                                        }

                                        //timeout need to activate current node    
                                        setTimeout(function(){                         
                                            var ele2 = ele.parents('.svs-contextmenu3');
                                            var menuid = ele2.attr('data-menuid');
                                            var pageid = ele2.attr('data-pageid');
                                            var parent_id = ele2.attr('data-parentid');

                                            if(ele.hasClass('ui-icon-plus')){ //add new menu to 

                                                selectMenuRecord(menuid, function(){
                                                    _initWebSiteEditor();
                                                });
                                                
                                            }else if(ele.hasClass('ui-icon-menu')){
                                                
                                                __in_progress();
                                                //edit menu item
                                                window.hWin.HEURIST4.ui.openRecordEdit(menuid, null,
                                                {selectOnSave:true,
                                                 onClose: function(){ 
                                                     parent_span.find('.svs-contextmenu4').hide();
                                                 },
                                                 onselect:function(event, data){
                                                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                                        _initWebSiteEditor();
                                                    }
                                                }});
                                                

                                            }else if(ele.hasClass('ui-icon-document')){
                                                 __in_progress();
                                                //edit page
                                                window.hWin.HEURIST4.ui.openRecordEdit(pageid, null,
                                                {selectOnSave:true,
                                                 onClose: function(){ 
                                                     parent_span.find('.svs-contextmenu4').hide();
                                                 },
                                                 onselect:function(event, data){
                                                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                                        _initWebSiteEditor();
                                                    }
                                                }});
                                                
                                            }else if(ele.hasClass('ui-icon-trash')){
                                                removeMenuEntry(parent_id, menuid, function(){
                                                    item.remove();    
                                                    //@todo 
                                                    //refresh preview
                                                });
                                            }

                                            },500);
                                    });

                                    //hide icons on mouse exit
                                    function _onmouseexit(event){
                                        var node;
                                        if($(event.target).is('li')){
                                            node = $(event.target).find('.fancytree-node');
                                        }else if($(event.target).hasClass('fancytree-node')){
                                            node =  $(event.target);
                                        }else{
                                            //hide icon for parent 
                                            node = $(event.target).parents('.fancytree-node');
                                            if(node) node = $(node[0]);
                                        }
                                        var ele = node.find('.svs-contextmenu3');
                                        ele.hide();
                                    }               

                                    $(parent_span).hover(
                                        function(event){
                                            var node;
                                            if($(event.target).hasClass('fancytree-node')){
                                                node =  $(event.target);
                                            }else{
                                                node = $(event.target).parents('.fancytree-node');
                                            }
                                            if(! ($(node).hasClass('fancytree-loading') || $(node).find('.svs-contextmenu4').is(':visible')) ){
                                                var ele = $(node).find('.svs-contextmenu3');
                                                ele.css({'display':'inline-block'});//.css('visibility','visible');
                                            }
                                        }
                                    );               
                                    $(parent_span).mouseleave(
                                        _onmouseexit
                                    );
                                }
                            } //end __defineActionIcons

                            var fancytree_options =
                            {
                                checkbox: false,
                                //titlesTabbable: false,     // Add all node titles to TAB chain
                                source: treedata,
                                quicksearch: false, //true,
                                selectMode: 1, //1:single, 2:multi, 3:multi-hier (default: 2)
                                renderNode: function(event, data) {
                                    if(!no_access){
                                        var item = data.node;
                                        __defineActionIcons( item );
                                    }
                                },
                                extensions:["edit", "dnd"],
                                dnd:{
                                    preventVoidMoves: true,
                                    preventRecursiveMoves: true,
                                    autoExpandMS: 400,
                                    dragStart: function(node, data) {
                                        return true;
                                    },
                                    dragEnter: function(node, data) {
                                        //data.otherNode - dragging node
                                        //node - target node
                                        return !no_access; //node.folder ?['over'] :["before", "after"];
                                    },
                                    dragDrop: function(node, data) {
                                        //data.otherNode - dragging node
                                        //node - target node
                                        var source_parent = data.otherNode.parent.key;//data.otherNode.data.parent_id;
                                        if(!(source_parent>0))
                                            source_parent = home_page_record_id;

                                        data.otherNode.moveTo(node, data.hitMode);

                                        var target_parent = data.otherNode.parent.key;
                                        if(!(target_parent>0))
                                            target_parent = home_page_record_id;
                                        data.otherNode.data.parent_id = target_parent;
                                        
                                        var request = {actions:[]};

                                        if(source_parent!=target_parent){
                                        //remove from source
                                        request.actions.push(
                                            {a: 'delete',
                                                recIDs: source_parent,
                                                dtyID: target_parent==home_page_record_id?DT_CMS_TOP_MENU:DT_CMS_MENU,
                                                sVal:data.otherNode.key});

                                        }
                                        //return;
                                        //change order in target
                                        request.actions.push(
                                            {a: 'delete',
                                                recIDs: target_parent,
                                                dtyID: target_parent==home_page_record_id?DT_CMS_TOP_MENU:DT_CMS_MENU});

                                        for (var i=0; i<data.otherNode.parent.children.length; i++){

                                            var menu_node = data.otherNode.parent.children[i];
                                            request.actions.push(
                                                {a: 'add',
                                                    recIDs: target_parent,
                                                    dtyID: target_parent==home_page_record_id?DT_CMS_TOP_MENU:DT_CMS_MENU,
                                                    val:menu_node.key}                                                   
                                            );
                                        }                    

                                        window.hWin.HEURIST4.msg.bringCoverallToFront(edit_dialog.parents('.ui-dialog')); 
                                        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                                                window.hWin.HEURIST4.msg.sendCoverallToBack();
                                                if(response.status == hWin.ResponseStatus.OK){
                                                    window.hWin.HEURIST4.msg.showMsgFlash('saved');
                                                }else{
                                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                                }
                                        });                                        

                                    }
                                },
                                edit:{
                                    triggerStart: ["clickActive", "dblclick", "f2", "mac+enter", "shift+click"],
                                    close: function(event, data){
                                        // Editor was removed
                                        if( data.save ) {
                                            // Since we started an async request, mark the node as preliminary
                                            $(data.node.span).addClass("pending");
                                        }
                                    },                                    
                                    save:function(event, data){
                                        if(''!=data.input.val()){
                                            var new_name = data.input.val();
                                            renameEntry(data.node.key, new_name, function(){
                                                $(data.node.span).removeClass("pending");
                                                data.node.setTitle( new_name ); 
                                                __defineActionIcons( data.node );   
                                            });
                                        }else{
                                            $(data.node.span).removeClass("pending");    
                                        }
                                    }
                                }
                            };
                            
                            tree_element.fancytree(fancytree_options);
                        }
                        
                        if(no_access){

                            var eles = edit_dialog.find('.ui-layout-west');

                            //screen for summary
                            /*$('<div>').addClass('coverall-div-bare')
                            .css({top:0,bottom:60,left:0,right:0,'zIndex':9999999999,
                                  'background-image': 
                                    'url('+window.hWin.HAPI4.baseURL+'hclient/assets/non-editable-watermark.png)'})
                            .appendTo(eles);*/
                            eles.css({'background-image': 
                                    'url('+window.hWin.HAPI4.baseURL+'hclient/assets/non-editable-watermark.png)'});
                            
                            $('<div><div class="edit-button" '
                                +'style="background:#f48642 !important;margin: 40px auto;width:200px;padding:10px;border-radius:4px;">'
                                +'<h2 style="display:inline-block;color:white">View-only mode</h2>'
                                +'<span><br>Not enough rights</span></div></div>')
                            .addClass('ui-front')
                            //.addClass('coverall-div-bare')
                            .css({top:'48px', left:'85px', position:'absolute', 
                                    'text-align':'center', height:'auto'}) 
                            .appendTo(eles.find('.ent_wrapper'));
                            
                        }
                        
                        
                    }
                    
                    else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        edit_dialog.dialog('close');
                    }
                    
                }
            ); //end searh for cms records          
        
        
    } //_initWebSiteEditor
    
    
    //
    // Select or create new menu item for website
    //
    // Opens record selector popup and adds selected menu given mapdoc or other menu
    //
   function selectMenuRecord(parent_id, callback){
        
        var popup_options = {
                        select_mode: 'select_single', //select_multi
                        select_return_mode: 'recordset',
                        edit_mode: 'popup',
                        selectOnSave: true, //it means that select popup will be closed after add/edit is completed
                        title: window.hWin.HR('Select or create a website menu record'),
                        rectype_set: RT_CMS_MENU,
                        parententity: 0,
                        
                        onselect:function(event, data){
                                 if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                    var recordset = data.selection;
                                    var record = recordset.getFirstRecord();
                                    var menu_id = recordset.fld(record,'rec_ID');
                                    
                                    addMenuEntry(parent_id, menu_id, callback)
                                 }
                        }
        };//popup_options

                
        var usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_records', 
            {width: null,  //null triggers default width within particular widget
             height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });

        popup_options.width = Math.max(usrPreferences.width,710);
        popup_options.height = usrPreferences.height;
        
        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
    }
    
    //
    //
    //
    function addMenuEntry(parent_id, menu_id, callback){

        var request = {a: 'add',
                    recIDs: parent_id,
                    dtyID:  (parent_id==home_page_record_id)?DT_CMS_TOP_MENU:DT_CMS_MENU,
                    val:    menu_id};
        
        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                if(response.status == hWin.ResponseStatus.OK){
                    //refresh treeview
                    if($.isFunction(callback)) callback.call();
                }else{
                    hWin.HEURIST4.msg.showMsgErr(response);
                }
        });                                        
        
    }

    function removeMenuEntry(parent_id, menu_id, callback){

        var request = {a: 'delete',
                    recIDs: parent_id,
                    dtyID:  (parent_id==home_page_record_id)?DT_CMS_TOP_MENU:DT_CMS_MENU,
                    sVal:   menu_id};
        
        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                if(response.status == hWin.ResponseStatus.OK){
                    //refresh treeview
                    if($.isFunction(callback)) callback.call();
                }else{
                    hWin.HEURIST4.msg.showMsgErr(response);
                }
        });                                        
        
    }
    
    function renameEntry(rec_id, newvalue, callback){

        var request = {a: 'replace',
                    recIDs: rec_id,
                    dtyID:  DT_NAME,
                    rVal:    newvalue};
        
        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                if(response.status == hWin.ResponseStatus.OK){
                    //refresh treeview
                    if($.isFunction(callback)) callback.call();
                }else{
                    hWin.HEURIST4.msg.showMsgErr(response);
                }
        });                                        
        
    }
    
}
   
