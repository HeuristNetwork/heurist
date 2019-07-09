function onPageInit(success){   

    if(!success){
        window.hWin.HEURIST4.msg.showMsgErr('Cannot initialize system on client side, please consult Heurist developers');
        return;
    }
    
    window.hWin.HEURIST4.msg.bringCoverallToFront($('body').find('.ent_wrapper'));
    
    //cfg_widgets is from layout_defaults=.js 
    window.hWin.HAPI4.LayoutMgr.init(cfg_widgets, null);
    
    var current_pageid = $('#main-content').attr('data-homepageid'),
        was_modified = false, //was modified and saved - on close need to reinit widgets
        last_save_content = null;

    var inlineEditorConfig = {
        selector: '.tinymce-body',
        menubar: false,
        inline: false,
        branding: false,
        elementpath: false,
        //statusbar: true,
        resize: 'both',
        entity_encoding:'raw',

        plugins: [
            'advlist autolink lists link image media preview textcolor', //anchor charmap print 
            'searchreplace visualblocks code fullscreen',
            'media table contextmenu paste help noneditable'  //insertdatetime  wordcount save
        ],      
        //undo redo | code insert  |  fontselect fontsizeselect |  forecolor backcolor | media image link | alignleft aligncenter alignright alignjustify | fullscreen            
        toolbar: ['formatselect | bold italic forecolor backcolor  | media image link | align | bullist numlist outdent indent | table | removeformat | help | customAddWidget customSaveButton customCloseButton' ],  
        content_css: [
            '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i'
            //,'//www.tinymce.com/css/codepen.min.css'
        ],                    
        powerpaste_word_import: 'clean',
        powerpaste_html_import: 'clean',
        
        setup:function(editor) {

            /* it does not work properly
            editor.on('change', function(e) {
                was_modified = true;  
            });
            */
            
            editor.addButton('customAddWidget', { //since v5 .ui.registry
                  text: 'Add Widget',
                  onclick: function (_) {  //since v5 onAction
                        __addWidget();
                  }
                });
                            
            editor.addButton('customSaveButton', { //since v5 .ui.registry
                  icon: 'ui-icon-arrowstop-1-s',
                  text: 'Save changes',
                  onclick: function (_) {  //since v5 onAction
                        __saveChanges(false);
                  }
                });            

            editor.addButton('customCloseButton', {
                  text: 'Close editor',
                  onclick: function (_) {
                       //check that changed
                       var edited_content = __getEditorContent();
                       if( edited_content!=null && last_save_content != edited_content ){ //was_modified
                           
                            var $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                                '<br>Web page content has been modified',
                                {'Save':function(){__saveChanges( true, 0 );$dlg.dialog('close');},
                                  'Cancel':function(){$dlg.dialog('close');},
                                   'Abandon changes':function(){__hideEditor();$dlg.dialog('close');}},
                                'Content modified');
                            $dlg.parents('.ui-dialog').css('font-size','1.2em');    
                       }else{
                            //restore previous content 
                            __hideEditor();      
                       }
                       
                  }
                });            
            
        }

    };
    
    $( "#main-menu > ul" ).addClass('horizontalmenu').menu( {position:{ my: "left top", at: "left+20 bottom" }} );
    $('#main-menu').show()
    $('#main-menu').find('a').click(function(event){  //load new page

        var pageid = $(event.target).attr('data-pageid');

       var edited_content = __getEditorContent();
       if( edited_content!=null && last_save_content != edited_content ){ //was_modified
            
            //window.hWin.HEURIST4.msg.showMsgFlash('Web page content has been modified. Save changes before load other page');
            
            var $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                '<br>Web page content has been modified',
                    {'Save':function(){__saveChanges( true, pageid );$dlg.dialog('close');},
                        'Cancel':function(){$dlg.dialog('close');},
                        'Abandon changes':function(){__hideEditor(pageid);$dlg.dialog('close');}},
                                'Content modified');
            $dlg.parents('.ui-dialog').css('font-size','1.2em');    
            
        }else{
            __hideEditor( pageid );
        }
    });

    
    setTimeout(function(){
        __alignButtons();
        window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, "#main-content" );
        
        $(document).trigger(window.hWin.HAPI4.Event.ON_SYSTEM_INITED, []);
        
        window.hWin.HEURIST4.msg.sendCoverallToBack();
        
    },1500);

    
    $('#btn_inline_editor')
            //.button({icon:'ui-icon-pencil'})
            .css({position:'absolute', right:'40px', top:'40px', 'font-size':'1.1em'})
            //.position({my:'right bottom', at:'right top', of:'#main-content'})
            .click(function( event ){
                
                $('#btn_inline_editor').hide();
                
                $('#main-content').hide();
                $('.tinymce-body').show();
                last_save_content = $('.tinymce-body').val();  //keep value to restore
                tinymce.init(inlineEditorConfig);
                /*setTimeout(function(){
                    $('.mce-tinymce').css({position:'absolute',
                        top:140,  //
                        bottom:20 //$('#main-content').css('bottom')
                    });    
                },500);*/
                
                
            })
            .show();

    $('#btn_inline_editor2')
            .css({position:'absolute', right:'40px', top:'10px', 'font-size':'1.1em'}).show();
            
            
    function __loadPageById( pageid ){
        
        if(pageid>0){
            current_pageid = pageid;
            $('#main-content').empty().load(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                +'&fmt=web&recid='+pageid, function()
                {
                        $('.tinymce-body').val($('#main-content').html());
                        window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, "#main-content" );
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
    // need_close close editor and reinit page preview
    //
    function __saveChanges( need_close, new_pageid ){
        
        window.hWin.HEURIST4.msg.bringCoverallToFront($('body').find('.ent_wrapper'));

        var newval = __getEditorContent();
        
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
            
            tinymce.remove('.tinymce-body');
            $('#btn_inline_editor').show();
            
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
            
    }
    
    //
    //
    //
    function __alignButtons(){
        var itop = $('#main-header').height(); //[0].scrollHeight;
        $('#btn_inline_editor').css({top:itop-30});
        
        //$('#main-header').css('height',itop-10);
        //$('#main-content').css('top',itop+10);
    }    
    
         
    //
    // defines widget to be inserted (heurist-app-id) and its options (heurist-app-options)
    //      
    //select widget in list
    //select size
    //define search realm,  init query or savedsearch id
    //select specific options
    //returns div
    function __addWidget(){
        
        var $dlg, buttons = {};
            
        function __prepareVal(val){
            if(val==='false'){
                val = false;
            }else if(val==='true'){
                val = true;
            }
            return val;
        }
        
        function __prepareWidgetDiv(){
            //var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
            
            var widget_name = $dlg.find('#widgetName').val();
            var widget_Css = $dlg.find('#widgetCss').val();
            
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
            
                $dlg.find('div.'+widget_name+' input').each(function(idx, item){
                    item = $(item);
                    if(item.attr('type')=='checkbox'){
                        opts[item.attr('name')] = item.is(':checked');    
                    }else if(item.attr('type')=='radio'){
                        if(item.is(':checked')) opts[item.attr('name')] = __prepareVal(item.val());    
                    }else if(item.val()!=''){
                        opts[item.attr('name')] = __prepareVal(item.val());    
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
            
            var content = '<div id="mywidget_'+window.hWin.HEURIST4.util.random()+'" class="mceNonEditable" '
                +' data-heurist-app-id="'+widget_name
                + '" style="'+ widget_Css+'" '
                //+ ' data-heurist-app-options="'+ widget_options + '"'
                + '> Config for ' + widget_name+' <span style="font-style:italic">'+widget_options+'</span></div>';
                
            return content; 
        }
                
        buttons[window.hWin.HR('Add')]  = function() {
            
                    var  content = __prepareWidgetDiv();            
                    tinymce.activeEditor.insertContent(content);
            
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
        {
           open: function(){
               //init elements on dialog open
               $dlg.find('#widgetName').on({change:function( event ){
                   var val = $(event.target).val();
                   $dlg.find('div[class^="heurist_"]').hide();    
                   var dele = $dlg.find('div.'+val+'');
                   dele.show();
                   
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
                                window.hWin.HEURIST4.ui.initHSelect($select[0], false);
                                
                            });
                   
                   }
                   
                   
               }}).change();
           }  
        });
        
        
    }
 
}