/**
 * @file utils_msg.js
 * @brief Provides a comprehensive suite of functions for displaying messages, dialogs, popups, and notifications.
 * @fileOverview This file defines the `HEURIST4.msg` namespace, which includes a variety of utilities for user interaction.
 * These utilities cover:
 * - Standard error message dialogs (`showMsgErr`, `showMsgErrJson`).
 * - General purpose dialogs with customizable buttons and titles (`showMsgDlg`, `showMsg`).
 * - Dialogs for loading external URL content (`showMsgDlgUrl`, `showDialog`).
 * - Prompt dialogs for user input (`showPrompt`).
 * - Flash messages and tooltips for temporary notifications (`showMsgFlash`, `showTooltipFlash`).
 * - Input validation helpers that display messages (`checkLength`, `checkLength2`).
 * - Progress indicators (`showProgress`, `hideProgress`).
 * - Specialized dialogs like exit warnings (`showMsgOnExit`) and messages for parent record operations (`prepareParentRecordMsg`).
 * The functions often leverage jQuery UI Dialog for their implementation and provide options for styling and behavior.
 * @project     Heurist academic knowledge management system
 *
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 4.0
 */

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}

/**
 * @namespace HEURIST4.msg
 * @memberof HEURIST4
 * @description Provides a comprehensive set of functions for displaying various types of messages,
 * dialogs, popups, tooltips, and progress indicators within the Heurist application.
 * This includes error handling, informational messages, user prompts, modal dialogs for
 * iframe content, and temporary flash notifications.
 */
