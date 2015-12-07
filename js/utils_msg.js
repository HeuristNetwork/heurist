if (!top.HEURIST4){
    top.HEURIST4 = {};
}
if (! top.HEURIST4.msg) top.HEURIST4.msg = {
    
    EMPTY_MESSAGE:("Server returns nothing. Either server not accessible or script is corrupted."
                +" Please try later and if issue persists please consult your system administrator "
                +" or contact development team"),    

    showMsgErrJson: function(response){
        if(typeof response === "string"){
            showMsgErr(null);
        }else{
            showMsgErr('Can not parse server response: '+response.substr(0,255)+'...');
        }        
    },
    
    showMsgErr: function(response){
        var msg = "";
        if(typeof response === "string"){
            msg = response;
        }else{
            if(top.HEURIST4.util.isempty(response.message)){
                msg = top.HEURIST4.msg.EMPTY_MESSAGE;   
            }else{
                msg = response.message;
            }

            if(response.sysmsg){

                if(typeof response.sysmsg['join'] === "function"){
                    msg = msg + '<br>System error: ' +response.sysmsg.join('<br>');
                }else{
                    msg = msg + '<br>System error: ' + response.sysmsg;
                }
                
            }
            if(response.status==top.HAPI4.ResponseStatus.SYSTEM_FATAL){

                msg = msg + "<br><br>The system is not configured properly. Please consult your system administrator";
            
            }else if(response.status==top.HAPI4.ResponseStatus.INVALID_REQUEST){

                msg = msg + "<br><br>The number and/or set of request parameters is not valid. Please contact development team";

            }else if(response.status==top.HAPI4.ResponseStatus.REQUEST_DENIED){

                msg = msg + "<br><br>This action is not allowed for your current permissions";

            }else if(response.status==top.HAPI4.ResponseStatus.DB_ERROR){
                msg = msg + "<br><br>Please consult your system administrator. Error may be due to an incomplete "   
                        +"database eg. missing stored procedures, functions, triggers, or there may be an "
                        +"error in our code (in which case we need to know so we can fix it";
            }
        }
        if(top.HEURIST4.util.isempty(msg)){
                msg = top.HEURIST4.msg.EMPTY_MESSAGE;   
        }

        top.HEURIST4.msg.showMsgDlg(msg, null, "Error");
    },
    
    //load content to dialog and show it
    // 
    //
    showMsgDlgUrl: function(url, buttons, title){

        if(url){
            var $dlg = top.HEURIST4.msg.getMsgDlg();
            $dlg.load(url, function(){
                top.HEURIST4.msg.showMsgDlg(null, buttons, title);    
            });
        }
    },
    
    //
    //
    showMsgWorkInProgress: function( message ){
        
        if(top.HEURIST4.util.isempty(message)){
            message = "this feature";
        }
        
        message = "Beta version: we are still working on "
              + message 
              + "<br/><br/>Please email Heurist support (support _at_ HeuristNetwork.org)"
              + "<br/>if you need this feature and we will provide workarounds and/or fast-track your needs.";
            
        top.HEURIST4.msg.showMsgDlg(message, null, "Work in Progress");
    },
    
    //
    //
    //
    getMsgDlg: function(){
        var $dlg = $( "#dialog-common-messages" );
        if($dlg.length==0){
            $dlg = $('<div>',{id:'dialog-common-messages'}).css({'min-wdith':'380px','max-width':'640px'}).appendTo('body');
        }
        return $dlg.removeClass('ui-heurist-border');
    },

    //similar to  dialog-common-messages - but without width limit
    getPopupDlg: function(){
        var $dlg = $( "#dialog-popup" );
        if($dlg.length==0){
            $dlg = $('<div>',{id:'dialog-popup'}).css('padding','2em').css({'min-wdith':'380px'}).appendTo('body'); //,'max-width':'640px'
            $dlg.removeClass('ui-heurist-border');
        }
        return $dlg;
    },
    
    //
    // buttons - callback function or array of buttons for dialog option
    //
    showMsgDlg: function(message, buttons, title, position_to_element, isPopupDlg){

        if(!$.isFunction(top.HR)){
            alert(message);
            return;
        }

        var $dlg = top.HEURIST4.msg.getMsgDlg();
        var isPopup = false; //bigger and resizable

        if(message!=null){
            $dlg.empty();
            
            var isobj = (typeof message ===  "object");
            
            if(!isobj){
                isPopupDlg = isPopupDlg || (message.indexOf('#')===0 && $(message).length>0);
            }
            
            if(isPopupDlg){

                $dlg = top.HEURIST4.msg.getPopupDlg();
                if(isobj){
                    $dlg.append(message);    
                }else if(message.indexOf('#')===0 && $(message).length>0){
                    $dlg.html($(message).html());    
                }else{
                    $dlg.html(message);    
                }
                
                isPopup = true;
                
            }else{
                isPopup = false;
                if(isobj){
                    $dlg.append(message);    
                }else{
                    $dlg.append('<span>'+top.HR(message)+'</span>');    
                }
            }
        }

        if(!title) title = 'Info';
        if (typeof buttons === "function"){

            var titleYes = top.HR('Yes'),
            titleNo = top.HR('No'),
            callback = buttons;

            buttons = {};
            buttons[titleYes] = function() {
                callback.call();
                $dlg.dialog( "close" );
            };
            buttons[titleNo] = function() {
                $dlg.dialog( "close" );
            };
        }else if(!buttons){

            var titleOK = top.HR('OK');
            buttons = {};
            buttons[titleOK] = function() {
                $dlg.dialog( "close" );
            };

        }
        
        var options =  {
            title: top.HR(title),
            resizable: false,
            //height:140,
            width: 'auto',
            modal: true,
            closeOnEscape: true,
            buttons: buttons
        };
        if(isPopup){
            
            options.open = function(event, ui){ 
                $dlg.scrollTop(0);
            };
            
            options.height = 515;
            options.width = 705;
            options.resizable = true;
            options.resizeStop = function( event, ui ) {
                    $dlg.css({overflow: 'none !important','width':'100%', 'height':$dlg.parent().height() 
                            - $dlg.parent().find('.ui-dialog-titlebar').height() - $dlg.parent().find('.ui-dialog-buttonpane').height() - 20 });
                };
        }
        
        if(position_to_element){
           options.position = { my: "left top", at: "left bottom", of: $(position_to_element) };
        }
        
        $dlg.dialog(options);
        
        return $dlg;
        //$dlg.parent().find('.ui-dialog-buttonpane').removeClass('ui-dialog-buttonpane');
        //$dlg.parent().find('.ui-dialog-buttonpane').css({'background-color':''});
        //$dlg.parent().find('.ui-dialog-buttonpane').css({'background':'red none repeat scroll 0 0 !important','background-color':'transparent !important'});
        //'#8ea9b9 none repeat scroll 0 0 !important'     none !important','background-color':'none !important
    },

    showMsgFlash: function(message, timeout, title, position_to_element){

        if(!$.isFunction(top.HR)){
            alert(message);
            return;
        }
        
        $dlg = top.HEURIST4.msg.getMsgDlg();
        
        $dlg.addClass('ui-heurist-border');

        if(message!=null){
            $dlg.empty();
            $dlg.append('<span>'+top.HR(message)+'</span>');
        }

        if(!title) title = 'Info';
        
        var options =  {
            title: top.HR(title),
            resizable: false,
            //height:140,
            width: 'auto',
            modal: false,
            buttons: {}
        };
        
        
        if(position_to_element){
           options.position = { my: "left top", at: "left bottom", of: $(position_to_element) };
        }
        
        $dlg.dialog(options);
        $dlg.dialog("option", "buttons", null);
        
        if (!(timeout>200)) {
            timeout = 1000;
        }
    
        setTimeout(function(){
            $dlg.dialog('close');    
        }, timeout);
        
    },
        
    //@todo - redirect to error page
    redirectToError: function(message){
        top.HEURIST4.msg.showMsgDlg(message, null, 'Error');
    },

    checkLength: function( input, title, message, min, max ) {
        var message_text = top.HEURIST4.msg.checkLength2( input, title, min, max );
        if(message_text!=''){

            if(message){
                message.text(message_text);
                message.addClass( "ui-state-highlight" );
                setTimeout(function() {
                    message.removeClass( "ui-state-highlight", 1500 );
                    }, 500 );
            }

            return false;
        }else{
            return true;
        }

    },

    checkLength2: function( input, title, min, max ) {

        var len = input.val().length;
        if ( (max>0 &&  len > max) || len < min ) {
            input.addClass( "ui-state-error" );
            if(max>0 && min>1){
                message_text = top.HR(title)+" "+top.HR("length must be between ") +
                min + " "+top.HR("and")+" " + max + ". ";
                if(len<min){
                    message_text = message_text + (min-len) + top.HR(" characters left");
                }else{
                    message_text = message_text + (len-max) + top.HR(" characters over");
                }
                
                
            }else if(min==1){
                message_text = top.HR(title)+" "+top.HR("required field");
            }

            return message_text; 

        } else {
            return '';
        }
    },

    checkRegexp:function ( o, regexp ) {
        if ( !( regexp.test( o.val() ) ) ) {
            o.addClass( "ui-state-error" );
            return false;
        } else {
            return true;
        }
    },
  
    /**
    * show url in iframe within popup dialog
    */
    showDialog: function(url, options){
    
                if(!options) options = {};
        
                if(!options.title) options.title = 'Information';
        
                var opener = options['window']?options['window'] :window;
        
                //.appendTo( that.document.find('body') )
                
                //create new div for dialogue with $(this).uniqueId();                 
                var $dlg = $('<div>').addClass('loading').appendTo( $(opener.document).find('body') ).uniqueId();
                var $dosframe = $( "<iframe>").attr('parent-dlg-id', $dlg.attr('id'))
                            .css({overflow: 'none !important', width:'100% !important'}).appendTo( $dlg );
                $dosframe.hide();

                //on close event listener - invoke callback function if defined
                $dosframe[0].close = function() {
                    var rval = true;
                    var closeCallback = options['callback'];
                    if(closeCallback){
                        rval = closeCallback.apply(opener, arguments);
                    }
                    if ( !rval  &&  rval !== undefined){
                        return false;
                    }
                    
                    $dlg.dialog('close');
                    return true;
                };             

                $dosframe[0].doDialogResize = function(width, height) {
                    //top.HEURIST4.msg.showMsgDlg('resize to '+width+','+height);
                    var body = $(this.document).find('body');
                    var dim = {h:body.innerHeight()-10, w:body.innerWidth()-10};
                    
                    if(width>0)
                        $dlg.dialog('option','width', Math.min(dim.w, width));
                    if(height>0)
                        $dlg.dialog('option','height', Math.min(dim.h, height));
                };

                
                //on load content event listener
                $dosframe.on('load', function(){
                        $dosframe.show();
                        var content = $dosframe[0].contentWindow;
                        //content.document.reference_to_parent_dialog = $dlg.attr('id');
                        //$dosframe[0].contentDocument.reference_to_parent_dialog = $dlg.attr('id');
                        if(!options["title"]){
                            $dlg.dialog( "option", "title", content.document.title );
                        }
                        content.close = $dosframe[0].close;    // make window.close() do what we expect
                        //content.popupOpener = opener;  
                        content.doDialogResize = $dosframe[0].doDialogResize;
                        
                        var onloadCallback = options['onpopupload'];
                        if(onloadCallback){
                                onloadCallback.call(opener, $dosframe[0]);
                        }
                        
                        $dlg.removeClass('loading'); //.css('background','none');
                });
                
//    options['callback']
                //(this.document.find('body').innerHeight()-20)
                        options.height = parseInt(options.height, 10);
                        if(isNaN(options.height) || options.height<10){
                            options.height = top.innerHeight-20;
                        }
                        options.width = parseInt(options.width, 10);
                
                        var opts = {
                                autoOpen: true,
                                width : (options.width>0?options.width+20 :690 ),
                                height: options.height,
                                modal: true,
                                resizable: (options['no-resize']==true),
                                //draggable: false,
                                title: options["title"],
                                resizeStop: function( event, ui ) {
                                    $dosframe.css('width','100%');
                                },
                                close: function(event, ui){
                                    $dlg.remove();
                                }
                        };
                        $dlg.dialog(opts);
                        $dosframe.attr('src', url);

    },
    
    showElementAsDialog: function(options){
        
            var opener = options['window']?options['window'] :window;
        
            var $dlg = $('<div>')
               .appendTo( $(opener.document).find('body') );
               
            var element = options['element'];
            var originalParentNode = element.parentNode;
            element.parentNode.removeChild(element);
               
            $(element).show().appendTo($dlg);

            var opts = {
                    autoOpen: true,
                    width : (options.width>0?options.width+20:690),
                    height: (options.height>0?options.height+20:690),
                    modal: true,
                    resizable: (options['no-resize']==true),
                    //draggable: false,
                    title: options["title"],
                    close: function(event, ui){
                        
                        //var element = popup.element.parentNode.removeChild(popup.element);
                        element.style.display = "none";
                        originalParentNode.appendChild(element);
                        
                        $dlg.remove();
                    }
            };
            $dlg.dialog(opts);
        
            return $dlg;
    },
    
};