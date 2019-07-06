function onPageInit(success){   

    if(!success){
        window.hWin.HEURIST4.msg.showMsgErr('Cannot initialize system on client side, please consult Heurist developers');
        return;
    }
    
    window.hWin.HEURIST4.msg.bringCoverallToFront($('body').find('.ent_wrapper'));
    
    //cfg_widgets is from layout_defaults=.js 
    window.hWin.HAPI4.LayoutMgr.init(cfg_widgets, null);
    
    var current_pageid = $('#main-content').attr('data-homepageid'),
        was_modified = false,
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
            'media table contextmenu paste help'  //insertdatetime  wordcount save
        ],      
        //undo redo | code insert  |  fontselect fontsizeselect |  forecolor backcolor | media image link | alignleft aligncenter alignright alignjustify | fullscreen            
        toolbar: ['formatselect | bold italic forecolor backcolor  | media image link | align | bullist numlist outdent indent | table | removeformat | help | customSaveButton customCloseButton' ],  
        content_css: [
            '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i'
            //,'//www.tinymce.com/css/codepen.min.css'
        ],                    
        powerpaste_word_import: 'clean',
        powerpaste_html_import: 'clean',
        
        setup:function(editor) {

            editor.on('change', function(e) {
                was_modified = true;        
            });
            
            editor.addButton('customSaveButton', { //since v5 .ui.registry
                  icon: 'ui-icon-arrowstop-1-s',
                  text: 'Save changes',
                  onclick: function (_) {  //since v5 onAction
                        __saveChanges();
                  }
                });            

            editor.addButton('customCloseButton', {
                  text: 'Close editor',
                  onclick: function (_) {
                       //check that changed
                       if(was_modified){
                           
                            var $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                                '<br>Web page content has been modified',
                                {'Save':function(){__saveChanges( true );$dlg.dialog('close');},
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
    $('#main-menu').find('a').click(function(event){

        if(was_modified){ //!$('#btn_inline_editor').is(':visible'))
            
            //window.hWin.HEURIST4.msg.showMsgFlash('Web page content has been modified. Save changes before load other page');
            
            var $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                '<br>Web page content has been modified',
                    {'Save':function(){__saveChanges( true );$dlg.dialog('close');},
                        'Cancel':function(){$dlg.dialog('close');},
                        'Abandon changes':function(){__hideEditor();$dlg.dialog('close');}},
                                'Content modified');
            $dlg.parents('.ui-dialog').css('font-size','1.2em');    
            
            //'Web page content has been modified. Save before close?','Content modified'
            
        }else{
            __hideEditor();
        
            var pageid = $(event.target).attr('data-pageid');

            if(pageid>0){
                current_pageid = pageid;
                $('#main-content').empty().load(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                    +'&fmt=web&recid='+pageid, function()
                    {
                            window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, "#main-content" );
                    });
            }
            
            __alignButtons();
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
                //var h = $('body').innerHeight()-$('#main-header').height()-110;
                //$('#main-content').css('height', h);
                last_save_content = $('#main-content').html(); 
                tinymce.init(inlineEditorConfig);
                setTimeout(function(){
                    $('.mce-tinymce').css({position:'absolute',
                        top:140,  //
                        bottom:20 //$('#main-content').css('bottom')
                    });    
                },500);
                
                
            })
            .show();

    $('#btn_inline_editor2')
            .css({position:'absolute', right:'40px', top:'10px', 'font-size':'1.1em'}).show();
            
         
         
    //
    //
    //
    function __saveChanges( need_close ){

        var newval = tinymce.activeEditor.getContent();
        var nodes = $.parseHTML(newval);
        if(nodes && nodes.length==1 &&  !(nodes[0].childElementCount>0) &&
            (nodes[0].nodeName=='#text' || nodes[0].nodeName=='P'))
        { 
            //remove the only tag
            newval = nodes[0].textContent;
        }
        
        //send data to server
        var request = {a: 'replace',
                    recIDs: current_pageid,
                    dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'],
                    rVal: newval};
        
        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                if(response.status == hWin.ResponseStatus.OK){

                    last_save_content = newval;
                    window.hWin.HEURIST4.msg.showMsgFlash('saved');
                    
                    was_modified = false;     
                    if(need_close){
                        __hideEditor();    
                    }
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });                                        
    }
            
    //
    //
    //                    
    function __hideEditor(){
            
            tinymce.remove('#main-content');
            $('#main-content').css('height','auto');
            $('#btn_inline_editor').show();
            if(was_modified && last_save_content){
                $('#main-content').html(last_save_content);
            }
            last_save_content = null;    
            was_modified = false;    
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
 
}