if (! window.hWin.HEURIST4.msg) window.hWin.HEURIST4.msg = {

    /**
     * Displays an error message, specifically handling JSON-related error responses.
     * If the response is a string (implying a JSON parsing error), it shows a generic JSON parse error.
     * Otherwise, it passes the response object to `showMsgErr`.
     *
     * @param {Object|string} response - The error response object, or a string message if JSON parsing failed.
     *                                   If an object, it's passed to `showMsgErr`.
     * @memberof HEURIST4.msg
     * @returns {void}
     */
    showMsgErrJson: function(response){
        if(typeof response === "string"){
            window.hWin.HEURIST4.msg.showMsgErr(null); // TODO: Review if passing null is intended or should be a specific message
        }else{
            // Assuming response is an error object from a failed JSON.parse, not the unparsed string.
            // If response IS the unparsed string that failed, this message might be confusing.
            // For now, documenting as is.
            window.hWin.HEURIST4.msg.showMsgErr(window.hWin.HR('Error_Json_Parse')+': '+String(response).slice(0,255)+'...');
        }
    },

    /**
     * Displays a detailed error message dialog based on a response object or string.
     * It handles different error statuses (system fatal, invalid request, access denied, DB error, etc.),
     * formats the message, sets an appropriate title, and may include system messages or a prompt to login.
     * Uses `showMsgDlg` for the actual display.
     *
     * @memberof HEURIST4.msg
     * @param {Object|string} response - The error response object or a simple error message string.
     *                                   If an object, it can have properties like `message`, `status`,
     *                                   `sysmsg`, `error_title`, `request_code`.
     * @param {boolean} [needlogin=false] - If true and the error indicates an access denied due to not being logged in,
     *                                   it may trigger a login prompt after closing the dialog.
     * @param {Object} [ext_options] - Additional options passed to `showMsgDlg` for customizing the dialog appearance.
     *                                 Typically includes `default_palette_class: 'ui-heurist-error'`.
     * @returns {string} The formatted error message string that was displayed.
     */
    showMsgErr: function(response, needlogin, ext_options){
        let msg = '';
        let dlg_title = null;
        let show_login_dlg = false;
        
        window.hWin.HEURIST4.msg.sendCoverallToBack(true);
        window.hWin.HEURIST4.msg.closeMsgFlash();
        
        if(typeof response === "string"){
            msg = response;
        }else{
            let request_code = null;
            
            if($.isPlainObject(response.message)){
                    response = response.message;
            }
            
            if(window.hWin.HEURIST4.util.isempty(response.message) || response.message.trim().toLowerCase() == 'error'){

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

            dlg_title = response.error_title?response.error_title:'';

            if(response.sysmsg && response.status!=window.hWin.ResponseStatus.REQUEST_DENIED){
                //sysmsg for REQUEST_DENIED is current user id - it allows to check if session is expired
                msg = msg + '<br><br>System error:<br>';
                if(typeof response.sysmsg['join'] === "function"){
                    msg = msg + response.sysmsg.join('<br>');
                }else{
                    msg = msg + response.sysmsg;
                }

            }
            
            let error_report_team = '';
            if(window.hWin?.HAPI4 && window.hWin.HAPI4?.sysinfo){
                let admin_email =  window.hWin.HAPI4.sysinfo.sysadmin_email;
                error_report_team = '<br><br>'+window.hWin.HR('Error_Report_Team').replace('#sysadmin_email#', admin_email)
            }
            
            if(response.status==window.hWin.ResponseStatus.SYSTEM_FATAL
            || response.status==window.hWin.ResponseStatus.SYSTEM_CONFIG){

                let def_title = window.hWin.ResponseStatus.SYSTEM_CONFIG ? 'System misconfiguration' : 'Fatal error';
                dlg_title = window.hWin.HEURIST4.util.isempty(dlg_title) ? def_title : dlg_title;
                msg = msg + '<br><br>'+window.hWin.HR('Error_System_Config');

            }else if(response.status==window.hWin.ResponseStatus.INVALID_REQUEST){

                dlg_title = window.hWin.HEURIST4.util.isempty(dlg_title) ? 'Invalid request made' : dlg_title;

                msg = msg + '<br><br>' + window.hWin.HR('Error_Wrong_Request') 
                    +'<br><br>' + error_report_team;

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
                dlg_title = window.hWin.HEURIST4.util.isempty(dlg_title) ? 'Database error' : dlg_title;
                msg = msg + error_report_team;
            }else  if(response.status==window.hWin.ResponseStatus.ACTION_BLOCKED){
                // No enough rights or action is blocked by constraints
                dlg_title = window.hWin.HEURIST4.util.isempty(dlg_title) ? 'Action blocked' : dlg_title;
            }else  if(response.status==window.hWin.ResponseStatus.NOT_FOUND){
                // The requested object not found.
                dlg_title = window.hWin.HEURIST4.util.isempty(dlg_title) ? 'Request not found' : dlg_title;
            }else if(response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                // An unknown/un-handled error
                dlg_title = window.hWin.HEURIST4.util.isempty(dlg_title) ? 'An unknown error has occurred' : dlg_title;
                msg += error_report_team;
            }
            
            if(request_code!=null){
                msg = msg + '<br>'+window.hWin.HR('Error_Report_Code')+': "'
                    +(request_code.script+' '
                    +(window.hWin.HEURIST4.util.isempty(request_code.action)?'':request_code.action)).trim()+'"';
            }
        }
        
        if(window.hWin.HEURIST4.util.isempty(msg) || msg.trim().toLowerCase() == 'error'){
            msg = window.hWin.HR('Error_Empty_Message');
        }
        if(window.hWin.HEURIST4.util.isempty(dlg_title)){
            dlg_title = 'Error_Title';
        }
        dlg_title = window.hWin.HEURIST4.util.isFunction(window.hWin.HR)?window.hWin.HR(dlg_title):'Heurist';

        let buttons = {};
        buttons[window.hWin.HR('OK')]  = function() {
                    let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                    $dlg.dialog( "close" );
                    if(show_login_dlg){
                            if(window.hWin?.HAPI4){
                                window.hWin.HAPI4.setCurrentUser(null);
                                $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS);
                            }
                    }
                }; 
                
        if(!ext_options) ext_options = {};
        //else if(!ext_options['default_palette_class']) 
        ext_options['default_palette_class'] = 'ui-heurist-error';
                
        window.hWin.HEURIST4.msg.showMsgDlg(msg, buttons, dlg_title, ext_options);
       
        return msg; 
    },

    /**
     * Loads content from a specified URL into a dialog and displays it.
     * The dialog is created using `getPopupDlg` or `getMsgDlg` based on options.
     * After loading, it calls `showMsgDlg` to configure and show the dialog.
     *
     * @memberof HEURIST4.msg
     * @param {string} url - The URL from which to load content.
     * @param {Object<string, function>|function} [buttons] - Buttons to display on the dialog, in the format expected by jQuery UI Dialog,
     *                                                        or a callback function for default Yes/No buttons. See `showMsgDlg`.
     * @param {string|Object<string, string>} [title] - The title for the dialog or an object with localized title/button labels. See `showMsgDlg`.
     * @param {Object} [options] - Additional options for the dialog.
     * @param {boolean} [options.isPopupDlg=false] - If true, uses `getPopupDlg`; otherwise, `getMsgDlg`.
     * @param {string} [options.container] - If provided with `isPopupDlg`, specifies the container for `getPopupDlg`.
     * @param {boolean} [options.use_doc_title=false] - If true, sets the dialog title from the `<title>` tag of the loaded document.
     *                                                Other options are passed to `showMsgDlg`.
     * @returns {jQuery|undefined} The jQuery object representing the dialog element, or undefined if URL is not provided.
     */
    showMsgDlgUrl: function(url, buttons, title, options){

        let $dlg;
        if(url){
            let isPopupDlg = (options && (options.isPopupDlg || options.container));
            $dlg = isPopupDlg
                            ?window.hWin.HEURIST4.msg.getPopupDlg( options.container )
                            :window.hWin.HEURIST4.msg.getMsgDlg();
            $dlg.load(url, function(){
                window.hWin.HEURIST4.msg.showMsgDlg(null, buttons, title, options);

                if(options && options.use_doc_title){
                    $dlg.dialog('option', 'title', $dlg.find('title').text());
                }
            });
        }
        return $dlg;
    },
    
    /**
     * Displays a standardized error message when a dynamic script loading fails.
     * The message informs the user about a program error and advises them to report it.
     *
     * @memberof HEURIST4.msg
     * @param {boolean} isdlg - If true, displays the message in a modal dialog (`showMsgDlg`).
     *                          If false, displays it as a temporary flash message (`showMsgFlash`).
     * @param {string} [message="this feature"] - A string to insert into the error message, indicating
     *                                            what feature encountered the loading problem.
     * @returns {void}
     */
    showMsg_ScriptFail: function( isdlg, message ){

        if(window.hWin.HEURIST4.util.isempty(message)){
            message = "this feature";
        }

        message = 
'Unfortunately we have encountered a program error. Please report this to us using ' 
+'create ticket under Help at the top right of the main screen, or via email '
+'to support@heuristnetwork.org so that we can fix it immediately.' 

+'<br><br>Please remember to tell us the context in which this occurred. '
+'A screenshot including the URL is very useful.';
        
        if(isdlg){
            window.hWin.HEURIST4.msg.showMsgDlg(message, null, "Work in Progress");    
        }else{
            window.hWin.HEURIST4.msg.showMsgFlash(message, 4000, {title:'Work in Progress',height:160});
        }
        
    },

    /**
     * Displays a dialog prompting the user for input.
     * The dialog contains a message and an input field. If the provided message
     * does not include an element with ID `dlg-prompt-value`, an input field is automatically created.
     *
     * @memberof HEURIST4.msg
     * @param {string} message - The message or HTML content to display in the prompt.
     *                           If it contains an element with `id="dlg-prompt-value"`, that element is used for input.
     *                           Otherwise, a new text input is created.
     * @param {function} callbackFunc - A function to call when the user submits the dialog (e.g., clicks OK).
     *                                  This function will be called with the value from the input field.
     * @param {string} [sTitle="Specify value"] - The title for the prompt dialog.
     * @param {Object} [ext_options] - Additional options passed to `showMsgDlg`.
     * @param {string} [ext_options.dialogId="dialog-common-messages"] - The ID of the dialog element to use.
     * @param {boolean} [ext_options.password=false] - If true, the created input field will be of type "password".
     * @returns {jQuery} The jQuery object representing the dialog element.
     */
    showPrompt: function(message, callbackFunc, sTitle, ext_options){
        
        if(message.indexOf('dlg-prompt-value')<0){
            const value = window.hWin.HEURIST4.util.htmlEscape(ext_options?.value??'');
            
            message = message+'<input id="dlg-prompt-value" class="text ui-corner-all" '
                + (ext_options?.password?'type="password"':'') 
                + ` style="max-width: 250px; min-width: 10em; width: 250px; margin-left:0.2em" value="${value}"/>`;    
                
        }

        let dlg_id = ext_options?.dialogId??'dialog-common-messages';

        return window.hWin.HEURIST4.msg.showMsgDlg( message,
        function(){ // This is the OK button callback for showMsgDlg
            if(window.hWin.HEURIST4.util.isFunction(callbackFunc)){
                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg(dlg_id);      
                let ele = $dlg.find('#dlg-prompt-value');
                let val = '';
                if(ele.attr('type')!='checkbox' || ele.is(':checked')){ // Also handles checkbox type input
                    val =  ele.val();
                }
                callbackFunc.call(this, val); // Call the user-provided callback with the value
            }
        },
        window.hWin.HEURIST4.util.isempty(sTitle)?'Specify value':sTitle, ext_options);
        
    },
    
    /**
     * Retrieves or creates the primary jQuery UI Dialog element used for common messages.
     * If the dialog element with the specified ID (default "dialog-common-messages") does not exist,
     * it is created and appended to the body.
     *
     * @memberof HEURIST4.msg
     * @param {string} [dialogId="dialog-common-messages"] - The ID of the dialog element to get or create.
     *                                                       If a leading '#' is present, it's stripped.
     * @returns {jQuery} The jQuery object representing the dialog div.
     */
    getMsgDlg: function(dialogId){

        if(window.hWin.HEURIST4.util.isempty(dialogId)){
            dialogId = "dialog-common-messages";
        }else if(dialogId[0] == '#'){
            dialogId = dialogId.slice(0, 1);
        }

        let $dlg = $( "#" + dialogId );
        if($dlg.length==0){
            $dlg = $('<div>',{id: dialogId})
                .css({'min-wdith':'380px','max-width':'640px'}) //,padding:'1.5em 1em'
                .appendTo( $('body') );
        }
        return $dlg.removeClass('ui-heurist-border');
    },

    /**
     * Retrieves or creates the jQuery UI Dialog element used for flash messages.
     * If the dialog element with ID "dialog-flash-messages" does not exist,
     * it is created and appended to the body.
     *
     * @memberof HEURIST4.msg
     * @returns {jQuery} The jQuery object representing the flash message dialog div.
     */
    getMsgFlashDlg: function(){
        let $dlg = $( "#dialog-flash-messages" );
        if($dlg.length==0){
            $dlg = $('<div>',{id:'dialog-flash-messages'}).css({'min-wdith':'380px','max-width':'640px'})
                .appendTo('body'); //$(window.hWin.document)
        }
        return $dlg.removeClass('ui-heurist-border');
    },
    
    /**
     * Retrieves or creates a generic jQuery UI Dialog element, typically used for popups that might require more space
     * or custom styling (e.g., no width limit by default).
     * If the dialog element with the specified ID (default "dialog-popup") does not exist,
     * it is created and appended to the body.
     *
     * @memberof HEURIST4.msg
     * @param {string} [element_id="dialog-popup"] - The ID of the dialog element to get or create.
     * @returns {jQuery} The jQuery object representing the popup dialog div.
     */
    getPopupDlg: function(element_id){        
        if(!element_id) element_id = 'dialog-popup';
        let $dlg = $( '#'+element_id );
        if($dlg.length==0){
            $dlg = $('<div>',{id:element_id})
                .css({'padding':'2em','min-wdith':'380px',overflow:'hidden'}).appendTo('body');
            $dlg.removeClass('ui-heurist-border');
        }
        return $dlg;
    },

    
    /**
     * Displays a jQuery UI tooltip associated with a given element for a specified duration.
     * The tooltip content is taken from the `message` parameter.
     *
     * @memberof HEURIST4.msg
     * @param {string} message - The message to display in the tooltip. This will be translated using `window.hWin.HR`.
     * @param {number} [timeout=1000] - The duration in milliseconds for which the tooltip should be visible. Defaults to 1000ms.
     * @param {jQuery|HTMLElement|Object} to_element - The target element for the tooltip.
     *                                               Can be a jQuery object, DOM element, or an object specifying
     *                                               `{of: targetElement, my: "...", at: "..."}` for custom positioning.
     *                                               If not an object, default positioning is "left top" at "left bottom".
     * @returns {void}
     */
    showTooltipFlash: function(message, timeout, to_element){
        
        if(!window.hWin.HEURIST4.util.isFunction(window.hWin.HR)){
            alert(message); // Fallback if HR (translation) function is not available
            return;
        }
        
        if(window.hWin.HEURIST4.util.isempty(message) ||  window.hWin.HEURIST4.util.isnull(to_element)){
            return;   
        }
        
        let position;
        
        if($.isPlainObject(to_element)){ // Custom position provided
                position = { my:to_element.my, at:to_element.at};
                to_element =  to_element.of;
        }else{ // Default position relative to the element
                position = { my: "left top", at: "left bottom", of: $(to_element) };    
        }

        if (!(timeout>200)) {
            timeout = 1000;
        }
        
        $( to_element ).attr('title',window.hWin.HR(message)); // Set title attribute for jQuery UI Tooltip
        $( to_element ).tooltip({
            position: position,
            // content: '<span>'+window.hWin.HR(message)+'</span>', // Alternative way to set content if needed
            hide: { effect: "explode", duration: 500 } // Optional hide effect
        });

        $( to_element ).tooltip('open'); // Open the tooltip
        
        setTimeout(function(){ // Set timeout to close and clean up tooltip
            $( to_element ).tooltip('close');
            $( to_element ).attr('title',null); // Remove title to prevent native tooltip
        }, timeout);
        
    },

    /**
     * Displays a buttonless dialog (flash message) that automatically closes after a specified timeout.
     * The dialog's content is the provided message. Styling and positioning can be customized.
     *
     * @memberof HEURIST4.msg
     * @param {string|null} message - The message to display. If null, the function returns early.
     *                                This message will be translated using `window.hWin.HR`.
     * @param {number|boolean} [timeout=1000] - Duration in milliseconds before the dialog auto-closes.
     *                                         If `false`, the dialog will not auto-close. Defaults to 1000ms.
     * @param {Object|string} [options] - Configuration options for the dialog.
     *                                    If a string, it's treated as the dialog title.
     *                                    If an object, can include:
     * @param {string} [options.title] - Title for the dialog. If null, title bar is hidden.
     * @param {number} [options.height] - Height of the dialog. If not set, auto-calculated.
     *                                    Other jQuery UI dialog options can also be included.
     * @param {jQuery|HTMLElement|Object} [position_to_element] - Element or position object to position the dialog relative to.
     *                                                          If an object, e.g., `{ my: "center", at: "center", of: window }`.
     *                                                          Defaults to centering on the document.
     * @returns {void}
     */
    showMsgFlash: function(message, timeout, options, position_to_element){

        if(!window.hWin.HEURIST4.util.isFunction(window.hWin.HR)){
            alert(message); // Fallback if HR (translation) function is not available
            return;
        }

        if(!$.isPlainObject(options)){
             options = {title:options};
        }
        
        let $dlg = window.hWin.HEURIST4.msg.getMsgFlashDlg();

        if(message==null){
            return;
        }

        $dlg.empty();
        let content = $('<span>'+window.hWin.HR(message)+'</span>')
                    .css({'overflow':'hidden','font-weight':'bold','font-size':'1.2em'});
                    
        $dlg.append(content);
        
        let hideTitle = (options.title==null);
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
                options.position = position_to_element.position;
           }else{
                options.position = { my: "left top", at: "left bottom", of: $(position_to_element) };    
           } 
        }else{
            options.position = { my: "center center", at: "center center", of: $(document) };    
        }
        options.position.collision = 'none'; //FF fix

        $dlg.dialog(options);
        
        if(!(options.height>0)){
            let height = $(content).height()+90;
           
            $dlg.dialog({height:height});
        }
           
        
        content.position({ my: "center center", at: "center center", of: $dlg });
        
        if(hideTitle){
            $dlg.parent().find('.ui-dialog-titlebar').hide();
        }
    
        $dlg.parent().css({background: '#7092BE', 'border-radius': "6px", 'border-color': '#7092BE !important',
                    'outline-style':'none', outline:'none'})
        $dlg.css({color:'white', border:'none', overflow:'hidden' });
        
        if(timeout!==false){

            if (!(timeout>200)) {
                timeout = 1000;
            }
            setTimeout(window.hWin.HEURIST4.msg.closeMsgFlash, timeout);
            
        }
    },
    
    /**
     * Closes the flash message dialog (`#dialog-flash-messages`).
     * It also ensures the title bar is shown if it was previously hidden.
     * This function respects a `coverallKeep` flag; if true, it won't close the dialog.
     *
     * @memberof HEURIST4.msg
     * @returns {void}
     */
    closeMsgFlash: function(){
        
        if(window.hWin.HEURIST4.msg.coverallKeep===true) return;
        
        let $dlg = window.hWin.HEURIST4.msg.getMsgFlashDlg();
        if($dlg.dialog('instance')) $dlg.dialog('close');
        $dlg.parent().find('.ui-dialog-titlebar').show(); // Ensure title bar is visible for next time
    },

    /**
     * Displays a simple error dialog with the given message and an "Error" title.
     * This is a convenience wrapper around `showMsgDlg`.
     *
     * @memberof HEURIST4.msg
     * @param {string} message - The error message to display.
     * @returns {void}
     * @todo The name `redirectToError` is misleading as it doesn't actually redirect. Consider renaming or clarifying its purpose.
     */
    redirectToError: function(message){
        window.hWin.HEURIST4.msg.showMsgDlg(message, null, 'Error');
    },

    /**
     * Checks if the length of an input field's value is within a specified range [min, max].
     * If the length is outside the range, it displays a flash error message.
     * 
     * Uses `checkLength2` to get the error message text and add error class.
     *
     * @memberof HEURIST4.msg
     * @param {jQuery} input - jQuery object representing the input element.
     * @param {string} title - A title or name for the input field, used in the error message (e.g., "Username").
     * @param {string|jQuery|null} message - If a jQuery object, its text is updated with the error message
     *                                     and 'ui-state-error' class is added/removed (this part is commented out).
     *                                     If a string, this custom error message is used for the flash display,
     *                                     overriding the default generated by `checkLength2`.
     *                                     If null or empty, `checkLength2`'s message is used.
     * @param {number} min - The minimum allowed length for the input value.
     * @param {number} max - The maximum allowed length for the input value. Use 0 or negative for no max limit.
     * @returns {boolean} True if the length is valid, false otherwise.
     */
    checkLength: function( input, title, message, min, max ) {
        let message_text = window.hWin.HEURIST4.msg.checkLength2( input, title, min, max );
        if(message_text!=''){
            // Use custom message string if provided
            if(!window.hWin.HEURIST4.util.isempty(message) && typeof message === 'string') message_text = message; 
             
            window.hWin.HEURIST4.msg.showMsgFlash('<span style="padding:10px;border:0;">'+message_text+'</span>', 3000);
            
            return false;
        }else{
            return true;
        }

    },

    /**
     * Generates an error message if an input field's value length is outside a specified range.
     * It also adds or removes the "ui-state-error" class from the input field based on validity.
     *
     * @memberof HEURIST4.msg
     * @param {jQuery} input - jQuery object representing the input element.
     * @param {string} title - A title or name for the input field, used in constructing the error message (e.g., "Username").
     * @param {number} min - The minimum allowed length.
     * @param {number} max - The maximum allowed length. If 0 or negative, no maximum is enforced by this part of the logic,
     *                       but the message might still reflect "between min and max" if min > 1.
     * @returns {string} An error message string if the length is invalid, or an empty string if valid.
     */
    checkLength2: function( input, title, min, max ) {

        let len = input.val().length;
        let message_text = '';

        if ( (max>0 &&  len > max) || len < min ) {
            input.addClass( "ui-state-error" );
            if(max>0 && min>1){
                message_text = window.hWin.HR(title)+" "+window.hWin.HR("length must be between ") +
                min + " "+window.hWin.HR("and")+" " + max + ". ";
                if(len>=min){ // This condition seems off if len > max, it will always be true.
                              // The message should probably distinguish between too short and too long.
                    message_text = message_text + (len-max) + window.hWin.HR(" characters over");
                }

            }else if(min>1){ // Only min length is specified (or max <= 0)
               message_text = window.hWin.HR(title)+" "+window.hWin.HR('. At least '+min+' characters required'); 
            }else if(min==1){ // Required field (min length 1)
                message_text = window.hWin.HR(title)+" "+window.hWin.HR(" is required field");
            }
            // If only max is specified (min is 0 or 1, and it's too long), current logic doesn't explicitly state "too long".
            // It might fall into the "length must be between" if min > 1, or no specific message if min <=1.

            return message_text;

        } else {
            input.removeClass( "ui-state-error" );
            return '';
        }
    },

    
    /**
    * Displays a URL content within an iframe embedded in a jQuery UI popup dialog.
    * This function is highly configurable through the `options` parameter.
    * It handles creating the dialog, loading the iframe, and setting up communication
    * (e.g., overriding `alert`, providing `close` and `doDialogResize` functions to the iframe content).
    *
    * @memberof HEURIST4.msg
    * @param {string} url - The URL to load into the iframe. If empty, behavior might be undefined or rely on other options.
    * @param {Object} [options] - Configuration options for the dialog and iframe.
    * @param {string} [options.dialogid] - A unique ID for the dialog. If provided, the dialog div is reused/reopened
    *                                      instead of being removed on close.
    * @param {boolean} [options.force_reload=false] - If `dialogid` is used and the dialog already exists,
    *                                                force iframe content to reload even if URL is the same.
    * @param {jQuery} [options.container] - If provided, uses `showDialogInDiv` to render the dialog inline within this container.
    * @param {string} [options.title=''] - Title for the dialog.
    * @param {Window} [options.window=window] - The opener window object.
    * @param {Object} [options.params] - Parameters to pass to the iframe's `assignParameters` function, if it exists.
    * @param {function} [options.onpopupload] - Callback function executed after the iframe content has loaded.
    *                                          Receives the iframe DOM element as an argument.
    * @param {function} [options.callback] - Callback function executed when the iframe attempts to close (e.g., via `contentWindow.close()`).
    *                                        If it returns `false`, the dialog closing is prevented.
    * @param {function} [options.afterclose] - Callback function executed after the dialog has been closed.
    * @param {boolean} [options.modal=true] - Whether the dialog should be modal.
    * @param {function} [options.onmouseover] - Event listener for mouseover on the dialog title bar or iframe.
    * @param {string} [options.default_palette_class] - A CSS class to apply to the dialog for theming.
    * @param {string} [options.padding] - CSS padding for the dialog content area.
    * @param {string} [options.padding-content] - CSS padding for the direct ui-dialog-content parent of the iframe.
    * @param {boolean} [options.allowfullscreen=false] - If true, adds `allowfullscreen` attributes to the iframe.
    * @param {boolean} [options.noClose=false] - If true, hides the close button on the dialog title bar.
    * @param {boolean} [options.borderless=false] - If true, removes dialog border and title bar.
    * @param {string|number} [options.width='640'] - Initial width of the dialog.
    * @param {string|number} [options.height='480'] - Initial height of the dialog.
    * @param {boolean} [options.resizable=true] - Whether the dialog is resizable.
    * @param {boolean} [options.draggable=true] - Whether the dialog is draggable.
    * @param {boolean} [options.closeOnEscape=true] - Whether to close the dialog on ESC key.
    * @param {function} [options.beforeClose] - Callback function executed before the dialog closes.
    * @param {function} [options.onOpen] - Callback function executed when the dialog opens.
    * @param {string} [options.class] - Additional CSS class for the dialog main div.
    * @param {boolean} [options.is_h6style=false] - If true, applies Heurist 6 specific styling and layout adjustments.
    * @param {Object} [options.position] - jQuery UI position object for dialog placement.
    * @param {boolean} [options.maximize=false] - If true (and not `is_h6style`), attempts to maximize the dialog.
    * @param {string} [options.coverMsg] - Message to display on the loading coverall while iframe loads.
    * @returns {void} (The function primarily has side effects by creating and showing a dialog).
    *                 If `options.container` is provided, it effectively calls `showDialogInDiv` which also returns void.
    *                 The created dialog jQuery object is not directly returned.
    */
    showDialog: function(url, options){

        if(!options) options = {};

        if(options.container){
            window.hWin.HEURIST4.msg.showDialogInDiv(url, options);
            return;
        }


        if(!options.title) options.title = ''; // removed 'Information'  which is not a particualrly useful title

        let opener = options['window']?options['window'] :window;

        //.appendTo( that.document.find('body') )
        let $dlg = [];

        if(options['dialogid']){
            $dlg = $(opener.document).find('body #'+options['dialogid']);
        }
        
        
        let $dosframe;
        
        
        function __canAccessIframe(iframe) {
          try {
            return Boolean(iframe.contentDocument);
          }
          catch(e){
            return false;
          }
        }        

        if($dlg.length>0){
            //reassign dialog onclose and call new parameters
            
            $dlg.dialog('open');  
            
            $dosframe = $dlg.find('iframe');
            if(__canAccessIframe($dosframe[0]))
            {
                let content = $dosframe[0].contentWindow;
                
                //close dialog from inside of frame - need redifine each time
                content.close = function() {
                    let rval = true;
                    let closeCallback = options['callback'];
                    if(window.hWin.HEURIST4.util.isFunction(closeCallback)){
                        rval = closeCallback.apply(opener, arguments);
                    }
                    if ( rval===false ){ //!rval  &&  rval !== undefined){
                        return false;
                    }
                    $dlg.dialog('close');
                    return true;
                };

                // if content in iframe has function "assignParameters" we may pass parameters
                if(options['params'] && window.hWin.HEURIST4.util.isFunction(content.assignParameters)) {
                    content.assignParameters(options['params']);
                }
            }
            if(options['height']>0 && options['width']>0){
                 $dlg.dialog('option', 'width', options['width']);    
                 $dlg.dialog('option', 'height', options['height']);    
            }

            if($dosframe.attr('src')!=url || options['force_reload']){ // hide previous content
                $dosframe.hide();
                $dlg.addClass('loading');
            }

        }else{

           
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
            .css({border:'none', overflow: 'none !important', width:'100% !important'}).appendTo( $dlg );
            
            if(options['allowfullscreen']){
                $dosframe.attr('allowfullscreen',true);
                $dosframe.attr('webkitallowfullscreen',true);
                $dosframe.attr('mozallowfullscreen',true);
                
               
            }

            $dosframe.hide();
            //callback function to resize dialog from internal frame functions
            $dosframe[0].doDialogResize = function(width, height) {
                
                /*
                let body = $(this.document).find('body');
                let dim = { h: Math.max(400, body.innerHeight()-10), w:Math.max(400, body.innerWidth()-10) };

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

                window.hWin.HEURIST4.msg.sendCoverallToBack();

                let has_access = __canAccessIframe($dosframe[0]);
                
                if(has_access)
                {
                    
                    let content = $dosframe[0].contentWindow;
                    try{
                        //replace standard "alert" to Heurist dialog    
                        content.alert = function(txt){
                            let $dlg_alert = window.hWin.HEURIST4.msg.showMsgDlg(txt, null, ""); // Title was an unhelpful and inelegant "Info"
                            $dlg_alert.dialog('open');
                            return true;
                        }
                    }catch(e){
                        console.error(e);
                    }

                    if(!options["title"]){
                        $dlg.dialog( "option", "title", content.document.title );
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

                   
                   
                    //functions in internal document
                    //content.close = $dosframe[0].close;    // make window.close() do what we expect

                    //close dialog from inside of frame
                    content.close = function() {
                        let rval = true;
                        let closeCallback = options['callback'];
                        if(window.hWin.HEURIST4.util.isFunction(closeCallback)){
                            rval = closeCallback.apply(opener, arguments);
                        }
                        if ( rval===false ){ //!rval  &&  rval !== undefined){
                            return false;
                        }
                        $dlg.dialog('close');
                        return true;
                    };

                   
                    content.doDialogResize = $dosframe[0].doDialogResize;

                }
                
                $dlg.removeClass('loading');
                $dosframe.show();    

                let onloadCallback = options['onpopupload'];
                if(onloadCallback){
                    onloadCallback.call(opener, $dosframe[0]);
                }

                if(has_access){
                    let content = $dosframe[0].contentWindow;

                    if(window.hWin.HEURIST4.util.isFunction(content.onFirstInit)) {  //see mapPreview
                        content.onFirstInit();
                    }
                    //pass parameters to frame 
                    if(options['params'] && window.hWin.HEURIST4.util.isFunction(content.assignParameters)) {
                        content.assignParameters(options['params']);
                    }
                }
                
                if(options['onmouseover']){ 
                    $dlg.parent().find('.ui-dialog-titlebar').on('mouseover', function(){
                        options['onmouseover'].call();
                    });
                    $dosframe.on('mouseover', function(){
                        options['onmouseover'].call();
                    });
                }
                

            });

            options.width = window.hWin.HEURIST4.msg._setDialogDimension(options, 'width');
            options.height = window.hWin.HEURIST4.msg._setDialogDimension(options, 'height');
         
            let opts = {
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
                    let closeCallback = options['afterclose'];
                    if(window.hWin.HEURIST4.util.isFunction(closeCallback)){
                        closeCallback.apply();
                    }
                    if(!options['dialogid']){
                        $dlg.remove();
                    }
                },
                open: options.onOpen
            };
            $dlg.dialog(opts);
            
            if($dlg.attr('data-palette'))
                $dlg.parent().removeClass($dlg.attr('data-palette'));
            if(options.default_palette_class){
                $dlg.attr('data-palette', options.default_palette_class);
                $dlg.parent().addClass(options.default_palette_class);
            }else{
                $dlg.attr('data-palette', null);
            }
            
            $dlg.parent().find(".ui-dialog-title").html(options["title"]);
            $dlg.parent().find('.ui-dialog-content').css({'overflow':'hidden'});

            if(options.noClose){
                $dlg.parent().find('.ui-dialog-titlebar').find('.ui-icon-closethick').parent().hide();
            }


            if(!window.hWin.HEURIST4.util.isempty(options['padding'])){ //by default 2em
                $dlg.css('padding', options.padding);
            }
            if(!window.hWin.HEURIST4.util.isempty(options['padding-content'])){ 
                $dlg.parent().find('.ui-dialog-content').css('padding', options['padding-content']);
            }
            
            if(!options.is_h6style && options.maximize){
                        function __maximizeOneResize(){
                            let dialog_height = window.innerHeight;
                            $dlg.dialog( 'option', 'height', dialog_height);
                            $dlg.dialog( 'option', 'width', '100%'); //dialog_width
                        }
                        __maximizeOneResize();
            }     
            if(options.borderless){
                $dlg.css('padding',0);
                $dlg.parent() //s(".ui-dialog")
                      .css("border", "0 none")
                      .find(".ui-dialog-titlebar").remove();
            }

        }


        if(options.is_h6style)
        {

            $dlg.parent().addClass('ui-dialog-heurist ui-heurist-explore');

            if(options.container){

                $dlg.dialog( 'option', 'position', 
                    { my: "left top", at: "left top", of:options.container, collision:'none'});

                function __adjustOneResize(e){
                    let ele = e ?$(e.target) :options.container;

                    let dialog_height = ele.height(); 
                    $dlg.dialog( 'option', 'height', dialog_height);
                    let dialog_width = ele.width(); 
                    $dlg.dialog( 'option', 'width', dialog_width);
                }
                //$(window).on('onresize',__adjustOneResize)
                options.container.off('resize');
                options.container.on('resize', __adjustOneResize);
                __adjustOneResize();

            }else
                if(options.position){
                    $dlg.dialog( 'option', 'position', options.position );   
                    if(options.maximize){
                        function __maximizeOneResize(){
                            let dialog_height = window.innerHeight - $dlg.parent().position().top - 5;
                            $dlg.dialog( 'option', 'height', dialog_height);
                            let dialog_width = window.innerWidth - $dlg.parent().position().left - 5;
                            $dlg.dialog( 'option', 'width', dialog_width);
                        }
                        __maximizeOneResize();
                    }else{
                        if($dlg.parent().position().left<0){
                            $dlg.parent().css({left:0});
                        }else{
                            let max_width = window.innerWidth - $dlg.parent().position().left - 5;
                            let dlg_width = $dlg.dialog( 'option', 'width');
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
        if(url!='' && ($dosframe.attr('src')!=url || options['force_reload'])){
            if(options.coverMsg){
                window.hWin.HEURIST4.msg.bringCoverallToFront($dlg, {'font-size': '16px', color: 'white'}, options.coverMsg); 
            }
            $dosframe.attr('src', url);
        }

    },

    /**
     * Displays URL content within an iframe embedded directly (inline) into a specified container element,
     * styled according to Heurist 6 UI guidelines. This is an alternative to popup dialogs.
     *
     * @memberof HEURIST4.msg
     * @param {string} url - The URL to load into the iframe.
     * @param {Object} [options] - Configuration options.
     * @param {jQuery} options.container - The jQuery object of the container element where the iframe will be placed.
     * @param {string} [options.title='&nbsp;'] - Title to display in a header bar above the iframe content.
     *                                            If empty, the header bar might be hidden or take less space.
     * @param {string} [options.context_help] - URL for a context-sensitive help page, linked via an info button in the header.
     * @param {boolean} [options.show_help_on_init=true] - If `context_help` is provided, whether to open the help panel immediately.
     * @param {Object} [options.params] - Parameters to pass to the iframe's `assignParameters` function.
     * @param {function} [options.doDialogResize] - A function to be made available to the iframe content for resizing itself.
     * @param {function} [options.onContentLoad] - Callback executed after iframe content loads. Receives iframe DOM element.
     * @param {function} [options.beforeClose] - Callback executed before the container/dialog is hidden or closed.
     *                                          If it returns `false`, closing is prevented.
     * @param {function} [options.afterClose] - Callback executed after the container/dialog is hidden or closed.
     * @param {string} [options.padding] - CSS padding for the main container.
     * @returns {jQuery|undefined} The jQuery object of the iframe if it's reused, otherwise undefined.
     *                             The function primarily modifies the DOM and sets up event handlers.
     */
    showDialogInDiv: function(url, options){

            if(!options) options = {};

            if(!options.title) options.title = '&nbsp;';
            
            let $container = options['container'];
            
            let $dosframe = $container.find('iframe');
            let _innerTitle = $container.children('.ui-heurist-header');
            let frame_container = $container.children('.ent_content_full');
            let $info_button;
               
            if($dosframe.length==0)
            {
                $container.empty();    
                //add h6 style header    
                _innerTitle = $('<div>').addClass('ui-heurist-header')
                        .appendTo($container);

                frame_container = $('<div>').addClass('ent_content_full')
                        .css({'top':'37px','bottom':'1px', 'overflow':'hidden'})
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
                
                let canClose = true;
                if(window.hWin.HEURIST4.util.isFunction(options['beforeClose'])){
                    canClose = options['beforeClose'].call( $dosframe[0], arguments );
                }
                if(canClose===false){
                    return false;
                }else{
                    $container.hide();
                    if(window.hWin.HEURIST4.util.isFunction(options['afterClose'])){
                        canClose = options['afterClose'].call( $dosframe[0], arguments );
                    }
                    return true;
                }
            };

            //init close button     
            $('<button>').button({icon:'ui-icon-closethick',showLabel:false, title:'Close'}) 
                                    //classes:'ui-corner-all ui-dialog-titlebar-close'})
                     .css({'position':'absolute', 'right':'4px', 'top':'6px', height:24, width:24})
                     .appendTo(_innerTitle)
                     .on({click:function(){
                         __onDialogClose();
                     }});
                     
            //init help button     
            if( options['context_help'] && window.hWin.HEURIST4.ui ){
                    
                    $info_button = $('<button>')
                            .button({icon:'ui-icon-circle-help', showLabel:false, label:'Help'})
                            .addClass('ui-helper-popup-button')
                            .css({'position':'absolute', 'right':'34px', 'top':'6px', height:24, width:24})
                            .appendTo(_innerTitle);
                    
                    window.hWin.HEURIST4.ui.initHelper({
                            button:$info_button, 
                            url: window.hWin.HRes(options['context_help']),
                            position:{my:'right top', at:'right top', of:$container},
                            container: $container,
                            is_open_at_once: options['show_help_on_init']===false ? false : true
                    });
            }else{
                //hide helper div
                frame_container.css({right:1});
                $container.children('.ui-helper-popup').hide();
            }
                
            if(navigator.userAgent.indexOf('Firefox')<0){            
                $dosframe.hide();
            }
            
            frame_container.addClass('loading');
            
            //callback function to resize dialog from internal iframe functions
           

            $dosframe.off('load');
            //on load content event listener
            $dosframe.on('load', function(){
                         
                if(window.hWin.HEURIST4.util.isempty($dosframe.attr('src'))){
                    return;
                }
                    
                let content = $dosframe[0].contentWindow;
                
                //replace native alert 
                try{
                    content.alert = function(txt){
                        let $dlg_alert = window.hWin.HEURIST4.msg.showMsgDlg(txt, null, ""); // Title was an unhelpful and inelegant "Info"
                        $dlg_alert.dialog('open');
                        return true;
                }
                }catch(e){
                    console.error(e);
                }
                
                frame_container.removeClass('loading');
                $dosframe.show();    
                
                //close dialog from inside of frame
                content.close = __onDialogClose;
                
               
                if(window.hWin.HEURIST4.util.isFunction(options['doDialogResize'])){
                    content.doDialogResize = options['doDialogResize'];
                } 
                if(window.hWin.HEURIST4.util.isFunction(options['onContentLoad'])){
                    options['onContentLoad'].call(this, $dosframe[0]);
                } 
                       
                //pass params into iframe
                if(options['params'] && window.hWin.HEURIST4.util.isFunction(content.assignParameters)) {
                    content.assignParameters(options['params']);
                }
                
            }); //onload

                        
            if(!window.hWin.HEURIST4.util.isempty(options['padding'])){
                //by default 2em
                $container.css('padding', options.padding);
            } 
                    
            //start content loading
            $dosframe.attr('src', url);
                        
           
    },   
    
    //
    //
    //
    _setDialogDimension: function(options, axis){
        
            let opener = options['window']?options['window'] :window;
        
            let wp = 0;
            
            if(axis=='width'){
                wp = (opener && opener.innerWidth>0)? opener.innerWidth
                    :(window.hWin?window.hWin.innerWidth:window.innerWidth);
            }else{
                wp = (opener && opener.innerHeight>0)? opener.innerHeight
                    :(window.hWin?window.hWin.innerHeight:window.innerHeight);
            }
            
            let res;

            if(typeof options[axis]==='string'){
                let isPercent = (options[axis].indexOf('%')>0);
                
                res = parseInt(options[axis], 10);
                
                if(isPercent){
                    res = wp*res/100;
                }
            }else{
                res = options[axis];
            }
            
            if(isNaN(res) || res<100){
                res = (axis=='width')?640:480;
            } 
            
            if(res > wp){
                res = wp;
            }
            
            return res;
        
    },
    
    /**
     * Displays an existing DOM element within a jQuery UI popup dialog.
     * The element is temporarily detached from its original position in the DOM,
     * shown in the dialog, and then re-appended to its original parent when the dialog is closed.
     * The dialog itself is removed from the DOM after closing, unless `onCloseCalback` returns false.
     *
     * @memberof HEURIST4.msg
     * @param {Object} options - Configuration options for the dialog.
     * @param {HTMLElement} options.element - The DOM element to display in the dialog.
     * @param {Window} [options.window=window] - The window object where the dialog will be created.
     * @param {string|number} [options.width='640'] - Initial width of the dialog.
     * @param {string|number} [options.height='480'] - Initial height of the dialog.
     * @param {boolean} [options.autoOpen=true] - Whether to open the dialog immediately.
     * @param {boolean} [options.modal=true] - Whether the dialog should be modal.
     * @param {boolean} [options.resizable=true] - Whether the dialog is resizable.
     * @param {string} [options.title] - Title for the dialog.
     * @param {Object<string, function>} [options.buttons] - Buttons for the dialog.
     * @param {function} [options.open] - Callback executed when the dialog opens.
     * @param {function} [options.beforeClose] - Callback executed before the dialog closes.
     * @param {function} [options.close] - Callback executed after the dialog closes and the element is reattached.
     *                                     If this callback returns `false`, the dialog div is not removed from DOM.
     * @param {Object} [options.position] - jQuery UI position object for dialog placement.
     * @param {boolean} [options.borderless=false] - If true, removes dialog border and title bar.
     * @param {string} [options.default_palette_class] - A CSS class to apply to the dialog for theming.
     * @param {string} [options.h6style_class] - Additional class for Heurist 6 styling.
     * @returns {jQuery} The jQuery object representing the created dialog.
     */
    showElementAsDialog: function(options){

            let opener = options['window']?options['window'] :window;

            let $dlg = $('<div>')
               .appendTo( $(opener.document).find('body') );

            let element = options['element'];
            let originalParentNode = element.parentNode;
            originalParentNode.removeChild(element);

            $(element).show().appendTo($dlg);

            let dimW = window.hWin.HEURIST4.msg._setDialogDimension(options, 'width');
            let dimH = window.hWin.HEURIST4.msg._setDialogDimension(options, 'height');
            
            let onCloseCalback = (options['close'])?options.close:null;
            
            let opts = {
                    autoOpen:(options['autoOpen']!==false),
                    width : dimW,
                    height: dimH,
                    modal: (options.modal!==false),
                    resizable: (options.resizable!==false),
                    //draggable: false,
                    title: options["title"],
                    buttons: options["buttons"],
                    open: options.open,  //callback
                    beforeClose: options.beforeClose,
                    close: function(event, ui){

                        let need_remove = true;                        
                        if(window.hWin.HEURIST4.util.isFunction(onCloseCalback)){
                             need_remove = onCloseCalback.call(this, event, ui);
                        }
                        
                        if(need_remove!==false){
                            element.parentNode.removeChild(element);
                            element.style.display = "none";
                            originalParentNode.appendChild(element);

                            $dlg.remove();
                        }
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

    /**
     * Creates an HTML string for a styled alert message div.
     * The div includes an alert icon and the provided message, styled with "ui-state-error".
     *
     * @memberof HEURIST4.msg
     * @param {string} msg - The message content for the alert.
     * @returns {string} HTML string representing the alert div.
     */
    createAlertDiv: function(msg){
        
        return '<div class="ui-state-error" style="width:90%;margin:auto;margin-top:10px;padding:10px;">'+
                                '<span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>'+
                                msg+'</div>';
    },
    
    /**
     * Displays a "coverall" overlay, typically used as a loading indicator that blocks UI interaction.
     * It creates or reuses a div element, styles it, sets a message, and appends it to a specified element or body.
     *
     * @memberof HEURIST4.msg
     * @param {jQuery|HTMLElement} [ele=jQuery('body')] - The element to append the coverall to. Defaults to document body.
     * @param {Object} [styles] - CSS styles to apply to the coverall div.
     *                            Defaults to an opacity, background color, text color, font size, and font weight.
     * @param {string} [message="Loading Content..."] - The message to display within the coverall.
     *                                                 Translated using `window.hWin.HR` if available.
     * @returns {void}
     */
    bringCoverallToFront: function(ele, styles, message) {
        if (!  window.hWin.HEURIST4.msg.coverall ) {
            window.hWin.HEURIST4.msg.coverall = 
                $('<div><div class="internal_msg" style="position: absolute;top: 30px;left: 30px;">'
                        +'</div></div>').addClass('coverall-div')
                .css({
                    'zIndex': 60000, // Higher zIndex to cover most elements
                    'font-size': '1.2em'
                });
        }else{
            window.hWin.HEURIST4.msg.coverall.detach(); // Detach if exists to re-append later, ensuring it's on top or in new context
        }
        
        if(!message){
            message = 'Loading Content';
            message = (window.hWin.HEURIST4.util.isFunction(window.hWin.HR)?window.hWin.HR(message):message)+'...';
        }    
        window.hWin.HEURIST4.msg.coverall.find('.internal_msg').html(message);
        
        let $appendTo = ele ? $(ele) : $('body');
        if($appendTo.find('.coverall-div').length==0) { // Append only if not already there (e.g. if ele is specific)
            window.hWin.HEURIST4.msg.coverall.appendTo($appendTo);
        }
        
        if(!styles){
            styles = {opacity: '0.6', 'background-color': "rgb(0, 0, 0)", color: 'rgb(0, 190, 0)', 'font-size': '20px', 'font-weight': 'bold'};
        }
        window.hWin.HEURIST4.msg.coverall.css( styles );
        
        $(window.hWin.HEURIST4.msg.coverall).show();
    },    
    
    /**
     * Hides the "coverall" overlay.
     * This function respects a `coverallKeep` flag; if true, it won't hide the overlay unless `force_close` is also true.
     *
     * @memberof HEURIST4.msg
     * @param {boolean} [force_close=false] - If true, forces the `coverallKeep` flag to false, ensuring the overlay is hidden.
     * @returns {void}
     */
    sendCoverallToBack: function(force_close) {
        if(force_close===true) window.hWin.HEURIST4.msg.coverallKeep = false;
        if(window.hWin.HEURIST4.msg.coverallKeep===true) return; // Don't hide if explicitly kept
        if(window.hWin.HEURIST4.msg.coverall) $(window.hWin.HEURIST4.msg.coverall).hide(); // Hide only if coverall exists
    },
  

    /**
     * A simplified method to show a message dialog. It's a wrapper around `showMsgDlg`.
     * This function is intended for displaying a simple message with default "OK" button behavior.
     *
     * @memberof HEURIST4.msg
     * @param {string|jQuery|Object} message - The message content to display. Can be a string, HTML, or jQuery object.
     * @param {Object} [options] - Additional options passed directly to `showMsgDlg`.
     *                             These options can customize aspects like title, buttons (though typically not used here),
     *                             positioning, and styling. See `showMsgDlg` for more details on `ext_options`.
     * @returns {jQuery} The jQuery object representing the dialog element.
     */
    showMsg: function(message, options){
        return window.hWin.HEURIST4.msg.showMsgDlg(message, null, null, options )
    },
    
    /**
     * Displays a general-purpose jQuery UI dialog with a message and customizable buttons.
     * This is a core function for creating various types of dialogs (info, confirmation, error).
     *
     * @memberof HEURIST4.msg
     * @param {string|jQuery|Object|null} message - The content to display in the dialog.
     *                                            Can be a string (HTML is allowed), a jQuery object, or a DOM element.
     *                                            If null, the dialog is created/opened, but content must be set separately.
     * @param {Object<string, function>|function} [buttons] - Defines the buttons for the dialog.
     *                                                        - If an object: Keys are button labels (will be translated by `HR`),
     *                                                          values are click handler functions.
     *                                                        - If a function: Assumed to be a callback for a default "Yes"/"No" button set.
     *                                                          "Yes" executes the callback, "No" closes the dialog.
     *                                                        - If null/undefined: A default "OK" button is shown which closes the dialog.
     * @param {string|Object<string, string>} [labels] - Custom labels for title and buttons.
     *                                                 - If a string: Used as the dialog title (will be translated by `HR`).
     *                                                 - If an object: Can contain `title`, `yes`, `no`, `ok`, `cancel` properties
     *                                                   for localized strings (will be translated by `HR`).
     *                                                   Default title is "·" (was 'Info').
     * @param {Object} [ext_options] - Extended options for jQuery UI Dialog and custom behavior.
     * @param {string} [ext_options.default_palette_class] - CSS class for dialog theming.
     * @param {Object} [ext_options.position] - jQuery UI position object. Defaults to center of window.
     *                                          Can also be specified via `my`, `at`, `of` properties directly in `ext_options`.
     * @param {jQuery} [ext_options.container] - If provided, uses `getPopupDlg` with this container for dialog creation.
     * @param {boolean} [ext_options.isPopupDlg=false] - If true (or `container` is set), uses `getPopupDlg`.
     * @param {string} [ext_options.dialogId="dialog-common-messages"] - ID for the dialog element.
     * @param {boolean} [ext_options.removeOnClose=false] - If true, the dialog DOM element is removed when closed.
     * @param {boolean} [ext_options.hideTitle=false] - If true, hides the dialog title bar.
     * @param {number|string} [ext_options.width='auto'] - Dialog width. For non-popup, defaults to 'auto'. For popups, defaults to 705.
     * @param {number|string} [ext_options.height] - Dialog height. For popups, defaults to 515.
     * @param {boolean} [ext_options.resizable=false] - Whether the dialog is resizable. (Popup default: true).
     * @param {boolean} [ext_options.modal=true] - Whether the dialog is modal.
     * @returns {jQuery} The jQuery object representing the dialog element.
     */
    showMsgDlg: function(message, buttons, labels, ext_options){

        if(!window.hWin.HEURIST4.util.isFunction(window.hWin.HR)){ // Fallback if HR (translation) function is not available
            alert(message);
            return;
        }
        
        if(!ext_options) ext_options = {};
        
        
        if(ext_options['buttons']){
            buttons = ext_options['buttons'];
            delete ext_options['buttons'];
        }
        if(ext_options['labels']){
            labels = ext_options['labels'];
            delete ext_options['labels'];
        }
        
        let isPopupDlg = (ext_options.isPopupDlg || ext_options.container);
        let dialogId = (!ext_options.dialogId) ? 'dialog-common-messages' : ext_options.dialogId;

        let $dlg = isPopupDlg  //show popup in specified container
                    ?window.hWin.HEURIST4.msg.getPopupDlg(ext_options.container)
                    :window.hWin.HEURIST4.msg.getMsgDlg(dialogId);

        if(message!=null){
            
            let isobj = (typeof message ===  "object");

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

        let title = '·', // 'Info' removed - it's a useless popup window title, better to have none at all
            lblYes = window.hWin.HR('Yes'),
            lblNo =  window.hWin.HR('No'),
            lblOk = window.hWin.HR('OK'),
            lblCancel = window.hWin.HR('Cancel');
        
        if($.isPlainObject(labels)){
            if(labels.title)  title = labels.title;
            if(labels.yes)    lblYes = window.hWin.HR(labels.yes);
            if(labels.no)     lblNo = window.hWin.HR(labels.no);
            if(labels.ok)     lblOk = window.hWin.HR(labels.ok);
            if(labels.cancel) lblCancel = window.hWin.HR(labels.cancel);
        }else if (labels!=''){
            title = labels;
        }
        
        if (window.hWin.HEURIST4.util.isFunction(buttons)){ //}typeof buttons === "function"){

            let callback = buttons;

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

        let options =  {
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
                let posele = $(ext_options);
                if(posele.length>0)
                    options.position = { my: "left top", at: "left bottom", of: $(ext_options) };
           }
           
           if(ext_options['removeOnClose']){
                options.close = function(event, ui){  $dlg.remove(); };   
           }
           
        }
        if(!options.position){
            options.position = { my: "center center", at: "center center", of: window };    
        }
        options.position.collision = 'none'; //FF fix
        

        if(isPopupDlg){

            if(!options.open){
                options.open = function(event, ui){
                    $dlg.scrollTop(0);
                };
            }

            if(!options.height) options.height = 515;
            if(!options.width) options.width = 705;
            if(window.hWin.HEURIST4.util.isempty(options.resizable)) options.resizable = true;
            /* auto height dialog
            if(options.resizable === true){
            options.resizeStop = function( event, ui ) {
 
               var nh = $dlg.parent().height()
                            - $dlg.parent().find('.ui-dialog-titlebar').height() - $dlg.parent().find('.ui-dialog-buttonpane').height(); //-20

                    $dlg.css({overflow: 'none !important','width':'100%', 'height':nh });
                };
            }*/
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
        
        if(options.enable_buttons_after>0){
            const btns = $dlg.parent().find('.ui-dialog-buttonset > button');
            window.hWin.HEURIST4.util.setDisabled(btns, true);
            setTimeout(()=>{ window.hWin.HEURIST4.util.setDisabled(btns, false); }, options.enable_buttons_after)
        }
        if(options.noClose){
            $dlg.parent().find('.ui-dialog-titlebar').find('.ui-icon-closethick').parent().hide();
        }
        
        return $dlg;
       
       
       
        //'#8ea9b9 none repeat scroll 0 0 !important'     none !important','background-color':'none !important
    },  
    
    // for progress message
    _progressInterval: 0, // Internal: Stores interval ID for progress polling
    _progressDiv: null, // Internal: Stores jQuery object of the progress display div
    _progressPopup: null, // Internal: Stores jQuery object of the progress popup dialog
    
    /**
     * Displays a progress indicator, either in a popup dialog or an existing container.
     * It periodically polls a progress URL to update the status, steps, and progress bar.
     *
     * @memberof HEURIST4.msg
     * @param {Object} options - Configuration options for the progress display.
     * @param {jQuery} [options.container] - If provided, the progress indicator is shown within this jQuery element.
     *                                       Otherwise, a popup dialog is created.
     * @param {string|false} [options.content] - Custom HTML content for the progress display area.
     *                                         - If a string: Used as the HTML content.
     *                                         - If `false`: The function uses the `options.container`'s existing content.
     *                                         - If undefined/null: Default content with steps and progress bar is generated.
     * @param {Array<string>} [options.steps] - An array of strings representing the steps of the progress,
     *                                          used if default content is generated.
     * @param {function} [options.onComplete] - Callback function to execute when the progress is terminated/completed.
     * @param {string|number} [options.session_id] - A unique session ID for the progress tracking.
     *                                             If not provided, a random one is generated.
     * @param {number} [options.interval=900] - The interval in milliseconds for polling the progress URL. Defaults to 900ms.
     * @param {number} [options.width=500] - Width of the popup dialog, if applicable.
     * @param {boolean} [options.hideTitle=true] - Whether to hide the title of the popup dialog, if applicable.
     * @returns {string|undefined} The session ID used for the progress tracking, or undefined if a previous progress
     *                             operation is still active (indicated by `_progressInterval > 0`).
     */
    showProgress: function( options ){
        if(window.hWin.HEURIST4.msg._progressInterval>0){
            console.log('previous progress is not completed'); // Log and exit if another progress is running
            return 0;
        }
        
        let $progress_div;
        let content = options?.content;
        let onComplete = options?.onComplete;
        let is_popup = true;
        
        if(!options){
            options = {};
        }        

        if(options?.container){ //container element
            is_popup = false;
            $progress_div = options.container;
        }        
        
        if (window.hWin.HEURIST4.util.isempty(content)) {
            //default content
            content = '';
            
            if(Array.isArray(options?.steps)){
                
                content = '<ol type="1" style="font-size:12px;height:80%;padding-top:20px;" class="progress-steps">';
                
                options.steps.forEach((item)=>{
                    content += `<li style="color:gray">${item}</li>`;
                });
                
                content += '</ol>';
            }else{
                content = '<div class="loading" style="height:80%;min-height:150px"></div>';
            }
            
            content += '<div style="display:none;width:80%;height:40px;padding:5px;text-align:center;margin:auto;margin-top:10px">'
                +'<div id="progressbar"><div class="progress-label">Processing data...</div></div>'
                +'<div class="progress_stop" style="text-align:center;margin-top:4px">Abort</div>'
            +'</div>';
            
        }else if(typeof content !== 'string'){
            content = false;
        }
        
        if(is_popup){
            options['buttons'] = {}; //no buttons
            options['width'] = 500;
            options['hideTitle'] = true;
   
            window.hWin.HEURIST4.msg._progressPopup = window.hWin.HEURIST4.msg.showMsg( 
                    '<div class="progressbar_div" style="margin:10px"></div>', options );
        
            $progress_div = window.hWin.HEURIST4.msg._progressPopup.find('div.progressbar_div');
        }
        
        
        let progress_url = window.hWin.HAPI4.baseURL + "hserv/controller/progress.php";
        
        let session_id = options.session_id;
        if(!(session_id>0)) session_id = window.hWin.HEURIST4.util.random();

        let t_interval = options.interval;
        if(!(t_interval>0)) t_interval = 900;
        
        window.hWin.HEURIST4.msg._progressDiv = $progress_div;
        $progress_div.show(); 
        document.body.style.cursor = 'progress';
        
        //add progress bar content
        if(content){
            $progress_div.html(content);
        }
        

        //elements        
        let btn_stop = $progress_div.find('.progress_stop').button();
        
        // termination
        btn_stop.on({click:function(){
                let request = {terminate:1, t:(new Date()).getMilliseconds(), session:session_id};
                window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                    window.hWin.HEURIST4.msg.hideProgress();
                });
            }});
    
        let progressSteps = $progress_div.find('.progress-steps');
        let div_loading = $progress_div.find('.loading').show();
        let pbar = $progress_div.find('#progressbar');
        let progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});

        let elapsed = 0;
        
        window.hWin.HEURIST4.msg._progressInterval = setInterval(function(){ 
            
            let request = {t:Date.now(), session:session_id};            
            
            window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){

                if(response?.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    window.hWin.HEURIST4.msg.hideProgress();
                }else if(response){
                    //it may return terminate,done,
                    
                    let resp = response.split(',');
                    
                    if(response=='terminate' || !(resp.length>=2)){
                        //first or last response
                        if(response=='terminate'){
                            
                            if(onComplete){
                                onComplete.call();    
                            }
                            
                            window.hWin.HEURIST4.msg.hideProgress();
                        }else{
                            div_loading.show();    
                        }
                    }else{
                        div_loading.hide();
                        pbar.parent().show();
                        
                        if(resp.length==3 || resp.length==1){
                            let newStep = resp.shift();
                            let percentage = 0;
                            progressSteps.show();

                            if(window.hWin.HEURIST4.util.isNumber(newStep)){
                                newStep = parseInt(newStep);
                                let all_li = progressSteps.find('li');
                                if(newStep>0){
                                    let arr = all_li.slice(0,newStep);
                                    arr.css('color','black');
                                    arr.find('span.processing').remove(); //remove rotation icon
                                }
                                if(newStep<all_li.length){
                                        if(percentage>0){
                                            if(percentage>100) percentage = 100;
                                            percentage = percentage+'%'; 
                                        }else{
                                            percentage = 'processing...'
                                        }
                                        let ele = $(all_li[newStep]).find('span.percentage');
                                        if(ele.length==0){
                                            percentage = '<span class="percentage">'+percentage+'</span>';
                                            $('<span class="processing"> <span class="ui-icon ui-icon-loading-status-balls"></span> '
                                                +percentage+'</span>')
                                                .appendTo( $(all_li[newStep]) );
                                            $(all_li[newStep]).css('color','black');
                                        }else{
                                            ele.text(percentage);
                                        }
                                }
                            }else{
                                let container = progressSteps.find('ol');
                                let li_ele = container.find('li:contains("'+newStep+'")');
                                if(li_ele.length==0){ //not added yet
                                    $('<li>'+newStep+'</li>').appendTo(container);    
                                }
                            }
                        }

                        let cnt=0, total=0;
                        if(resp.length>1){
                            cnt = resp[0];
                            total = resp[1];
                        }
                        
                        if(cnt>0 && total>0){
                            const val = cnt*100/total;
                            pbar.progressbar( "value", val );

                            elapsed += t_interval;
                            let est_remaining = (elapsed / resp[0]) * (resp[1] - resp[0]);

                            if(est_remaining < 10000){ // less than 10 seconds
                                est_remaining = 'a few seconds';
                            }else if(est_remaining < 60000){ // less than a minute
                                est_remaining = `${Math.ceil(est_remaining / 1000)} seconds`;
                            }else{
                                est_remaining = `${Math.ceil(est_remaining / 60000)} minutes`;
                            }

                            progressLabel.text(`${resp[0]} of ${resp[1]}   ${(resp.length==3?resp[2]:'')} (approximately ${est_remaining} remaining)`);
                        }else{
                            progressLabel.text('preparing...');
                            pbar.progressbar( "value", 0 );
                        }
                    }
                    
                }
            },'text');
          
        
        }, t_interval);                
        
        return session_id;
    },
    
    /**
     * Hides the currently active progress indicator.
     * It clears the polling interval, hides the progress div, and closes the progress popup if it was used.
     * Also resets the mouse cursor from 'progress' to 'auto'.
     *
     * @memberof HEURIST4.msg
     * @returns {void}
     */
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
        if(window.hWin.HEURIST4.msg._progressPopup){
            if (window.hWin.HEURIST4.msg._progressPopup.dialog('instance')) {
                 window.hWin.HEURIST4.msg._progressPopup.dialog( "close" );
            }
            window.hWin.HEURIST4.msg._progressPopup = null;
        }
        
    },
    
    
    /**
     * Displays a warning dialog prompting the user to save or ignore changes before exiting.
     * Typically used when there are unsaved modifications.
     *
     * @memberof HEURIST4.msg
     * @param {string} sMessage - The message content for the dialog (e.g., "You have unsaved changes...").
     *                            This message is translated using `window.hWin.HR`.
     *                            Note: The `sMessage` parameter is defined but not directly used in the current implementation;
     *                            instead, a hardcoded "Warn_Lost_Data" key is used for the message.
     * @param {function} onSave - Callback function to execute if the user chooses to "Save".
     * @param {function} onIgnore - Callback function to execute if the user chooses to "Ignore and close".
     * @returns {void}
     */
    showMsgOnExit: function(sMessage, onSave, onIgnore){ // sMessage seems unused
        let $dlg, buttons = {};
        buttons[window.hWin.HR('Save')] = function(){  // Using HR for button labels directly based on other patterns
            onSave();
            $dlg.dialog('close'); 
        }; 
        buttons[window.hWin.HR('Ignore and close')] = function(){
            onIgnore();
            $dlg.dialog('close'); 
        };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
            window.hWin.HR('Warn_Lost_Data'), // sMessage is passed but 'Warn_Lost_Data' is used
            buttons,
            {title: window.hWin.HR('Confirm'),
               // yes: window.hWin.HR('Save'), // These are covered by direct button definition above
               // no: window.hWin.HR('Ignore and close')
            },
            {default_palette_class:'ui-heurist-design'});
        
    },
    
    /**
     * Checks if an error message string indicates the use of a disabled PHP function in a Smarty template (custom report).
     * If such an error is detected, it displays a warning dialog informing the user and suggesting they request
     * the function to be enabled via a bug report.
     *
     * @memberof HEURIST4.msg
     * @param {string} txt - The error message string to check.
     * @returns {boolean} True if a warning dialog was shown (meaning the specific error was detected), false otherwise.
     */
    showWarningAboutDisabledFunction: function(txt){
      
            if(txt.indexOf('Exception on execution: Syntax error in template')==0 
            && txt.indexOf('not allowed by security setting')>0){
                
                    let buttons = null;
                    let $dlgm; // Define $dlgm here to be accessible in button callbacks
                    if(window.hWin.HAPI4 && window.hWin.HAPI4.actionHandler){ // Check HAPI4 exists
                        buttons = 
                        {
                            [window.hWin.HR('Send Bug Report')]: function() {
                                window.hWin.HAPI4.actionHandler.executeActionById('menu-help-bugreport');
                                if ($dlgm) $dlgm.dialog( 'close' );
                                },
                            [window.hWin.HR('Cancel')]:function() { // Translate button label
                                if ($dlgm) $dlgm.dialog( 'close' );
                            }
                        };
                    }

                    $dlgm = window.hWin.HEURIST4.msg.showMsgDlg(
    '<p>Sorry, native php functions in custom reports are disabled by default<br>'
    +'as a security precaution. </p>'
    +'<p>Please use the Create ticket function to ask that this function be enabled. </p>',
                        buttons,
                        window.hWin.HR('Warning')); // Translate title
                        
                        return true;
                
            }
            
            return false;
        
    },
    
    /**
     * Prepares and optionally displays a detailed message about parent record pointers
     * that could not be automatically removed due to existing reverse child record pointers.
     * The message explains the situation and provides links to view the affected records.
     *
     * @memberof HEURIST4.msg
     * @param {Object} details - An object where keys are parent record IDs. Each value is an object:
     *                           `{title: string, type: string, restored: Array<Object>}`.
     *                           `restored` is an array of child info: `{field: string, id: string, title: string, type: string}`.
     * @param {boolean} [create_popup=false] - If true, displays the generated message in a dialog.
     *                                         If false, returns an object with message components.
     * @returns {jQuery|Object|string}
     *          - If `create_popup` is true: Returns the jQuery dialog object.
     *          - If `create_popup` is false and details are found: Returns an object
     *            `{message: string, title: string, handlers: Object}` for manual dialog creation.
     *          - If `create_popup` is false and no relevant details found: Returns an empty string.
     */
    prepareParentRecordMsg: function(details, create_popup = false){

        let link_styling = "text-decoration: underline; cursor: pointer; color: black;";

        let title = 'Parent record pointers which could not be removed';
        let msg = 'Not all parent record pointers could be deleted, because some have a child record pointer in the reverse direction.<br><br>'
                + 'To delete these parent record pointers you will need to delete the child record pointers in the affected parent records, or<br>'
                + 'change the Child Record Pointer field(s) in the parent record types to a standard Record pointer field (by editing the field in Structure Modification mode and unchecking the Child checkbox)<br><br>'
                + `Click <span style="${link_styling}" id="parent_list">here</span> for a list of two-way pointers which were not deleted.<br><br>`
                + `Click <span style="${link_styling}" id="parent_search">here</span> for a search result of all parent records containing such pointers.<br><br>`
                + 'Note: we do not delete the existing parent-child relations because of the risk that this unintentionally deletes data<br>'
                + 'that is still required (as simple record pointer connections). A manual process as above is much safer.'

        let icon_bg = `${window.hWin.HAPI4.baseURL}hclient/assets/16x16.gif`;

        let list = '<div>';
        for(const parent_ID in details){

            const parent_title = details[parent_ID].title;
            const parent_type = details[parent_ID].type;
            let restored_children = details[parent_ID].restored;

            if(window.hWin.HEURIST4.util.isempty(restored_children)){
                continue;
            }

            list += '<div>'
                 +  `<img src="${icon_bg}" class="rt-icon" style="background-image:url('${window.hWin.HAPI4.iconBaseURL}${parent_type}');">`
                 + `<span style="${link_styling} vertical-align: 4px;" class="record_search_link" data-recid=="${parent_ID}">${parent_title}</span>`
                 + '<div style="margin: 5px 15px 10px;">';

            for(const idx in restored_children){

                let child = restored_children[idx];

                list += `<em style="vertical-align: 4px;">${$Db.rst(parent_type, child.field, 'rst_DisplayName')}</em> <span style="vertical-align: 4px;">&rArr;</span> `
                     + `<img src="${icon_bg}" class="rt-icon" style="background-image:url('${window.hWin.HAPI4.iconBaseURL}${child.type}');">`
                     + `<span style="${link_styling} vertical-align: 4px;" class="record_search_link" data-recid=="${child.id}">${child.title}</span><br>`;
            }

            list += '</div></div>';
        }
        if(list.length === 5){
            return '';
        }
        list += '</div>';

        let handlers = {
            '#parent_list': () => {

                let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(list, null, {title: 'List of pointers not deleted'}, {default_palette_class: 'ui-heurist-explore', dialogId: 'retained-parents'});

                $dlg.on('click', (event) => {
                    let recID = $(event.target).attr('data-recid');
                    let url = `${window.hWin.HAPI4.baseURL}viewers/record/renderRecordData.php?db=${window.hWin.HAPI4.database}&recID=${recID}`;
                    window.open(url, '_blank');
                })
            },
            '#parent_search': () => {
                let query = `ids:${Object.keys(details).join(',')}`;
                let url = `${window.hWin.HAPI4.baseURL}?db=${window.hWin.HAPI4.database}&q=${encodeURIComponent(query)}`;
                window.open(url, '_blank');
            }
        };

        if(!create_popup){
            return {
                message: msg,
                title: title,
                handlers: handlers
            };
        }

        let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, null, {title: title}, {default_palette_class: 'ui-heurist-populate', dialogId: 'restore-parent-records'});

        $dlg.find('#parent_list').on('click', handlers['#parent_list']);
        $dlg.find('#parent_search').on('click', handlers['#parent_search']);

        $dlg.find('.record_search_link').on('click', handlers['#parent_list.record_search_link']);

        return $dlg;
    }
};