/**
*  Message and popup functions
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}

/*
showMsgErrJson - check if response is json 
showMsgErr     -    loads into id=dialog-error-messages

getMsgDlg      - creates and returns div (id=dialog-common-messages) that is base element for jquery ui dialog
getPopupDlg    - creates and returns div (id=dialog-popup) similar to  dialog-common-messages - but without width limit

showMsgDlg     - MAIN 
showMsgWorkInProgress - shows standard work in progress message
showPrompt    - show simple input value dialog with given prompt message
    
showMsgFlash - show buttonless dialog with given timeout
showHintFlash
checkLength  - fill given element with error message and highlight it
checkLength2 - get message if input value beyound given ranges

showMsgDlgUrl  - loads content url (not frame!) into dialog (getMsgDlg) and show it (showMsgDlg)

showDialog - creates div with frame, loads url content into it and shows it as popup dialog, on close this div will be removed
showElementAsDialog

createAlertDiv - return error-state html with alert icon 

bringCoverallToFront - show loading icon and lock all screen (curtain)
sendCoverallToBack

showDialogInDiv - show diaog content inline for h6 ui

*/
if (! window.hWin.HEURIST4.msg) window.hWin.HEURIST4.msg = {

    showMsgErrJson: function(response){
        if(typeof response === "string"){
            window.hWin.HEURIST4.msg.showMsgErr(null);
        }else{
            window.hWin.HEURIST4.msg.showMsgErr('Cannot parse server response: '+response.substr(0,255)+'...');
        }
    },

    showMsgErr: function(response, needlogin, ext_options){
        var msg = '';
        var dlg_title = null;
        var show_login_dlg = false;
        
        if(typeof response === "string"){
            msg = response;
        }else{
            var request_code = null;
            if(window.hWin.HEURIST4.util.isnull(response) || window.hWin.HEURIST4.util.isempty(response.message)){
                msg = 'Error_Empty_Message';
                if(response){
                    if(response.status==window.hWin.ResponseStatus.REQUEST_DENIED ){
                        msg = '';
                    }else if (!window.hWin.HEURIST4.util.isempty(response.request_code)){
                        request_code = response.request_code;    
                    }
                }
            }else{
                msg = response.message;
            }
            msg = window.hWin.HR(msg);

            dlg_title = response.error_title;

            if(response.sysmsg && response.status!=window.hWin.ResponseStatus.REQUEST_DENIED){
                //sysmsg for REQUEST_DENIED is current user id - it allows to check if session is expired
                msg = msg + '<br><br>System error: ';
                if(typeof response.sysmsg['join'] === "function"){
                    msg = msg + response.sysmsg.join('<br>');
                }else{
                    msg = msg + response.sysmsg;
                }

            }
            if(response.status==window.hWin.ResponseStatus.SYSTEM_FATAL
            || response.status==window.hWin.ResponseStatus.SYSTEM_CONFIG){

                msg = msg + "<br><br>May result from a network outage, or because the system is not properly configured. "
                +"If the problem persists, please report to your system administrator";

            }else if(response.status==window.hWin.ResponseStatus.INVALID_REQUEST){

                msg = msg + "<br><br>The value, number and/or set of request parameters is not valid. Please email the Heurist development team ( info at HeuristNetwork dot org)";

            }else if(response.status==window.hWin.ResponseStatus.REQUEST_DENIED){
                
                if(msg!='') msg = msg + '<br><br>';
                
                if(window.hWin && window.hWin.HAPI4){
                    dlg_title = 'Login required to access '+window.hWin.HAPI4.database;  
                    response.sysmsg = (window.hWin.HAPI4.currentUser['ugr_ID']==0)?0:1;
                }else{
                    dlg_title = 'Access denied';
                }

                if(msg=='' || (needlogin && response.sysmsg==0)){
                    msg = msg + top.HR('Session expired');
                    show_login_dlg = true;
                }else if(response.sysmsg==0){
                    msg = msg + 'You must be logged in';  
                }else{ 
                    dlg_title = 'Access denied';
                    msg = msg + 'This action is not allowed for your current permissions';    
                } 
                
            }else if(response.status==window.hWin.ResponseStatus.DB_ERROR){
                msg = msg + '<br><br>If this error occurs repeatedly, please contact '
                +'your system administrator or email us (support at HeuristNetwork dot org)'
                +' and describe the circumstances under which it occurs so that we/they can find a solution';
            }else  if(response.status==window.hWin.ResponseStatus.ACTION_BLOCKED){
                // No enough rights or action is blocked by constraints
                
            }else  if(response.status==window.hWin.ResponseStatus.NOT_FOUND){
                // The requested object not found.
                
            }
            
            if(request_code!=null){
                msg = msg + '<br>Report this code to Heurist team: "'
                    +(request_code.script+' '
                    +(window.hWin.HEURIST4.util.isempty(request_code.action)?'':request_code.action)).trim()+'"';
            }
        }
        
        if(window.hWin.HEURIST4.util.isempty(msg)){
                msg = 'Error_Empty_Message';
        }
        if(window.hWin.HEURIST4.util.isempty(dlg_title)){
                dlg_title = 'Error_Title';
        }
        dlg_title = window.hWin.HR(dlg_title);

        var buttons = {};
        buttons[window.hWin.HR('OK')]  = function() {
                    var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                    $dlg.dialog( "close" );
                    if(show_login_dlg){
                            window.hWin.HAPI4.setCurrentUser(null);
                            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS);
                    }
                }; 
        window.hWin.HEURIST4.msg.showMsgDlg(msg, buttons, dlg_title, ext_options);
       
        return msg; 
    },

    //
    // loads content url into dialog (getMsgDlg) and show it (showMsgDlg)
    //
    showMsgDlgUrl: function(url, buttons, title, options){

        var $dlg;
        if(url){
            var isPopupDlg = (options && (options.isPopupDlg || options.container));
            var $dlg = isPopupDlg
                            ?window.hWin.HEURIST4.msg.getPopupDlg( options.container )
                            :window.hWin.HEURIST4.msg.getMsgDlg();
            $dlg.load(url, function(){
                window.hWin.HEURIST4.msg.showMsgDlg(null, buttons, title, options);
            });
        }
        return $dlg;
    },
    
    //
    //  shows standard work in progress message (not used)
    //
    showMsgWorkInProgress: function( isdlg, message ){

        if(window.hWin.HEURIST4.util.isempty(message)){
            message = "this feature";
        }

        message = "Beta version: we are still working on "
              + message
              + "<br/><br/>Please email Heurist support (info at HeuristNetwork dot org)"
              + "<br/>if you need this feature and we will provide workarounds and/or fast-track your needs.";

        if(isdlg){
            window.hWin.HEURIST4.msg.showMsgDlg(message, null, "Work in Progress");    
        }else{
            window.hWin.HEURIST4.msg.showMsgFlash(message, 4000, {title:'Work in Progress',height:160});
        }
        
    },

    //
    // show simple input value dialog with given message
    //  message - either plain text or html with #dlg-prompt-value input element
    //
    showPrompt: function(message, callbackFunc, sTitle, ext_options){
        
        if(message.indexOf('dlg-prompt-value')<0){
            message = message+'<input id="dlg-prompt-value" class="text ui-corner-all" '
                + (ext_options && ext_options['password']?'type="password"':'') 
                + ' style="max-width: 250px; min-width: 10em; width: 250px; margin-left:0.2em"/>';    
        }
        
        return window.hWin.HEURIST4.msg.showMsgDlg( message,
        function(){
            if($.isFunction(callbackFunc)){
                var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();      
                var ele = $dlg.find('#dlg-prompt-value');
                var val = '';
                if(ele.attr('type')!='checkbox' || ele.is(':checked')){
                    val =  ele.val();
                }
                callbackFunc.call(this,val);
            }
        },
        window.hWin.HEURIST4.util.isempty(sTitle)?'Specify value':sTitle, ext_options);
        
    },
    
    //
    // creates and returns div (id=dialog-common-messages) that is base element for jquery ui dialog
    //
    getMsgDlg: function(){
        var $dlg = $( "#dialog-common-messages" );
        if($dlg.length==0){
            $dlg = $('<div>',{id:'dialog-common-messages'})
                .css({'min-wdith':'380px','max-width':'640px'}) //,padding:'1.5em 1em'
                .appendTo( $('body') ); //
                //$(window.hWin.document['body'])
        }
        return $dlg.removeClass('ui-heurist-border');
    },

    getMsgFlashDlg: function(){
        var $dlg = $( "#dialog-flash-messages" );
        if($dlg.length==0){
            $dlg = $('<div>',{id:'dialog-flash-messages'}).css({'min-wdith':'380px','max-width':'640px'})
                .appendTo('body'); //$(window.hWin.document)
        }
        return $dlg.removeClass('ui-heurist-border');
    },
    
    //
    // creates and returns div (id=dialog-popup) similar to  dialog-common-messages - but without width limit
    //
    getPopupDlg: function(element_id){        
        if(!element_id) element_id = 'dialog-popup';
        var $dlg = $( '#'+element_id );
        if($dlg.length==0){
            $dlg = $('<div>',{id:element_id})
                .css({'padding':'2em','min-wdith':'380px'}).appendTo('body'); //,'max-width':'640px' //$(window.hWin.document)
            $dlg.removeClass('ui-heurist-border');
        }
        return $dlg;
    },

    
    //
    //
    //
    showTooltipFlash: function(message, timeout, to_element){
        
        if(!$.isFunction(window.hWin.HR)){
            alert(message);
            return;
        }
        
        if(window.hWin.HEURIST4.util.isempty(message) ||  window.hWin.HEURIST4.util.isnull(to_element)){
            return;   
        }
        
        var position;
        
        if($.isPlainObject(to_element)){
                position = { my:to_element.my, at:to_element.at};
                to_element =  to_element.of;
        }else{
                position = { my: "left top", at: "left bottom", of: $(to_element) };    
        }

        if (!(timeout>200)) {
            timeout = 1000;
        }
        
        $( to_element ).attr('title',window.hWin.HR(message));
        $( to_element ).tooltip({
            position: position,
            //content: '<span>'+window.hWin.HR(message)+'</span>',
            hide: { effect: "explode", duration: 500 }
        });

        $( to_element ).tooltip('open');
        
        setTimeout(function(){
            $( to_element ).tooltip('close');
            $( to_element ).attr('title',null);
        }, timeout);
        
    },

    //
    // show buttonless dialog with given timeout
    //
    showMsgFlash: function(message, timeout, options, position_to_element){

        if(!$.isFunction(window.hWin.HR)){
            alert(message);
            return;
        }

        if(!$.isPlainObject(options)){
             options = {title:options};
        }
        
        $dlg = window.hWin.HEURIST4.msg.getMsgFlashDlg();

        var content;
        if(message!=null){
            $dlg.empty();
            content = $('<span>'+window.hWin.HR(message)+'</span>')
                    .css({'overflow':'hidden','font-weight':'bold','font-size':'1.2em'});
                    
            $dlg.append(content);
        }else{
            return;
        }
        
        var hideTitle = (options.title==null);
        if(options.title){
            options.title = window.hWin.HR(options.title);
        }

        $.extend(options, {
            resizable: false,
            width: 'auto',
            modal: false,
            //height: 80,
            buttons: {}
        });

        if(position_to_element){
           if($.isPlainObject(position_to_element)){
                options.position = position_to_element;
           }else{
                options.position = { my: "left top", at: "left bottom", of: $(position_to_element) };    
           } 
        }else{
            options.position = { my: "center center", at: "center center", of: $(document) };    
        }

        $dlg.dialog(options);
        
        if(!(options.height>0)){
            var height = $(content).height()+90;
            //options.height = $(content).height();//90;
            $dlg.dialog({height:height});
        }
           
        
        content.position({ my: "center center", at: "center center", of: $dlg });
        
        //var hh = hideTitle?0:$dlg.parent().find('.ui-dialog-titlebar').height() + $dlg.height() + 20; 
        
        //$dlg.dialog("option", "buttons", null);      
        
        if(hideTitle)
            $dlg.parent().find('.ui-dialog-titlebar').hide();
    
        if(true){
    //ui-dialog        
            $dlg.parent().css({background: '#7092BE', 'border-radius': "6px", 'border-color': '#7092BE !important',
                    'outline-style':'none', outline:'hidden'})
    //ui-dialog-content         
            $dlg.css({color:'white', border:'none', overflow:'hidden' });
            //addClass('ui-heurist-border').
        }
        
        if(timeout!==false){

            if (!(timeout>200)) {
                timeout = 1000;
            }

            
            setTimeout(window.hWin.HEURIST4.msg.closeMsgFlash, timeout);
            
        }
    },
    
    closeMsgFlash: function(){
        var $dlg = window.hWin.HEURIST4.msg.getMsgFlashDlg();
        if($dlg.dialog('instance')) $dlg.dialog('close');
        $dlg.parent().find('.ui-dialog-titlebar').show();
    },

    //@todo - redirect to error page
    redirectToError: function(message){
        window.hWin.HEURIST4.msg.showMsgDlg(message, null, 'Error');
    },

    //
    // fill given element with error message and highlight it
    // message - error message that overrides default message
    //
    checkLength: function( input, title, message, min, max ) {
        var message_text = window.hWin.HEURIST4.msg.checkLength2( input, title, min, max );
        if(message_text!=''){
                                          
                                          //'<div class="ui-state-error" style="padding:10px">XXXX'+        +'</div>'
                                          
                                          //class="ui-state-error" 
            if(!window.hWin.HEURIST4.util.isempty(message)) message_text = message;
             
            window.hWin.HEURIST4.msg.showMsgFlash('<span style="padding:10px;border:0;">'+message_text+'</span>', 3000);
            
            /*
            if(message){
                message.text(message_text);
                message.addClass( "ui-state-error" );
                setTimeout(function() {
                    message.removeClass( "ui-state-error", 1500 );
                    }, 3500 );
            } */

            return false;
        }else{
            return true;
        }

    },

    //
    // get message if input value beyound given ranges
    //
    checkLength2: function( input, title, min, max ) {

        var len = input.val().length;
        if ( (max>0 &&  len > max) || len < min ) {
            input.addClass( "ui-state-error" );
            if(max>0 && min>1){
                message_text = window.hWin.HR(title)+" "+window.hWin.HR("length must be between ") +
                min + " "+window.hWin.HR("and")+" " + max + ". ";
                if(len<min){
                    //message_text = message_text + (min-len) + window.hWin.HR(" characters left");
                }else{
                    message_text = message_text + (len-max) + window.hWin.HR(" characters over");
                }

            }else if(min>1){
               message_text = window.hWin.HR(title)+" "+window.hWin.HR('. At least '+min+' characters required'); 
            }else if(min==1){
                message_text = window.hWin.HR(title)+" "+window.hWin.HR(" is required field");
            }

            return message_text;

        } else {
            input.removeClass( "ui-state-error" );
            return '';
        }
    },

    
    /**
    * show url in iframe within popup dialog
    * 
    * options
    *   dialogid - unique id to reuse dialog  (in this case dialog will not be removed on close)
    * 
    *   is_h6style - apply heurist6 style
    *   position - adjust dialog position
    *   maximize - set maximum allowed widht and height  
    *   default_palette_class - color scheme class
    */
    showDialog: function(url, options){

        if(!options) options = {};

        if(options.container){
            window.hWin.HEURIST4.msg.showDialogInDiv(url, options);
            return;
        }


        if(!options.title) options.title = ''; // removed 'Information'  which is not a particualrly useful title

        var opener = options['window']?options['window'] :window;

        //.appendTo( that.document.find('body') )
        var $dlg = [];

        if(options['dialogid']){
            $dlg = $(opener.document).find('body #'+options['dialogid']);
        }
        
        var $dosframe;

        if($dlg.length>0){
            //reassign dialog onclose and call new parameters
            $dosframe = $dlg.find('iframe');
            var content = $dosframe[0].contentWindow;

            //close dialog from inside of frame - need redifine each time
            content.close = function() {
                var did = $dlg.attr('id');

                var rval = true;
                var closeCallback = options['callback'];
                if($.isFunction(closeCallback)){
                    rval = closeCallback.apply(opener, arguments);
                }
                if ( rval===false ){ //!rval  &&  rval !== undefined){
                    return false;
                }
                $dlg.dialog('close');
                return true;
            };
            
            $dlg.dialog('open');  

            if(options['params'] && $.isFunction(content.assignParameters)) {
                content.assignParameters(options['params']);
            }
            if(options['height']>0 && options['width']>0){
                 $dlg.dialog('option', 'width', options['width']);    
                 $dlg.dialog('option', 'height', options['height']);    
            }

        }else{

            //create new div for dialogue with $(this).uniqueId();
            $dlg = $('<div>')
            .addClass('loading')
            .appendTo( $(opener.document).find('body') );

            if(options['dialogid']){
                $dlg.attr('id', options['dialogid']);
            }else{
                $dlg.uniqueId();
            }


            if(options.class){
                $dlg.addClass(options.class);
            }

            $dosframe = $( "<iframe>").attr('parent-dlg-id', $dlg.attr('id'))
            .css({overflow: 'none !important', width:'100% !important'}).appendTo( $dlg );
            $dosframe.hide();
            /*
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
            */
            //callback function to resize dialog from internal frame functions
            $dosframe[0].doDialogResize = function(width, height) {
                //window.hWin.HEURIST4.msg.showMsgDlg('resize to '+width+','+height);
                /*
                var body = $(this.document).find('body');
                var dim = { h: Math.max(400, body.innerHeight()-10), w:Math.max(400, body.innerWidth()-10) };

                if(width>0)
                $dlg.dialog('option','width', Math.min(dim.w, width));
                if(height>0)
                $dlg.dialog('option','height', Math.min(dim.h, height));
                */    
            };

            //on load content event listener
            $dosframe.on('load', function(){

                if(window.hWin.HEURIST4.util.isempty($dosframe.attr('src'))){
                    return;
                }

                var content = $dosframe[0].contentWindow;
                try{
                    //replace standard "alert" to Heurist dialog    
                    content.alert = function(txt){
                        $dlg_alert = window.hWin.HEURIST4.msg.showMsgDlg(txt, null, ""); // Title was an unhelpful and inelegant "Info"
                        $dlg_alert.dialog('open');
                        return true;
                    }
                }catch(e){
                    console.log(e);
                }

                if(!options["title"]){
                    $dlg.dialog( "option", "title", content.document.title );
                }  

                //init help button     
                if(false && options["context_help"] && window.hWin.HEURIST4.ui){
                    window.hWin.HEURIST4.ui.initDialogHintButtons($dlg, null, options["context_help"], true);
                }


                /*
                content.confirm = function(txt){
                var resConfirm = false,
                isClosed = false;

                var $confirm_dlg = window.hWin.HEURIST4.msg.showMsgDlg(txt, function(){
                resConfirm = true;
                }, "Confirm");

                $confirm_dlg.dialog('option','close',
                function(){
                isClosed = true;        
                });

                while(!isClosed){
                $.wait(1000);
                }

                return resConfirm;
                }*/

                //content.document.reference_to_parent_dialog = $dlg.attr('id');
                //$dosframe[0].contentDocument.reference_to_parent_dialog = $dlg.attr('id');
                //functions in internal document
                //content.close = $dosframe[0].close;    // make window.close() do what we expect

                //close dialog from inside of frame
                content.close = function() {
                    var did = $dlg.attr('id');

                    var rval = true;
                    var closeCallback = options['callback'];
                    if($.isFunction(closeCallback)){
                        rval = closeCallback.apply(opener, arguments);
                    }
                    if ( rval===false ){ //!rval  &&  rval !== undefined){
                        return false;
                    }
                    $dlg.dialog('close');
                    return true;
                };

                //content.popupOpener = opener;
                content.doDialogResize = $dosframe[0].doDialogResize;

                $dlg.removeClass('loading');
                $dosframe.show();    

                var onloadCallback = options['onpopupload'];
                if(onloadCallback){
                    onloadCallback.call(opener, $dosframe[0]);
                }

                if($.isFunction(content.onFirstInit)) {  //see mapPreview
                    content.onFirstInit();
                }
                //pass parameters to frame 
                if(options['params'] && $.isFunction(content.assignParameters)) {
                    content.assignParameters(options['params']);
                }
                
                if(options['onmouseover']){ 
                    $dlg.parent().find('.ui-dialog-titlebar').mouseover(function(){
                        options['onmouseover'].call();
                    });
                    $dosframe.mouseover(function(){
                        options['onmouseover'].call();
                    });
                }
                

            });

            //    options['callback']
            //(this.document.find('body').innerHeight()-20)
            options.height = parseInt(options.height, 10);
            if(isNaN(options.height) || options.height<50){
                options.height = 480;
            } 

            //console.log('opener '+opener.innerHeight+'  '+opener.innerWidth);                        

            if(options.height > opener.innerHeight-20){
                options.height = opener.innerHeight-20;
            }
            options.width = parseInt(options.width, 10);
            if(isNaN(options.width) || options.width<100){
                options.width = 640; 
            } 
            if(options.width > opener.innerWidth-20){
                options.width = opener.innerWidth-20;
            }

            var opts = {
                autoOpen: true,
                width : options.width,
                height: options.height,
                modal: (options['modal']!==false),
                resizable: (options.resizable!==false),
                draggable: (options.draggable!==false),
                title: options["title"],
                resizeStop: function( event, ui ) {
                    $dosframe.css('width','100%');
                },
                closeOnEscape: options.closeOnEscape,
                beforeClose: options.beforeClose,
                close: function(event, ui){
                    var closeCallback = options['afterclose'];
                    if($.isFunction(closeCallback)){
                        closeCallback.apply();
                    }
                    if(!options['dialogid']){
                        $dlg.remove();
                    }
                }
            };
            $dlg.dialog(opts);

            //$dlg.addClass('ui-heurist-bg-light');
            //$dlg.parent().addClass('ui-dialog-heurist ui-heurist-explore');

            if($dlg.attr('data-palette'))
                $dlg.parent().removeClass($dlg.attr('data-palette'));
            if(options.default_palette_class){
                $dlg.attr('data-palette', options.default_palette_class);
                $dlg.parent().addClass(options.default_palette_class);
            }else{
                $dlg.attr('data-palette', null);
            }

            if(options.noClose){
                $dlg.parent().find('.ui-dialog-titlebar').find('.ui-icon-closethick').parent().hide();
            }


            if(!window.hWin.HEURIST4.util.isempty(options['padding'])) //by default 2em
                $dlg.css('padding', options.padding);

        }


        if(options.is_h6style)
        {

            $dlg.parent().addClass('ui-dialog-heurist ui-heurist-explore');

            if(options.container){

                $dlg.dialog( 'option', 'position', 
                    { my: "left top", at: "left top", of:options.container});

                function __adjustOneResize(e){
                    var ele = e ?$(e.target) :options.container;

                    var dialog_height = ele.height(); //window.innerHeight - $dlg.parent().position().top - 5;
                    $dlg.dialog( 'option', 'height', dialog_height);
                    var dialog_width = ele.width(); //window.innerWidth - $dlg.parent().position().left - 5;
                    $dlg.dialog( 'option', 'width', dialog_width);
                }
                //$(window).resize(__adjustOneResize)
                options.container.off('resize');
                options.container.on('resize', __adjustOneResize);
                __adjustOneResize();

            }else
                if(options.position){
                    $dlg.dialog( 'option', 'position', options.position );   
                    if(options.maximize){
                        function __maximizeOneResize(){
                            var dialog_height = window.innerHeight - $dlg.parent().position().top - 5;
                            $dlg.dialog( 'option', 'height', dialog_height);
                            var dialog_width = window.innerWidth - $dlg.parent().position().left - 5;
                            $dlg.dialog( 'option', 'width', dialog_width);
                        }
                        //$(window).resize(__maximizeOneResize)
                        __maximizeOneResize();
                    }else{
                        if($dlg.parent().position().left<0){
                            $dlg.parent().css({left:0});
                        }else{
                            var max_width = window.innerWidth - $dlg.parent().position().left - 5;
                            var dlg_width = $dlg.dialog( 'option', 'width');
                            if(max_width<380 || $dlg.parent().position().left<0){
                                $dlg.parent().css({left:0});
                                $dlg.dialog( 'option', 'width', 380);    
                            }else if(dlg_width>max_width){
                                $dlg.dialog( 'option', 'width', max_width);    
                            }
                        }
                    }
                }
        }

        //start content loading
        if($dosframe.attr('src')!=url || options['force_reload']){
            $dosframe.attr('src', url);
        }
        //return $dosframe;

    },

    //
    // h6 style do not popup dialog - show iframe inline
    //
    showDialogInDiv: function(url, options){

            if(!options) options = {};

            if(!options.title) options.title = '&nbsp;';
            
            $container = options['container'];
            
            var $dosframe = $container.find('iframe');
            var _innerTitle = $container.children('.ui-heurist-header');
            var frame_container = $container.children('.ent_content_full');
            var $info_button;
            var $help_button;
               
            if($dosframe.length==0)
            {
                $container.empty();    
                //add h6 style header    
                _innerTitle = $('<div>').addClass('ui-heurist-header')
                        .appendTo($container);

                frame_container = $('<div>').addClass('ent_content_full')
                        .css({'top':'37px','bottom':'1px'})
                        .appendTo($container);

                
                $dosframe = $( "<iframe>")
                            .css({height:'100%', width:'100%'})
                            .appendTo( frame_container );
            }else{
                if($dosframe.attr('src')==url){ //not changed
                    $container.show();
                    return $dosframe;        
                }
            }

            _innerTitle.empty();
            if(options.title){
                _innerTitle.text(options['title']);
                _innerTitle.show();
                frame_container.css('top','37px');
            }else{
                _innerTitle.text('');
                _innerTitle.hide();
                frame_container.css('top','0px');
            }
            
            function __onDialogClose() {
                
                var canClose = true;
                if($.isFunction(options['beforeClose'])){
                    canClose = options['beforeClose'].call( $dosframe[0], arguments );
                }
                if(canClose===false){
                    return false;
                }else{
                    $container.hide();
                    if($.isFunction(options['afterClose'])){
                        canClose = options['afterClose'].call( $dosframe[0], arguments );
                    }
                    return true;
                }
            };
//console.log('define buttons');            
            //init close button     
            $('<button>').button({icon:'ui-icon-closethick',showLabel:false, label:'Close'}) 
                                    //classes:'ui-corner-all ui-dialog-titlebar-close'})
                     .css({'position':'absolute', 'right':'4px', 'top':'6px', height:24, width:24})
                     .appendTo(_innerTitle)
                     .on({click:function(){
                         __onDialogClose();
                     }});
                     
            //init help button     
            if( options["context_help"] && window.hWin.HEURIST4.ui ){
                    
                    $info_button = $('<button>')
                            .button({icons:{primary:'ui-icon-circle-help'}, showLabel:false, label:'Help'})
                            .addClass('ui-helper-popup-button')
                            .css({'position':'absolute', 'right':'34px', 'top':'6px', height:24, width:24})
                            .appendTo(_innerTitle);
                    
                    window.hWin.HEURIST4.ui.initHelper({
                            button:$info_button, 
                            url:options['context_help'],
                            position:{my:'right top', at:'right top', of:$container},
                            container: $container,
                            is_open_at_once: true
                    });
            }else{
                //hide helper div
                frame_container.css({right:1});
                $container.children('.ui-helper-popup').hide();
            }
                

            
            $dosframe.hide();
            frame_container.addClass('loading');
            
            //callback function to resize dialog from internal iframe functions
            //$dosframe[0].doDialogResize = function(width, height) {};

            $dosframe.off('load');
            //on load content event listener
            $dosframe.on('load', function(){
                         
                if(window.hWin.HEURIST4.util.isempty($dosframe.attr('src'))){
                    return;
                }
                    
                var content = $dosframe[0].contentWindow;
                
                //replace native alert 
                try{
                    content.alert = function(txt){
                        $dlg_alert = window.hWin.HEURIST4.msg.showMsgDlg(txt, null, ""); // Title was an unhelpful and inelegant "Info"
                        $dlg_alert.dialog('open');
                        return true;
                }
                }catch(e){
                    console.log(e);
                }
                
                frame_container.removeClass('loading');
                $dosframe.show();    
                
                //close dialog from inside of frame
                content.close = __onDialogClose;
                
               
                if($.isFunction(options['doDialogResize'])){
                    content.doDialogResize = options['doDialogResize'];
                } 
                if($.isFunction(options['onContentLoad'])){
                    options['onContentLoad'].call(this, $dosframe[0]);
                } 
                       
                //pass params into iframe
                if(options['params'] && $.isFunction(content.assignParameters)) {
                    content.assignParameters(options['params']);
                }
                
            }); //onload

                        
            if(!window.hWin.HEURIST4.util.isempty(options['padding'])){
                //by default 2em
                $content.css('padding', options.padding);
            } 
                    
            //start content loading
            $dosframe.attr('src', url);
                        
            //return $dosframe;
    },
    
    //
    // take element and assign it to dialog, on dialog close place element back to original parent
    // and dialog object will be destroed
    //
    showElementAsDialog: function(options){

            var opener = options['window']?options['window'] :window;

            var $dlg = $('<div>')
               .appendTo( $(opener.document).find('body') );

            var element = options['element'];
            var originalParentNode = element.parentNode;
            originalParentNode.removeChild(element);

            $(element).show().appendTo($dlg);

            var body = $(this.document).find('body');
            //var dim = { h: Math.min((options.height>0?options.height:400), body.innerHeight()-10), 
            //            w: Math.min((options.width>0?options.width:690), body.innerWidth()-10) };
            
            var dim = { h: (options.height>0?options.height:400), 
                        w: (options.width>0?options.width:690) };
            
            var onCloseCalback = (options['close'])?options.close:null;
            
            var opts = {
                    autoOpen:(options['autoOpen']!==false),
                    width : dim.w,
                    height: dim.h,
                    modal: (options.modal!==false),
                    resizable: (options.resizable!==false),
                    //draggable: false,
                    title: options["title"],
                    buttons: options["buttons"],
                    open: options.open,  //callback
                    beforeClose: options.beforeClose,
                    close: function(event, ui){
                        
                        if($.isFunction(onCloseCalback)){
                             onCloseCalback.call(this, event, ui);
                        }
                        
                        element.parentNode.removeChild(element);
                        element.style.display = "none";
                        originalParentNode.appendChild(element);

                        $dlg.remove();
                    }
            };
            if(options["position"]) opts["position"] = options["position"];
            
            $dlg.dialog(opts);

            if(options.borderless){
                $dlg.css('padding',0);
                $dlg.parent() //s(".ui-dialog")
                      .css("border", "0 none")
                      .find(".ui-dialog-titlebar").remove();
            }
            
            if(options.default_palette_class){
                $dlg.attr('data-palette', options.default_palette_class);
                $dlg.parent().addClass(options.default_palette_class);
            }else {
                $dlg.attr('data-palette', null);
                if (options.h6style_class){
                    $dlg.parent().addClass('ui-dialog-heurist '+options.h6style_class);
                }
            }

            
            
            return $dlg;
    },

    //
    //
    //
    createAlertDiv: function(msg){
        
        return '<div class="ui-state-error" style="width:90%;margin:auto;margin-top:10px;padding:10px;">'+
                                '<span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>'+
                                msg+'</div>';
    },
    
    //curtain
    bringCoverallToFront: function(ele, styles) {
        if (!  window.hWin.HEURIST4.msg.coverall ) {
            window.hWin.HEURIST4.msg.coverall = 
                $('<div>').addClass('coverall-div').css('zIndex',60000); //9999999999
        }else{
            window.hWin.HEURIST4.msg.coverall.detach();
        }
        
        if(ele){
            if($(ele).find('.coverall-div').length==0) window.hWin.HEURIST4.msg.coverall.appendTo($(ele));
        }else{
            if($('body').find('.coverall-div').length==0) window.hWin.HEURIST4.msg.coverall.appendTo('body');
        }
        
        if(!styles){
            styles = {'background-color': "#000", opacity: '0.6'};
        }
        window.hWin.HEURIST4.msg.coverall.css( styles );
        
        $(window.hWin.HEURIST4.msg.coverall).show();
    },    
    
    sendCoverallToBack: function() {
        $(window.hWin.HEURIST4.msg.coverall).hide();//.style.visibility = "hidden";
    },
  

    //
    // MAIN method
    // buttons - callback function or objects of buttons for dialog option
    // title - either string for title, or object with labels {title:, yes: ,no, cancel, }
    // ext_options:   default_palette_class, position
    //
    showMsgDlg: function(message, buttons, labels, ext_options){

        if(!$.isFunction(window.hWin.HR)){
            alert(message);
            return;
        }
        
        if(!ext_options) ext_options = {};
        var isPopupDlg = (ext_options.isPopupDlg || ext_options.container);

        var $dlg = isPopupDlg  //show popup in specified container
                    ?window.hWin.HEURIST4.msg.getPopupDlg(ext_options.container)
                    :window.hWin.HEURIST4.msg.getMsgDlg();

        if(message!=null){
            
            var isobj = (typeof message ===  "object");

            if(!isobj){
                isPopupDlg = isPopupDlg || (message.indexOf('#')===0 && $(message).length>0);
            }
 
            if(isPopupDlg){

                $dlg = window.hWin.HEURIST4.msg.getPopupDlg( ext_options.container );
                if(isobj){
                    $dlg.append(message);
                }else if(message.indexOf('#')===0 && $(message).length>0){
                    //it seems it is in Digital Harlem only
                    $dlg.html($(message).html());
                }else{
                    $dlg.html(message);
                }

            }else{
                
                $dlg.empty();
                
                if(isobj){
                    $dlg.append(message);
                }else{
                    $dlg.append('<span>'+window.hWin.HR(message)+'</span>');
                }
            }
        }

        var title = '·', // 'Info' removed - it's a useless popup window title, better to have none at all
            lblYes = window.hWin.HR('Yes'),
            lblNo =  window.hWin.HR('No'),
            lblOk = window.hWin.HR('OK'),
            lblCancel = window.hWin.HR('Cancel');
        
        if($.isPlainObject(labels)){
            if(labels.title)  title = labels.title;
            if(labels.yes)    lblYes = labels.yes;
            if(labels.no)     lblNo = labels.no;
            if(labels.ok)     lblOk = labels.ok;
            if(labels.cancel) lblCancel = labels.cancel;
        }else if (labels!=''){
            title = labels;
        }
        
        if ($.isFunction(buttons)){ //}typeof buttons === "function"){

            callback = buttons;

            buttons = {};
            buttons[lblYes] = function() {
                $dlg.dialog( "close" );
                callback.call();
            };
            buttons[lblNo] = function() {
                $dlg.dialog( "close" );
            };
        }else if(!buttons){    //buttons are not defined - the only one OK button

            buttons = {};
            buttons[lblOk] = function() {
                $dlg.dialog( "close" );
            };

        }

        var options =  {
            title: window.hWin.HR(title),
            resizable: false,
            modal: true,
            closeOnEscape: true,
            buttons: buttons
        };
        
        if(ext_options){

           if( $.isPlainObject(ext_options) ){
                $.extend(options, ext_options);
           }
           if(ext_options.my && ext_options.at && ext_options.of){
               options.position = {my:ext_options.my, at:ext_options.at, of:ext_options.of};
           }
           else if(!ext_options.options && !$.isPlainObject(ext_options)){  
                //it seems this is not in use
                var posele = $(ext_options);
                if(posele.length>0)
                    options.position = { my: "left top", at: "left bottom", of: $(ext_options) };
           }
        }

        if(isPopupDlg){

            if(!options.open){
                options.open = function(event, ui){
                    $dlg.scrollTop(0);
                };
            }

            if(!options.height) options.height = 515;
            if(!options.width) options.width = 705;
            if(window.hWin.HEURIST4.util.isempty(options.resizable)) options.resizable = true;
            if(false && options.resizable === true){
            options.resizeStop = function( event, ui ) {
                    $dlg.css({overflow: 'none !important','width':'100%', 'height':$dlg.parent().height()
                            - $dlg.parent().find('.ui-dialog-titlebar').height() - $dlg.parent().find('.ui-dialog-buttonpane').height() - 20 });
                };
            }
        }else if(!options.width){
            options.width = 'auto';
        }

        
        $dlg.dialog(options);
        
        if(options.hideTitle){
            $dlg.parent().find('.ui-dialog-titlebar').hide();
        }else{
            $dlg.parent().find('.ui-dialog-titlebar').show();
        }

        
        if($dlg.attr('data-palette'))
            $dlg.parent().removeClass($dlg.attr('data-palette'));
        if(ext_options.default_palette_class){
            $dlg.attr('data-palette', ext_options.default_palette_class);
            $dlg.parent().addClass(ext_options.default_palette_class);
        }else{
            $dlg.attr('data-palette', null);
        }
        
        return $dlg;
        //$dlg.parent().find('.ui-dialog-buttonpane').removeClass('ui-dialog-buttonpane');
        //$dlg.parent().find('.ui-dialog-buttonpane').css({'background-color':''});
        //$dlg.parent().find('.ui-dialog-buttonpane').css({'background':'red none repeat scroll 0 0 !important','background-color':'transparent !important'});
        //'#8ea9b9 none repeat scroll 0 0 !important'     none !important','background-color':'none !important
    },  
    
    _progressInterval: 0,
    _progressDiv: null,
                        
    showProgress: function( $progress_div, session_id, t_interval, need_content ){
        
        var progressCounter = 0;        
        var progress_url = window.hWin.HAPI4.baseURL + "viewers/smarty/reportProgress.php";
        if(!(session_id>0)) session_id = Math.round((new Date()).getTime()/1000);
        
        window.hWin.HEURIST4.msg._progressDiv = $progress_div;
        $progress_div.show(); 
        document.body.style.cursor = 'progress';
        
        //add progress bar content
        if(need_content!=false){
            $progress_div.html('<div class="loading" style="display:none;height:80%"></div>'
            +'<div style="width:80%;height:40px;padding:5px;text-align:center;margin:auto;margin-top:20%;">'
                +'<div id="progressbar"><div class="progress-label">Processing data...</div></div>'
                +'<div class="progress_stop" style="text-align:center;margin-top:4px">Abort</div>'
            +'</div>');
        }
        
        
        var btn_stop = $progress_div.find('.progress_stop').button();
        
        //this._on(,{click: function() {
        
        btn_stop.click(function(){
                var request = {terminate:1, t:(new Date()).getMilliseconds(), session:session_id};
                window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                    _hideProgress(); //window.hWin.HEURIST4.ui
                    if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                        //console.log(response);                   
                    }
                });
            });
    
        var div_loading = $progress_div.find('.loading').show();
        var pbar = $progress_div.find('#progressbar');
        var progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});
        
        window.hWin.HEURIST4.msg._progressInterval = setInterval(function(){ 
            
            var request = {t:(new Date()).getMilliseconds(), session:session_id};            
            
            window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){

//console.log(response);                
                if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    _hideProgress();
                    //console.log(response+'  '+session_id);                   
                }else{
                    //it may return terminate,done,
                    var resp = response?response.split(','):[];
                    if(response=='terminate' || !(resp.length>=2)){
                        if(response=='terminate'){
                            _hideProgress();
                        }else{
                            div_loading.show();    
                            //pbar.progressbar( "value", 0 );
                            //progressLabel.text('wait...');
                        }
                    }else{
                        div_loading.hide();
                        if(resp[0]>0 && resp[1]>0){
                            var val = resp[0]*100/resp[1];
                            pbar.progressbar( "value", val );
                            progressLabel.text(resp[0]+' of '+resp[1]+'  '+(resp.length==3?resp[2]:''));
                        }else{
                            progressLabel.text('preparing...');
                            pbar.progressbar( "value", 0 );
                        }
                    }
                    
                    progressCounter++;
                    
                }
            },'text');
          
        
        }, t_interval);                
        
        return session_id;
    },
    
    hideProgress: function(){
        
        $('body').css('cursor','auto');
        
        if(window.hWin.HEURIST4.msg._progressInterval!=null){
            
            clearInterval(window.hWin.HEURIST4.msg._progressInterval);
            window.hWin.HEURIST4.msg._progressInterval = null;
        }
        if(window.hWin.HEURIST4.msg._progressDiv){
            window.hWin.HEURIST4.msg._progressDiv.hide();    
            window.hWin.HEURIST4.msg._progressDiv = null;
        }
        
    },
    
    
  
};