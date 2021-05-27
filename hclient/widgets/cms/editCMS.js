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

var editCMS_instance;

function editCMS( options ){

    var _className = "EditCMS";


    var home_page_record_id = options.record_id,
    header_or_content_field_id = options.field_id, //to open editor of specific field for edit_input
    main_callback = options.callback,
    webpage_title = options.webpage_title,
    webpage_private = (options.webpage_private==true);

    var RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'],
    RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
    DT_CMS_TOP_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOP_MENU'],
    DT_CMS_MENU  = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'],
    //     DT_CMS_THEME = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_THEME'],
    DT_NAME       = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
    DT_CMS_HEADER = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER'];

    if(!(RT_CMS_HOME>0 && RT_CMS_MENU>0 && DT_CMS_TOP_MENU>0 && DT_CMS_MENU>0)){
        var $dlg2 = window.hWin.HEURIST4.msg.showMsgDlg('You will need record types '
            +'99-51 (Web home) and 99-52 (Web menu/content) which are available as part of Heurist_Core_Definitions. '
            +'Click "Import" to get these definitions',
            {'Import':function(){
                var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                $dlg2.dialog('close');

                //import recctype 51 (CMS Home page) from Heurist_Core_Definitions        
                window.hWin.HEURIST4.msg.bringCoverallToFront();
                window.hWin.HEURIST4.msg.showMsgFlash('Import definitions', 10000);

                //import missed record types from Database #2 Heurist_Core_Definitions
                window.hWin.HAPI4.SystemMgr.import_definitions(2, 51,
                    function(response){    
                        window.hWin.HEURIST4.msg.sendCoverallToBack(); 
                        var $dlg2 = window.hWin.HEURIST4.msg.getMsgFlashDlg();
                        if($dlg2.dialog('instance')) $dlg2.dialog('close');

                        if(response.status == window.hWin.ResponseStatus.OK){
                            editCMS( options ); //call itself again
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
    var web_link, tree_element = null;
    var was_something_edited = false;
    var remove_menu_records = false;
    var open_page_on_init = -1;
    var home_page_record_title = '';

    var isWebPage = false; //single page website/embed otherwise website with menu

    var edit_buttons = [
        {text:window.hWin.HR('Close'), 
            id:'btnRecCancel',
            css:{'float':'right'}, 
            click: closeCMSEditor
        }
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
    popup_dlg.load(window.hWin.HAPI4.baseURL+'hclient/widgets/cms/editCMS.html', 
    );
    */    

    if(options.container){

        if(options.menu_container){
            //hide all menu items 
            options.menu_container.find('ul').hide();
            //show cms
            options.menu_container.find('ul.for_web_site').show();
            options.menu_container.find('span.ui-icon-circle-b-close').show();
            options.menu_container.find('span.ui-icon-circle-b-help').hide();
        }

        options.container.empty();
        edit_dialog = options.container;
        options.container.load(window.hWin.HAPI4.baseURL+'hclient/widgets/cms/editCMS.html',
            onDialogInit
        );
    }else{ 

        edit_dialog = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
            +'hclient/widgets/cms/editCMS.html',
            edit_buttons, window.hWin.HR('Define Website'),
            {open:onDialogInit, width:dim.w*0.95, height:dim.h*0.95, isPopupDlg:true, 
                close:function(){
                    edit_dialog.empty();//dialog('destroy');
                },
                beforeClose: beforeCloseCMSEditor
        });

    }

    //not used at the moment
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

    //
    // create set of website records (if new), otherwise proceed to _initWebSiteEditor
    //
    function onDialogInit(){

        web_link = null;
        if(edit_dialog.dialog('instance')){
            web_link = edit_dialog.parent().find('.ui-dialog-buttonpane').find('#web_link');

            if(web_link.length==0){
                web_link = $('<div>').attr('id','web_link').css({padding:'14px 0 0 12px'})
                .appendTo( edit_dialog.parent().find('.ui-dialog-buttonpane') );
            }
        }


        if(home_page_record_id<0){

            isWebPage = (home_page_record_id==-2);

            //create new set of records - website template -----------------------
            window.hWin.HEURIST4.msg.bringCoverallToFront(edit_dialog.dialog('instance')?edit_dialog.parents('.ui-dialog'):null); 
            window.hWin.HEURIST4.msg.showMsgFlash(
                (isWebPage
                    ?'Creating default layout (webpage) record'
                    :'Creating the set of website records')
                , 10000);

            var session_id = Math.round((new Date()).getTime()/1000); //for progress

            var request = { action: 'import_records',
                filename: isWebPage?'webpageStarterRecords.xml':'websiteStarterRecords.xml',
                is_cms_init: 1,
                is_cms_public: (webpage_private===true)?0:1 ,
                //session: session_id,
                id: window.hWin.HEURIST4.util.random()
            };

            //create default set of records for website see importController
            window.hWin.HAPI4.doImportAction(request, function( response ){

                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(response.status == window.hWin.ResponseStatus.OK){
                    $('#spanRecCount2').text(response.data.count_imported);

                    if(isWebPage){
                        //update title of webpage
                        if(!window.hWin.HEURIST4.util.isempty(webpage_title)){

                            var page_recid = response.data.ids[0];

                            var request = {a: 'replace',
                                recIDs: page_recid,
                                dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
                                rVal: webpage_title};
                            window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                                if(response.status == hWin.ResponseStatus.OK){
                                    _initWebSiteEditor( true, { q:"ids:"+page_recid } );
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }
                            });
                            return;
                        }
                    }else{

                        window.hWin.HEURIST4.msg.showMsgDlg(
                            '<p>To save you time we have created a set of commonly used menu entries and web pages with dummy content.</p>'
                            +'<p>Please use <b>Menu &amp; pages</b> on the left to delete the menu entries you don\'t need or to rename them '
                            +'(don\'t forget to change the title of the page which is generally a longer version of the menu label). You can also add new ones.</p>'
                            +'<p>The pages can be edited by navigating to the page in the preview above and clicking '
                            +'<b>Edit page content</b> (either the button on the left or the link on the right of the page)</p>');

                        _initWebSiteEditor( true, { q:"ids:"+response.data.ids.join(',') } );
                    }

                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    closeCMSEditor();
                }
            });

        }else{
            _initWebSiteEditor( true );
        }
    }


    //
    // open record editor
    //
    function _editHomePageRecord()
    {
        window.hWin.HEURIST4.ui.openRecordEdit(home_page_record_id, null,
            {selectOnSave:true, edit_obstacle: false, onselect: 
                function(event, res){
                    if(res && window.hWin.HEURIST4.util.isRecordSet(res.selection)){
                        //refresh everything
                        _initWebSiteEditor(true);
                    }
            }}
        );

    }

    //  
    // search all website records: home, menu, pages
    // create menu tree data
    //
    function _initWebSiteEditor(need_refresh_preview, request){

        if(window.hWin.HEURIST4.util.isnull(request))
        {
            var request = {a:'cms_menu'};
            if(home_page_record_id>0){
                request['ids'] = home_page_record_id;
            }else{
                request['ids'] = 0; //find first home record
            }

        }else{
            request['detail'] = 'detail';    
        }

        var orig_site_name = '';


        //perform search        
        window.hWin.HAPI4.RecordMgr.search(request,
            function(response){

                var no_access = false;
                var treedata = [];

                if(response.status == window.hWin.ResponseStatus.OK){
                    var resdata = new hRecordSet(response.data);

                    var idx, records = resdata.getRecords();
                    if(resdata.length()==0){

                        window.hWin.HEURIST4.msg.showMsgErr('No one CMS home page found');
                    
                    }else if(resdata.length()==1){  //this is standalone webpage for embedding
                        var record = resdata.getFirstRecord(); 
                        if(resdata.fld(record, 'rec_RecTypeID') == RT_CMS_MENU){
                            isWebPage = true;
                            home_page_record_id    = resdata.fld(record, 'rec_ID');
                            home_page_record_title = resdata.fld(record, DT_NAME);

                            no_access = !(window.hWin.HAPI4.is_admin() 
                                || window.hWin.HAPI4.is_member(resdata.fld(record,'rec_OwnerUGrpID')));

                            _initWebPageEditor( need_refresh_preview, no_access );
                        }
                    }

                    if(!isWebPage){

                        //fill tree data
                        for(idx in records){
                            if(idx)
                            {
                                var record = records[idx];
                                //find home record
                                if(resdata.fld(record, 'rec_RecTypeID')== RT_CMS_HOME){


                                    no_access = !(window.hWin.HAPI4.is_admin() 
                                        || window.hWin.HAPI4.is_member(resdata.fld(record,'rec_OwnerUGrpID')));

                                    open_page_on_init = (home_page_record_id>0)?home_page_record_id:-1;
                                    home_page_record_id  = resdata.fld(record, 'rec_ID');
                                    orig_site_name = resdata.fld(record, DT_NAME);
                                    home_page_record_title = orig_site_name;//resdata.fld(record, DT_NAME);

                                    /*
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
                                    */

                                    //assign listeneres for control buttons on left side panel
                                    var btn_refresh = edit_dialog.find('#btn_refresh');
                                    if(!btn_refresh.button('instance')){

                                        //init buttons
                                        if(!no_access){
                                            edit_dialog.find('#btn_edit_home').button({icon:'ui-icon-pencil'}).click(function(){
                                                _editHomePageRecord();
                                            });

                                            edit_dialog.find('#btn_edit_header_content').button({icon:'ui-icon-window-minimize'}).click(function(){
                                                var preview_frame = edit_dialog.find('#web_preview');
                                                preview_frame[0].contentWindow.cmsEditing.editHeaderContent();
                                            });
                                            edit_dialog.find('#btn_reset_header_content').click(function(){
                                                var preview_frame = edit_dialog.find('#web_preview');
                                                preview_frame[0].contentWindow.cmsEditing.resetHeaderContent();
                                            });
                                            edit_dialog.find('#btn_edit_footer_content').button({icon:'ui-icon-window-minimize'}).click(function(){
                                                var preview_frame = edit_dialog.find('#web_preview');
                                                preview_frame[0].contentWindow.cmsEditing.editFooterContent();
                                            });

                                            edit_dialog.find('#btn_edit_page_content').button({icon:'ui-icon-window'}).click(function(){
                                                var preview_frame = edit_dialog.find('#web_preview');
                                                //preview_frame[0].contentWindow.editPageContent();
                                                preview_frame[0].contentWindow.cmsEditing.editPageContent();
                                            });
                                            edit_dialog.find('#btn_edit_page_source').button({icon:'ui-icon-script'}).click(function(){
                                                var preview_frame = edit_dialog.find('#web_preview');
                                                preview_frame[0].contentWindow.cmsEditing.editPageSource();
                                            });
                                            edit_dialog.find('#btn_edit_page_template').button({icon:'ui-icon-addon'
                                                    ,title:'Insert components from a template'})
                                            .click(function(){
                                                var preview_frame = edit_dialog.find('#web_preview');
                                                preview_frame[0].contentWindow.cmsEditing.addTemplate();
                                            });
                                            /*
                                            edit_dialog.find('#btn_edit_page_record').button().click(function(){
                                            var preview_frame = edit_dialog.find('#web_preview');
                                            //preview_frame[0].contentWindow.editPageRecord();

                                            preview_frame[0].contentWindow.cmsEditing.editPageRecord();
                                            });
                                            */


                                            //add new root menu
                                            edit_dialog.find('#btn_add_menu').click(function(){
                                                selectMenuRecord(home_page_record_id, function(){
                                                    was_something_edited = true;
                                                    _initWebSiteEditor();
                                                });
                                            });

                                            var preview_frame = edit_dialog.find('#web_preview');
                                            preview_frame.on({load:_onLoadWebPreview});

                                        }//no access


                                        edit_dialog.find('#btn_edit_menu').button({icon:'ui-icon-menu'}).click(function(){

                                            was_something_edited = false;

                                            window.hWin.HEURIST4.msg.showElementAsDialog({
                                                element: edit_dialog.find('#web_tree_cont')[0],
                                                height: 600,
                                                width: 350,
                                                //position:{my:"left top", at:"left bottom", "of":edit_dialog.find('#btn_edit_menu')},
                                                position:{my:"left top", at:"right+4 top", "of":
                                                    $('.ui-menu6').find('.ui-menu6-section.ui-heurist-publish')},
                                                title: window.hWin.HR('Web site menu'),
                                                h6style_class: 'ui-heurist-publish',
                                                close: function(){
                                                    _initWebSiteEditor(was_something_edited); 
                                                }
                                            });


                                        });


                                        //reload preview
                                        btn_refresh.button({icon:'ui-icon-refresh'}).click(function(){

                                            var surl = window.hWin.HAPI4.baseURL+
                                            'hclient/widgets/cms/websiteRecord.php?edit=1&db='+window.hWin.HAPI4.database+'&recid='+home_page_record_id;

                                            if (open_page_on_init>0) {
                                                if(open_page_on_init != home_page_record_id){
                                                    //var preview_frame = edit_dialog.find('#web_preview');
                                                    //preview_frame[0].contentWindow.cmsEditing.loadPageById(open_page_on_init);
                                                    surl = surl + '&initid='+open_page_on_init;
                                                }
                                                open_page_on_init = -1;
                                            }

                                            //load new content to iframe
                                            edit_dialog.find('#web_preview').attr('src', surl);                                            

                                        });

                                        var url = window.hWin.HAPI4.baseURL+
                                        '?db='+window.hWin.HAPI4.database+'&website&id='+home_page_record_id

                                        //open preview in new tab
                                        edit_dialog.find('#btn_preview').button({icon:'ui-icon-extlink'}).click(function(){
                                            window.open(url, '_blank');
                                        });

                                        if(web_link)
                                            web_link.html('<b>Website URL:</b>&nbsp;<a href="'+url+'" target="_blank" style="color:blue">'+url+'</a>');

                                        edit_dialog.find('#btn_exit').button({icon:'ui-icon-circle-b-close'}).click( closeCMSEditor );


                                        if(options.menu_container){ //init menu items

                                            options.menu_container.find('span.ui-icon-circle-b-close').click( closeCMSEditor );

                                            options.menu_container.find('li[data-cms-action]').each(
                                                function(i,item){
                                                    var li = $(item);
                                                    var btn = edit_dialog.find('#'+li.attr('data-cms-action'));
                                                    if(btn.length>0 && btn.button('instance')){
                                                        li.empty();
                                                        var icon = btn.button('option','icon');
                                                        $('<span class="ui-icon '+icon+'" style="cursor: pointer;"></span>'
                                                            +'<span class="menu-text">'+
                                                            btn.button('option','label')
                                                            +'</span>').appendTo(li);
                                                        li.css({'font-size':'smaller', padding:'6px'});
                                                    }
                                                }
                                            );
                                            edit_dialog.find('.ui-layout-west').hide();
                                            edit_dialog.find('.ui-layout-center').css({left:0});
                                        }else{
                                            edit_dialog.find('.ui-layout-west').show();
                                            edit_dialog.find('.ui-layout-center').css({left:210});

                                        }

                                    }//end init buttons

                                    if(need_refresh_preview){
                                        btn_refresh.click(); //reload home page    
                                    }

                                    var ids_was_added = [], ids_recurred = [];

                                    function __getTreeData(parent_id, menuitems){

                                        var resitems = [];

                                        if(!window.hWin.HEURIST4.util.isnull(menuitems)){   

                                            for(var m=0; m<menuitems.length; m++){

                                                var menu_rec = resdata.getById(menuitems[m]);

                                                if(ids_was_added.indexOf(menuitems[m])>=0){
                                                    //already was included
                                                    ids_recurred.push(menuitems[m]);
                                                }else{
                                                    var $res = {};  
                                                    $res['key'] = menuitems[m];
                                                    $res['title'] = resdata.fld(menu_rec, DT_NAME);
                                                    $res['parent_id'] = parent_id; //reference to parent menu(or home)
                                                    $res['expanded'] = true;

                                                    var menuitems2 = resdata.values(menu_rec, DT_CMS_MENU);
                                                    //console.log($res);                                        
                                                    ids_was_added.push(menuitems[m]);
                                                    resitems.push($res);

                                                    $res['children'] = __getTreeData(menuitems[m], menuitems2);
                                                }
                                            }
                                        }
                                        return resitems;
                                    } //__getTreeData

                                    var topmenu = resdata.values(record, DT_CMS_TOP_MENU);
                                    treedata = __getTreeData(home_page_record_id, topmenu);

                                    if(ids_recurred.length>0){
                                        var s = [];
                                        for(var i=0;i<ids_recurred.length;i++){
                                            s.push(ids_recurred[i]+' '
                                                +resdata.fld(resdata.getById(ids_recurred[i]), DT_NAME));
                                        }
                                        window.hWin.HEURIST4.msg.showMsgDlg('Some menu items are recursive references to a menu containing themselves. Such a structure is not permissible for obvious reasons.<p>'
                                            +(s.join('<br>'))
                                            +'<p>How to fix:<ul><li>Open in record editor</li>'
                                            +'<li>Find parent menu(s) in "Linked From" section</li>'
                                            +'<li>Open parent menu record and remove link to this record</li></ul>');
                                    }

                                    break;
                                }
                            }
                        }//for records

                        //init treeview ---------------------
                        if(!tree_element) tree_element = edit_dialog.find('#web_tree');

                        if(tree_element.fancytree('instance')){

                            var tree = tree_element.fancytree('getTree');

                            tree.reload(treedata);

                        }else{

                            function __defineActionIcons(item){
                                var item_li = $(item.li), 
                                menu_id = item.key, 

                                is_top = (item.data.parent_id==home_page_record_id);

                                if($(item).find('.svs-contextmenu3').length==0){

                                    var parent_span = item_li.children('span.fancytree-node');

                                    //add,edit menu,edit page,remove
                                    var actionspan = $('<div class="svs-contextmenu3" style="padding-right: 20px;" data-parentid="'
                                        +item.data.parent_id+'" data-menuid="'+menu_id+'">'
                                        +'<span class="ui-icon ui-icon-plus" title="Add new page/menu item"></span>'
                                        +'<span class="ui-icon ui-icon-pencil" title="Edit menu record"></span>'
                                        //+'<span class="ui-icon ui-icon-document" title="Edit page record"></span>'
                                        +'<span class="ui-icon ui-icon-trash" '
                                        +'" title="Remove menu entry from website"></span>'
                                        +'</div>').appendTo(parent_span);

                                    $('<div class="svs-contextmenu4"/>').appendTo(parent_span); //progress icon

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
                                            var parent_id = ele2.attr('data-parentid');

                                            if(ele.hasClass('ui-icon-plus')){ //add new menu to 

                                                selectMenuRecord(menuid, function(){
                                                    was_something_edited = true;
                                                    _initWebSiteEditor();
                                                });

                                            }else if(ele.hasClass('ui-icon-pencil')){ //edit menu record

                                                __in_progress();
                                                //edit menu item
                                                window.hWin.HEURIST4.ui.openRecordEdit(menuid, null,
                                                    {selectOnSave:true,
                                                        onClose: function(){ 
                                                            parent_span.find('.svs-contextmenu4').hide();
                                                        },
                                                        onselect:function(event, data){
                                                            if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                                                was_something_edited = true;
                                                                _initWebSiteEditor();
                                                            }
                                                }});


                                            }else 
                                                if(ele.hasClass('ui-icon-trash')){    //remove menu entry

                                                    var menu_title = ele.parents('.fancytree-node').find('.fancytree-title')[0].innerText; // Get menu title

                                                    function __doRemove(){
                                                        var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                                                        var isDelete = $dlg.find('#del_menu').is(':checked');
                                                        $dlg.dialog( "close" );

                                                        var to_del = [];
                                                        if(remove_menu_records){
                                                            item.visit(function(node){
                                                                to_del.push(node.key);
                                                                },true);
                                                        }

                                                        if(!isDelete){ // Check if the menu and related records are to be deleted, or just removed
                                                            to_del = null;
                                                        }
                                                        
                                                        removeMenuEntry(parent_id, menuid, to_del, function(){
                                                            item.remove();    
                                                            was_something_edited = true;

                                                        });
                                                    }

                                                    var buttons = {};
                                                    buttons[window.hWin.HR('Remove menu entry and sub-menus (if any)')]  = function() {
                                                        remove_menu_records = true;
                                                        __doRemove();
                                                    };
                                                    /*        
                                                    buttons[window.hWin.HR('No. Remove menu only and retain records')]  = function() {
                                                    remove_menu_records = false;
                                                    __doRemove();
                                                    };
                                                    */
                                                    buttons[window.hWin.HR('Cancel')]  = function() {
                                                        var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                                                        $dlg.dialog( "close" );
                                                    };

                                                    window.hWin.HEURIST4.msg.showMsgDlg(
                                                        'This removes the menu entry from the website, as well as all sub-menus of this menu (if any).<br><br>'
                                                        + 'To avoid removing sub-menus, move them out of this menu before removing it.<br><br>'
                                                        + '<input type="checkbox" id="del_menu">'
                                                        + '<label for="del_menu" style="display: inline-flex;">If you want to delete the actual web pages from the database, not simply remove<br>'
                                                        + 'the menu entreis from this website, check this box. Note that this is not reversible.</label>', buttons,
                                                        'Remove "'+ menu_title +'" Menu');

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
                                        return !no_access;
                                    },
                                    dragEnter: function(node, data) {
                                        //data.otherNode - dragging node
                                        //node - target node
                                        return true; //node.folder ?['over'] :["before", "after"];
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
                                                    dtyID: source_parent==home_page_record_id?DT_CMS_TOP_MENU:DT_CMS_MENU,
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

                                        //window.hWin.HEURIST4.msg.bringCoverallToFront(edit_dialog.parents('.ui-dialog')); 
                                        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                                            //window.hWin.HEURIST4.msg.sendCoverallToBack();
                                            if(response.status == hWin.ResponseStatus.OK){
                                                was_something_edited = true;
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

                            tree_element.fancytree(fancytree_options).addClass('tree-cms');
                        }



                        if(options.container) options.container.show();
                    }//!isWebPage

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
                else{ //request for cms records fails
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    closeCMSEditor();
                }

            }
        ); //end searh for cms records          


    } //_initWebSiteEditor

    //
    // init interface for standalone web page (Heurist embed)
    //
    function _initWebPageEditor( need_refresh_preview, no_access ){


        if(options.menu_container){
            //hide all menu items 
            options.menu_container.find('ul').hide();
            //show cms
            options.menu_container.find('ul.for_web_page').show();
            options.container.show();
        }else{
            edit_dialog.parent().find('.ui-dialog-title').text( window.hWin.HR('Define layout for embedding in an indpendent website') );
        }

        edit_dialog.find('.cms').children().hide();
        edit_dialog.find('.cms > .for_web_page').css('display','inline-block');//show();

        var btn_refresh = edit_dialog.find('#btn_refresh2');
        if(!btn_refresh.button('instance')){

            if(!no_access){
                edit_dialog.find('#btn_edit_page_content2').button({icon:'ui-icon-pencil'}).click(function(){
                    var preview_frame = edit_dialog.find('#web_preview');
                    preview_frame[0].contentWindow.cmsEditing.editPageContent();
                });
                edit_dialog.find('#btn_edit_page_source2').button({icon:'ui-icon-script'}).click(function(){
                    var preview_frame = edit_dialog.find('#web_preview');
                    preview_frame[0].contentWindow.cmsEditing.editPageSource();
                });
                edit_dialog.find('#btn_edit_page_record').button({icon:'ui-icon-gear'}).click(function(){
                    var preview_frame = edit_dialog.find('#web_preview');
                    //preview_frame[0].contentWindow.editPageRecord();
                    //preview_frame[0].contentWindow.cmsEditing.editPageRecord();

                    _editHomePageRecord();
                });

                var preview_frame = edit_dialog.find('#web_preview');
                preview_frame.on({load:_onLoadWebPreview});
            }//no access

            //reload preview
            btn_refresh.button({icon:'ui-icon-refresh'}).click(function(){

                var surl = window.hWin.HAPI4.baseURL+
                'hclient/widgets/cms/websiteRecord.php?edit=1&db='+window.hWin.HAPI4.database+'&recid='+home_page_record_id;

                //load new content to iframe
                edit_dialog.find('#web_preview').attr('src', surl);                                            
            });

            //
            //
            //
            edit_dialog.find('#btn_preview_page').button({icon:'ui-icon-extlink'}).click(function(){
                window.open(url, '_blank');
            });
            
            edit_dialog.find('#btn_embed_dialog').button({icon:'ui-icon-extlink'}).click(function(){
                window.hWin.HEURIST4.ui.showRecordActionDialog('embedDialog',{layout_rec_id: home_page_record_id, path:'cms/'});
            });

            var url = window.hWin.HAPI4.baseURL+
            '?db='+window.hWin.HAPI4.database+'&website&id='+home_page_record_id

            //open preview in new tab
            edit_dialog.find('#btn_preview').button({icon:'ui-icon-extlink'}).click(function(){
                window.open(url, '_blank');
            });

            if(web_link)
                web_link.html('<b>Webpage URL:</b>&nbsp;<a href="'+url+'" target="_blank" style="color:blue">'+url+'</a>');

            edit_dialog.find('#btn_exit').button({icon:'ui-icon-circle-b-close'}).click( closeCMSEditor );

            if(options.menu_container){ //init menu items

                options.menu_container.find('span.ui-icon-circle-b-close').click( closeCMSEditor );

                options.menu_container.find('li[data-cms-action]').each(
                    function(i,item){
                        var li = $(item);
                        var btn = edit_dialog.find('#'+li.attr('data-cms-action'));
                        if(btn.length>0 && btn.button('instance')){
                            li.empty();
                            var icon = btn.button('option','icon');
                            $('<span class="ui-icon '+icon+'" style="cursor: pointer;"></span>'
                                +'<span class="menu-text">'+
                                btn.button('option','label')
                                +'</span>').appendTo(li);
                            li.css({'font-size':'smaller', padding:'6px'});
                        }
                    }
                );

                edit_dialog.find('.ui-layout-west').hide();
                edit_dialog.find('.ui-layout-center').css({left:0});
            }

        }//end init buttons

        if(need_refresh_preview){
            btn_refresh.click(); //reload home page    
        }
    }

    //
    // on open tinymce editor - loads list of widgets
    //
    function _onLoadWebPreview(){

        var preview_frame = edit_dialog.find('#web_preview');
        //coverall for left side panel by invocation from websiteRecord.js
        if(preview_frame[0].contentWindow.cmsEditing){
            preview_frame[0].contentWindow.cmsEditing.onEditPageContent = function(is_exit, widgets, dest){

                var curtain = edit_dialog.find('.ui-layout-west').find('.coverall-div-bare'); 

                if(is_exit){
                    curtain.remove();
                }else if(dest=='remove'){
                    curtain.find('a[widgetid="'+widgets+'"]').parent('li').remove();

                }else{
                    if(curtain.length==0){ //add curtain
                        curtain = $('<div>').addClass('coverall-div-bare')
                        .css({'zIndex':9999,background:'rgba(0,0,0,0.6)'})
                        .appendTo(edit_dialog.find('.ui-layout-west'));
                    }
                    var wlist = null;
                    if(dest=='add'){
                        wlist = curtain.find('ul');
                        widgets = [widgets];
                    }
                    if(!wlist || wlist.length==0){
                        curtain.empty();
                        if(widgets.length>0){
                            wlist = $('<ul>').appendTo(
                                $('<div><h4>Widgets on page</h4><span class="heurist-helper2">click to edit parameters</span></div>')
                                .css({background:'white', padding:'10px', 'overflow-y': 'auto',
                                    position:'absolute',top:'50%',bottom:4, right:4, left:4})
                                .appendTo(curtain));
                        }
                    }

                    if(widgets.length>0){
                        var added = [];

                        $(widgets).each(function(idx, ele){
                            var wid = $(ele).parent().attr('id');
                            if(added.indexOf(wid)<0){
                                $('<li><a href="#" widgetid="'+wid+'">'+$(ele).find('strong').text()+'</a></li>')
                                .appendTo(wlist);
                                added.push(wid);

                                wlist.find('a').css({'line-height':'24px'}).click(function(event){
                                    preview_frame[0].contentWindow.cmsEditing.editWidget( $(event.target).attr('widgetid') );
                                    window.hWin.HEURIST4.util.stopEvent(event);
                                }).dblclick(function(e){ 
                                    e.preventDefault();
                                });
                            }
                        });
                    }
                }
            };
        }
        //find elements in preview that opens home page record editor
        var d = $(preview_frame[0].contentWindow.document);
        d.find( "#btn_inline_editor4").click();

        /*
        d.find( "#edit_mode").on({click:function(event){
        if($(event.target).val()==1){
        $('<div>').addClass('coverall-div-bare')
        .css({'zIndex':9999999999,background:'rgba(0,0,0,0.6)'})
        .appendTo(edit_dialog.find('.ui-layout-west'));
        }else{
        edit_dialog.find('.ui-layout-west').find('.coverall-div-bare').remove();
        }
        }});
        */
    }

    //
    //
    //
    function closeCMSEditor()
    {
        if(edit_dialog.dialog('instance')){
            edit_dialog.dialog('close');     
        }else if(beforeCloseCMSEditor()){
            closeCMSEditorFinally();
        }
    }

    //
    //
    //
    function closeCMSEditorFinally(){
        if(edit_dialog.dialog('instance')){
            edit_dialog.dialog('close');     
        }else{
            edit_dialog.hide();
            if(options.container){
                options.container.empty();
                //restore menu
                //hide all menu items in mainMenu6_publish
                if(options.menu_container){
                    options.menu_container.find('ul').show();
                    //show cms
                    options.menu_container.find('ul.for_web_site').hide();
                    options.menu_container.find('ul.for_web_page').hide();
                    options.menu_container.find('span.ui-icon-circle-b-close').hide();
                    options.menu_container.find('span.ui-icon-circle-b-help').show();

                }
            }
        }
        editCMS_instance = null;
    }

    //
    //
    //
    function beforeCloseCMSEditor(){
        var preview_frame = edit_dialog.find('#web_preview');
        if(preview_frame.length>0 && preview_frame[0].contentWindow.cmsEditing){
            //check that everything is saved
            var res = preview_frame[0].contentWindow.cmsEditing.onEditorExit(
                function( need_close_explicitly ){
                    //exit allowed
                    if(need_close_explicitly!==false) closeCMSEditorFinally();

                    if($.isFunction(main_callback) && home_page_record_id>0){
                        main_callback( home_page_record_id, home_page_record_title ); 
                    }
            });
            return res;
        }
    }    
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
            default_palette_class: 'ui-heurist-publish',

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
    // Add new menu(page) menu_id to  parent_id
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

    //
    //
    //
    function removeMenuEntry(parent_id, menu_id, records_to_del, callback){

        //delete detail from parent menu
        var request = {a: 'delete',
            recIDs: parent_id,
            dtyID:  (parent_id==home_page_record_id)?DT_CMS_TOP_MENU:DT_CMS_MENU,
            sVal:   menu_id};

        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
            if(response.status == hWin.ResponseStatus.OK){
                if(records_to_del && records_to_del.length>0){

                    //delete children 
                    window.hWin.HAPI4.RecordMgr.remove({ids:records_to_del},
                        function(response){
                            if(response.status == hWin.ResponseStatus.OK){
                                //refresh treeview
                                if($.isFunction(callback)) callback.call();
                            }else{
                                hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        }      
                    );

                }else{
                    //refresh treeview
                    if($.isFunction(callback)) callback.call();
                }
            }else{
                hWin.HEURIST4.msg.showMsgErr(response);
            }
        });                                        

    }

    //
    //
    //
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


    //public members
    editCMS_instance = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },
        
        closeCMS: function(){
            return closeCMSEditor();
        }

    }
}
   
