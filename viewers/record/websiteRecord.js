function onPageInit(success){   

    if(!success){
        window.hWin.HEURIST4.msg.showMsgErr('Cannot initialize system on client side, please consult Heurist developers');
        return;
    }
    
    var ele = $('body').find('#main-content');
    window.hWin.HEURIST4.msg.bringCoverallToFront(ele);
    ele.show();
    
    //cfg_widgets is from layout_defaults=.js 
    window.hWin.HAPI4.LayoutMgr.init(cfg_widgets, null);
    
    var home_pageid = $('#main-content').attr('data-homepageid'),
        current_pageid = home_pageid,
        was_modified = false, //was modified and saved - on close need to reinit widgets
        last_save_content = null;

    var inlineEditorConfig = {
        selector: '.tinymce-body',
        //fixed_toolbar_container: '#main-header',
        menubar: false,
        inline: false,
        branding: false,
        elementpath: false,
        //statusbar: true,
        resize: false,
        //autoresize_on_init: false,
        //height: 300, //'100%',  //they said that this is entire height (including toolbar) alas it sets height of iframe only
        //max_height: 300,
        entity_encoding:'raw',
        inline_styles: true,

        plugins: [
            'advlist autolink lists link image media preview', //anchor charmap print 
            'searchreplace visualblocks code fullscreen',
            'media table  paste help noneditable contextmenu textcolor'  
//since v5 they are built in to the core: contextmenu textcolor
        ],      
        //undo redo | code insert  |  fontselect fontsizeselect |  forecolor backcolor | media image link | alignleft aligncenter alignright alignjustify | fullscreen            
        toolbar: ['formatselect | bold italic forecolor backcolor  | customHeuristMedia link | align | bullist numlist outdent indent | table | removeformat | help | customAddWidget customSaveButton customCloseButton' ],  
        content_css: [
            '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i'
            //,'//www.tinymce.com/css/codepen.`min.css'
        ],                    
        powerpaste_word_import: 'clean',
        powerpaste_html_import: 'clean',
        
        setup:function(editor) {

            /* it does not work properly
            editor.on('change', function(e) {
                was_modified = true;  
            });
            */
            editor.on('init', function(e) {
                last_save_content = __getEditorContent();//keep value to restore
                __initWidgetEditLinks(null);
                
                //adjust height
                var itop = $('.mce-top-area').height()>0?$('.mce-top-area').height():68;
                $('.mce-edit-area > iframe').height( $('.tinymce-body').height() - itop );
            });
            
            editor.addButton('customHeuristMedia', {
                  icon: 'image',
                  text: 'Add Media',
                  onclick: function (_) {  //since v5 onAction in v4 onclick
                        __addHeuristMedia();
                  }
                });
            
            
            editor.addButton('customAddWidget', { //since v5 .ui.registry
                  icon: 'plus',
                  text: 'Add database widget',
                  onclick: function (_) {  //since v5 onAction
                        __addEditWidget();
                  }
                });
                            
            editor.addButton('customSaveButton', { //since v5 .ui.registry
                  icon: 'save',
                  text: 'Save',
                  onclick: function (_) {  //since v5 onAction in v4 onclick
                        __saveChanges(false);
                  }
                });            

            editor.addButton('customCloseButton', {
                  icon: 'close',
                  text: 'Close',
                  onclick: function (_) {
                      __iniLoadPageById(0); 
                  }
                });            
            
        }

    };
    
    $( "#main-menu > ul" ).addClass('horizontalmenu').menu( {position:{ my: "left top", at: "left+20 bottom" }} );
    $('#main-menu').show()
    $('#main-menu').find('a').addClass('truncate').click(function(event){  //load new page

        var pageid = $(event.target).attr('data-pageid');
        __iniLoadPageById( pageid);

    });
    //reload home
    $( "#main-banner > a").click(function(event){
        __iniLoadPageById( home_pageid);
    });

    
    setTimeout(function(){
        __alignButtons();
        window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, "#main-content" );
        
        $(document).trigger(window.hWin.HAPI4.Event.ON_SYSTEM_INITED, []);
        
        window.hWin.HEURIST4.msg.sendCoverallToBack();
        
    },1500);

    
    $('#btn_inline_editor')
            .css({position:'absolute', right:'90px', top:'40px', 'font-size':'1.1em'})
            .click(function( event ){
                
                $('#btn_inline_editor').hide();
                $('#btn_inline_editor2').hide();
                $('#btn_inline_editor3').hide();
                $('#btn_inline_editor').text('Edit page content');
                $('#btn_inline_editor3').text('source');
                
                $('#main-content').parent().css('overflow-y','hidden');
                $('#main-content').hide();
                $('#edit_mode').val(1).click();//to disable left panel
                $('.tinymce-body').show();
                //last_save_content = $('.tinymce-body').val();  //keep value to restore
                //inlineEditorConfig.height = $('.tinymce-body').height();
                tinymce.init(inlineEditorConfig);
                
                /*setTimeout(function(){
                    $('.mce-tinymce').css({position:'absolute',
                        top:140,  //
                        bottom:20 //$('#main-content').css('bottom')
                    });    
                },500);*/
            })
            .show();

    $('#btn_inline_editor3')
            .css({position:'absolute', right:'40px', top:'40px', 'font-size':'1.1em'})
            .click(function( event ){
                
                if(_isDirectEditMode()){
                    //save changes
                    __saveChanges( true );
                }else{
                    $('#btn_inline_editor2').hide();
                    $('#btn_inline_editor').text('wyswyg');
                    $('#btn_inline_editor3').text('Save');
                    
                    $('#main-content').parent().css('overflow-y','hidden');
                    $('#main-content').hide();
                    $('#edit_mode').val(1).click();//to disable left panel
                    $('.tinymce-body').show();
                }
                

            })
            .show();
            
            
    $('#btn_inline_editor2')
            .css({position:'absolute', right:'40px', top:'10px', 'font-size':'1.1em'}).show();
            

    //
    //        
    //            
    function __iniLoadPageById( pageid ){                    
     
       var edited_content = __getEditorContent();
       if( edited_content!=null && last_save_content != edited_content ){ //was_modified
            
            var $dlg2 = window.hWin.HEURIST4.msg.showMsgDlg(
                '<br>Web page content has been modified',
                    {'Save':function(){__saveChanges( true, pageid );
                        var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                        $dlg2.dialog('close');},
                        'Cancel':function(){
                            var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                            $dlg2.dialog('close');},
                        'Abandon changes':function(){
                            var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                            $dlg2.dialog('close');__hideEditor(pageid);}},
                                'Content modified');
            $dlg2.parents('.ui-dialog').css('font-size','1.2em');    
            
        }else{
            __hideEditor( pageid ); //hide current editor and loads new page
        }
        
    }
            
    function __loadPageById( pageid ){
        
        if(pageid>0){
            window.hWin.HEURIST4.msg.bringCoverallToFront($('body').find('.ent_wrapper'));
            
            
            //var ele = $('#main-content').find('div[widgetid="heurist_Search"]');
            //if(ele.length>0 && ele.search('instance')) ele.search('destroy');
            
            current_pageid = pageid;
            $('#main-content').empty().load(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                +'&field=1&recid='+pageid, function()
                {
                        $('.tinymce-body').val($('#main-content').html());
                        window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, "#main-content" );
                        window.hWin.HEURIST4.msg.sendCoverallToBack();
                });
        }
        
        __alignButtons();
        
    }        
    
    function __getEditorContent(){
        var newval = null;
        if(tinymce && tinymce.activeEditor){
            newval = tinymce.activeEditor.getContent();
            var nodes = $.parseHTML(newval);
            if(nodes && nodes.length==1 &&  !(nodes[0].childElementCount>0) &&
                (nodes[0].nodeName=='#text' || nodes[0].nodeName=='P'))
            { 
                //remove the only tag
                newval = nodes[0].textContent;
            }
        }
        return newval;        
    }
    
    //
    //
    //
    function _isDirectEditMode(){
        
        return ($('#btn_inline_editor3').text()=='Save');
    }
         
    //
    // need_close close editor and reinit page preview
    //
    function __saveChanges( need_close, new_pageid ){
        
        window.hWin.HEURIST4.msg.bringCoverallToFront($('body').find('.ent_wrapper'));

        var newval = (_isDirectEditMode()) ?$('.tinymce-body').val() :__getEditorContent();
        
        //send data to server
        var request = {a: 'replace',
                    recIDs: current_pageid,
                    dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'],
                    rVal: newval};
        
        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                if(response.status == hWin.ResponseStatus.OK){

                    last_save_content = newval;
                    window.hWin.HEURIST4.msg.showMsgFlash('saved');
                    
                    was_modified = true;     
                    if(need_close){
                        __hideEditor( new_pageid );    
                    }
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                window.hWin.HEURIST4.msg.sendCoverallToBack();
        });                                        
    }
            
    //
    //
    //                    
    function __hideEditor( new_pageid ){
            
            if(!_isDirectEditMode()){
                tinymce.remove('.tinymce-body');
            }
            $('#btn_inline_editor').show();
            $('#btn_inline_editor2').show();
            $('#btn_inline_editor3').show();
            $('#btn_inline_editor').text('Edit page content');
            $('#btn_inline_editor3').text('source');
            
            if(new_pageid>0){
                __loadPageById( new_pageid );   
            }else{
                //assign last saved content
                if( last_save_content ){ 
                    $('.tinymce-body').val(last_save_content); 
                    
                    if(was_modified){
                        $('#main-content').html(last_save_content);
                        window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, "#main-content" );
                    }
                }
            }
            
            last_save_content = null;    
            was_modified = false;    
            $('.tinymce-body').hide();
            $('#main-content').show();
            $('#main-content').parent().css('overflow-y','auto');
            $('#edit_mode').val(0).click();
            
    }
    
    //
    //
    //
    function __alignButtons(){
        var itop = $('#main-header').height(); //[0].scrollHeight;
        $('#btn_inline_editor2').css({top:itop-70});
        $('#btn_inline_editor').css({top:itop-30});
        $('#btn_inline_editor3').css({top:itop-30});
        
        //$('#main-header').css('height',itop-10);
        //$('#main-content').css('top',itop+10);
    }    
    
    //
    // assign events for edit remove links on widget placeholder in timymce editor
    //
    function __initWidgetEditLinks(widgetid){

            var eles;
            if(widgetid==null){
                eles = tinymce.activeEditor.dom.select('.mceNonEditable'); //div.
            }else{
                eles = tinymce.activeEditor.dom.get( widgetid );
                eles = [eles];
            }
                
            $(eles).each(function(idx, ele){
                $(ele).find('a.edit').click(function(event){
                    var wid = $(event.target).parent().attr('id');
                    __addEditWidget( wid );                    
                });
                $(ele).find('a.remove').click(function(event){  
                    window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',function(){
                        var wid = $(event.target).parents('.mceNonEditable').attr('id');
                        tinymce.activeEditor.dom.remove( wid );
                    });
                });
            })
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
                                        
                                        var thumbURL = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
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
    // defines widget to be inserted (heurist-app-id) and its options (heurist-app-options)
    //      
    //select widget in list
    //select size
    //define search realm,  init query or savedsearch id
    //select specific options
    //returns div
    function __addEditWidget( widgetid_edit ){
        
        var $dlg, buttons = {};
            
        function __prepareVal(val){
            if(val==='false'){
                val = false;
            }else if(val==='true'){
                val = true;
            }
            return val;
        }

        //
        // from UI
        //
        function __prepareWidgetDiv( widgetid ){
            //var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
            
            var sel = $dlg.find('#widgetName');
            var widget_name = sel.val();
            var widget_title = sel.find('option:selected').attr('data-name');
            var widgetCss = $dlg.find('#widgetCss').val();
            
            var opts = {};
            
            if(widget_name=='heurist_Map'){
                
                    var layout_params = {};
                    //parameters for controls
                    layout_params['notimeline'] = !$dlg.find("#use_timeline").is(':checked');
                    layout_params['nocluster'] = !$dlg.find("#use_cluster").is(':checked');
                    layout_params['editstyle'] = $dlg.find("#editstyle").is(':checked');
                    //@todo select basemap from selector
                    //layout_params['basemap']
                    
                    var ctrls = [];
                    $dlg.find('input[name="controls"]:checked').each(
                        function(idx,item){ctrls.push($(item).val());}
                    );
                    if(ctrls.length>0) layout_params['controls'] = ctrls.join(',');
                    if(ctrls.indexOf('legend')>=0){
                        ctrls = [];
                        $dlg.find('input[name="legend"]:checked').each(
                            function(idx,item){ctrls.push($(item).val());}
                        );
                        if(ctrls.length>0 && ctrls.length<3) layout_params['legend'] = ctrls.join(',');
                    }
                    layout_params['published'] = 1;
                
                    opts['layout_params'] = layout_params;
                    opts['leaflet'] = true;
                    
                    var mapdoc_id = $dlg.find('select[name="mapdocument"]').val();
                    if(mapdoc_id>0) opts['mapdocument'] = mapdoc_id;

            }else{
                
                var cont = $dlg.find('div.'+widget_name);
                
                if(widget_name=='heurist_SearchTree'){
                    cont.find('input[name="allowed_UGrpID"]').val( 
                            cont.find('#allowed_UGrpID').editing_input('getValues') );
                    cont.find('input[name="init_svsID"]').val( 
                            cont.find('#init_svsID').editing_input('getValues') );
                }else if(widget_name=='heurist_Navigation'){
                    cont.find('input[name="menu_recIDs"]').val( 
                            cont.find('#menu_recIDs').editing_input('getValues') );
                }
            
                cont.find('input').each(function(idx, item){
                    item = $(item);
                    if(item.attr('name')){
                        if(item.attr('type')=='checkbox'){
                            opts[item.attr('name')] = item.is(':checked');    
                        }else if(item.attr('type')=='radio'){
                            if(item.is(':checked')) opts[item.attr('name')] = __prepareVal(item.val());    
                        }else if(item.val()!=''){
                            opts[item.attr('name')] = __prepareVal(item.val());    
                        }
                    }
                });
                $dlg.find('div.'+widget_name+' select').each(function(idx, item){
                    item = $(item);
                    opts[item.attr('name')] = item.val();    
                });
                
                if(widget_name=='heurist_resultListExt'){
                    opts['reload_for_recordset'] = true;
                    opts['url'] = 'viewers/smarty/showReps.php?publish=1&debug=0&template='
                        +encodeURIComponent(opts['template'])+'&[query]';
                }
                
            }
            
            opts['init_at_once'] = true;
            opts['search_realm'] = 'sr1';
            
            var widget_options = JSON.stringify(opts);
            /*var widget_options = '';//JSON.stringify(opts);
            for(var key in opts){
                widget_options = widget_options+key+':'+opts[key]+';'                
            }*/
            
            var content = '<div id="'+widgetid+'" class="mceNonEditable" '
                +' data-heurist-app-id="'+widget_name
                + '" style="'+ widgetCss+'" '
                + '>'
                + '<div style="padding:10px">Heurist '+widget_title
                //+ 'Placeholder for ' + widget_name 
                + ' widget</div><a href="#" class="edit" style="padding:0 10px">edit</a>&nbsp;&nbsp;<a href="#" class="remove">remove</a>'//+widgetCss
                + ' <span style="font-style:italic;display:none">'+widget_options+'</span></div>';
                
            return content; 
        }
        
        //
        // to UI
        //
        function __restoreValuesInUI( widgetid ){
            //var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
            var ele = $(tinymce.activeEditor.dom.get( widgetid ));
            
            var widget_name = ele.attr('data-heurist-app-id');
            $dlg.find('#widgetName').val( widget_name ); //selector
            if(ele  && ele[0].dataset)
                var s = ele[0].dataset.mceStyle;
                s = s.replace(/;/g, ";\n");
                $dlg.find('#widgetCss').val( s );
            
            var opts = window.hWin.HEURIST4.util.isJSON(ele.find('span').text());
            
            if(opts!==false){
            
                if(widget_name=='heurist_Map'){
                    
                    if(opts.layout_params){
                        $dlg.find("#use_timeline").prop('checked', !opts.layout_params.notimeline);    
                        $dlg.find("#use_cluster").prop('checked', !opts.layout_params.nocluster);    
                        $dlg.find("#editstyle").prop('checked', opts.layout_params.editstyle);    
                        var ctrls = (opts.layout_params.controls)?opts.layout_params.controls.split(','):[];
                        $dlg.find('input[name="controls"]').each(
                            function(idx,item){$(item).prop('checked',ctrls.indexOf($(item).val())>=0);}
                        );
                        var legend = (opts.layout_params.legend)?opts.layout_params.legend.split(','):[];
                        if(legend.length>0)
                        $dlg.find('input[name="legend"]').each(
                            function(idx,item){$(item).prop('checked',legend.indexOf($(item).val())>=0);}
                        );
                        
                    }
                    if(opts['mapdocument']>0){
                        $dlg.find('select[name="mapdocument"]').attr('data-mapdocument', opts['mapdocument']);        
                    }

                }else{
                
                    $dlg.find('div.'+widget_name+' input').each(function(idx, item){
                        item = $(item);
                        if(item.attr('name')){
                            if(item.attr('type')=='checkbox'){
                                item.prop('checked', opts[item.attr('name')]===true || opts[item.attr('name')]=='true');
                            }else if(item.attr('type')=='radio'){
                                item.prop('checked', item.val()== String(opts[item.attr('name')]));
                            }else {  //if(item.val()!=''){
                                item.val( opts[item.attr('name')] );
                            }
                        }
                    });
                    $dlg.find('div.'+widget_name+' select').each(function(idx, item){
                        item = $(item);
                        item.val( opts[item.attr('name')] );
                    });
                    
                    if(widget_name=='heurist_resultListExt'){
                        if(opts['template']){
                            $dlg.find('select[name="template"]').attr('data-template', opts['template']);        
                        }
                    }
                }
                
            }
        } //end __restoreValuesInUI
        
                
        buttons[window.hWin.HR(window.hWin.HEURIST4.util.isempty(widgetid_edit)?'Add':'Save')]  = function() {
            
                    var widgetid = 'mywidget_'+window.hWin.HEURIST4.util.random();
                    var  content = __prepareWidgetDiv( widgetid );            
                    
                    if(!window.hWin.HEURIST4.util.isempty(widgetid_edit)){
                        var ele = $(content).appendTo($('body'));
                        tinymce.activeEditor.dom.replace( ele[0], tinymce.activeEditor.dom.get(widgetid_edit) );
                    }else{
                        tinymce.activeEditor.insertContent(content);
                        __initWidgetEditLinks(widgetid);
                    }
            
            
                    //var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                    $dlg.dialog( "close" );
                }; 
                
        buttons[window.hWin.HR('Cancel')]  = function() {
                    //var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                    $dlg.dialog( "close" );
                };
        
      
        $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
                +"hclient/widgets/editing/editCMS_AddWidget.html?t="+(new Date().getTime()), 
                buttons, 'Add Heurist Widget to your Web Page', 
        {  container:'cms-add-widget-popup',
           width:750,
           close: function(){
                $dlg.dialog('destroy');       
                $dlg.remove();
           },
           open: function(){
               
               //init elements on dialog open
               var $select = $dlg.find('#widgetName');
               if(!window.hWin.HEURIST4.util.isempty(widgetid_edit)){
                   //fill values for edit
                   __restoreValuesInUI(widgetid_edit);
               }
               var is_initial_set = ($dlg.find('#widgetCss').val()=='');
               
               window.hWin.HEURIST4.ui.initHSelect($select[0], false);
               $select.on({change:function( event ){
                   var val = $(event.target).val();
                   $dlg.find('div[class^="heurist_"]').hide();    
                   var dele = $dlg.find('div.'+val+'');
                   dele.show();
                   
                   if(is_initial_set){
                       s = 'border:2px solid gray;background:white;position:relative;';

                       if(val=='heurist_Search'){
                            s = s + 'height:100px;width:600px;';        
                       }else if(val=='heurist_SearchTree'){
                            s = s + 'height:600px;width:230px;';        
                       }else{
                            s = s + 'height:600px;width:600px;';        
                       }
                       $dlg.find('#widgetCss').val(s);
                   }
                   
                   if(val=='heurist_Navigation'){
                       
                       var ele = dele.find('#menu_recIDs');
                       if(!ele.editing_input('instance')){
                           
                            var ed_options = {
                                recID: -1,
                                dtID: ele.attr('id'), //'group_selector',
                                //show_header: false,
                                values: [dele.find('input[name="menu_recIDs"]').val()],
                                readonly: false,
                                showclear_button: true,
                                dtFields:{
                                    dty_Type:"resource", rst_MaxValues:0,
                                    rst_DisplayName: 'Top menu items', rst_DisplayHelpText:'',
                                    rst_PtrFilteredIDs: [window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
                                              window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_PAGE'],
                                              window.hWin.HAPI4.sysinfo['dbconst']['RT_WEB_CONTENT']],
                                    rst_FieldConfig: {entity:'records', csv:false}
                                }
                            };

                            ele.editing_input(ed_options);
                            ele.parent().css('display','block');
                            ele.find('.header').css({'width':'150px','text-align':'right'});
                           
                       }
                       
                   }else
                   if(val=='heurist_SearchTree'){
                       
                       var ele = dele.find('#allowed_UGrpID');
                       if(!ele.editing_input('instance')){
                           
                            var ed_options = {
                                recID: -1,
                                dtID: ele.attr('id'), 
                                values: [dele.find('input[name="allowed_UGrpID"]').val()],
                                readonly: false,
                                showclear_button: true,
                                dtFields:{
                                    dty_Type:"resource", rst_MaxValues:1,
                                    rst_DisplayName: 'Allowed groups', rst_DisplayHelpText:'',
                                    rst_FieldConfig: {entity:'sysGroups', csv:true}
                                }
                            };

                            ele.editing_input(ed_options);
                            ele.parent().css('display','block');
                            ele.find('.header').css({'width':'150px','text-align':'right'});
                           
                       }
                       
                       ele = dele.find('#init_svsID');
                       if(!ele.editing_input('instance')){
                           
                            var ed_options = {
                                recID: -1,
                                dtID: ele.attr('id'), 
                                values: [dele.find('input[name="init_svsID"]').val()],
                                readonly: false,
                                showclear_button: true,
                                dtFields:{
                                    dty_Type:"resource", rst_MaxValues:1,
                                    rst_DisplayName: 'Initial search', rst_DisplayHelpText:'',
                                    rst_FieldConfig: {entity:'usrSavedSearches', csv:false}
                                }
                            };

                            ele.editing_input(ed_options);
                            ele.parent().css('display','block');
                            ele.find('.header').css({'width':'150px','text-align':'right'});
                           
                       }
                       
                   }else
                   if(val=='heurist_Map' && 
                    dele.find('select[name="mapdocument"]').find('options').length==0){
                       
                        var $select = dele.find('select[name="mapdocument"]');
                       //fill list of mapdpcuments
                        var request = {
                                    q: 't:'+window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT'],w: 'a',
                                    detail: 'header',
                                    source: 'cms_edit'};
                        //perform search        
                        window.hWin.HAPI4.RecordMgr.search(request,
                            function(response){
                                
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    
                                    var resdata = new hRecordSet(response.data);
                                    var idx, records = resdata.getRecords(), opts = [{key:'',title:'none'}];
                                    for(idx in records){
                                        if(idx)
                                        {
                                            var record = records[idx];
                                            opts.push({key:resdata.fld(record, 'rec_ID'), title:resdata.fld(record, 'rec_Title')});
                                        }
                                    }//for
                                    
                                    window.hWin.HEURIST4.ui.fillSelector($select[0], opts);
                                    if($select.attr('data-mapdocument')>0){
                                        $select.val( $select.attr('data-mapdocument') );
                                    }
                                    window.hWin.HEURIST4.ui.initHSelect($select[0], false);
                                    
                                }else {
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }

                            }
                        );  

                       
                   }else if(val=='heurist_resultListExt' && 
                    dele.find('select[name="template"]').find('options').length==0){
                       
                        var $select = dele.find('select[name="template"]');

                        var baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/templateOperations.php";
                        var request = {mode:'list', db:window.hWin.HAPI4.database};
                        
                        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
                            function(context){
                                
                                var opts = [];
                                if(context && context.length>0){
                                    for (var i=0; i<context.length; i++){
                                        opts.push({key:context[i].filename, title:context[i].name});
                                    } // for
                                }
                                
                                
                                window.hWin.HEURIST4.ui.fillSelector($select[0], opts);
                                if($select.attr('data-template')){
                                    $select.val( $select.attr('data-template') );
                                }
                                window.hWin.HEURIST4.ui.initHSelect($select[0], false);
                                
                            });
                   
                   }
                   
                   
               }}).change();
               
               if(!window.hWin.HEURIST4.util.isempty(widgetid_edit)){
                   window.hWin.HEURIST4.util.setDisabled($select[0], true);
               }
               
               
           }  //end open event
        });
        
        
    }
 
}