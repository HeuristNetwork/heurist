/**
* Login dialogue
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

//isforsed - if true - it is not possible to get out from login other than switch database
function showLoginDialog(isforsed, callback, parentwin, dialog_id){

    var is_secondary_parent = false;
    if(!parentwin){
        parentwin = window.hWin;  
    }else{
        is_secondary_parent = true; //to reduce font in popup
    } 
    
    if(!dialog_id) dialog_id = 'heurist-login-dialog';
    
    var login_dialog = $(parentwin.document['body']).find('#'+dialog_id);

    function _setMessage(text){
        var message = login_dialog.find('.messages');
        if(window.hWin.HEURIST4.util.isempty(text)){
            message.empty();
            message.removeClass('ui-state-error');   
        }else{
            message.html(text);
            message.addClass('ui-state-error');   
        }
    }    
    

    if(login_dialog.length<1)  // login_dialog.is(':empty') )
    {

        login_dialog = $( '<div id="'+dialog_id+'">' )
            .addClass('ui-heurist-bg-light')
            .appendTo( $(parentwin.document['body']) );

        var $dlg = login_dialog;

        //load login dialogue
        $dlg.load(window.hWin.HAPI4.baseURL + "hclient/widgets/profile/profile_login.html?t="
                            +window.hWin.HEURIST4.util.random(), 
            function(){ 

                
            $dlg.find('#span-database').text(window.hWin.HAPI4.database);

            $dlg.find('img#favicon').attr('src', window.hWin.HAPI4.baseURL + 'favicon.ico');

            if(window.hWin.HAPI4.sysinfo.host_logo){

                $('<div style="height:40px;padding-left:4px;float:right">'
                    +'<a href="'+(window.hWin.HAPI4.sysinfo.host_url?window.hWin.HAPI4.sysinfo.host_url:'#')
                    +'" target="_blank" style="text-decoration:none;color:black;">'
                            +'<label>at: &nbsp;</label>'
                            +'<img src="'+window.hWin.HAPI4.sysinfo.host_logo
                            +'" height="35" align="center"></a></div>')
                .appendTo($dlg.find('div#host_info'));
            }
            
            //find all labels and apply localization
            $dlg.find('label').each(function(){
                $(this).html(window.hWin.HR($(this).html()));
            });
            
            //fill databse owner information
            $dlg.find('#span-owner-name').text(window.hWin.HAPI4.sysinfo.dbowner_name);
            $dlg.find('#span-owner-org').text(window.hWin.HEURIST4.util.isempty(window.hWin.HAPI4.sysinfo.dbowner_org)
                    ?'N/A':window.hWin.HAPI4.sysinfo.dbowner_org);
            $dlg.find('#span-owner-email').html('<a href="mailto:'
                    +window.hWin.HAPI4.sysinfo.dbowner_email+'">'+window.hWin.HAPI4.sysinfo.dbowner_email+'</a>');
            
//console.log(window.hWin.HAPI4.sysinfo.db_total_records);            
            if(window.hWin.HAPI4.sysinfo.db_total_records && window.hWin.HAPI4.sysinfo.db_total_records>=0){
                $dlg.find('#span-reccount').text(window.hWin.HAPI4.sysinfo.db_total_records+' records');
            }else{
                $dlg.find('#span-reccount').text('');
            }
            

            if(false){
                //init captcha

                function __refreshCaptcha(){
                    var $dd = $dlg.find('#imgdiv');
                    var id = window.hWin.HEURIST4.util.random();
                    if(true){
                        $dd.load(window.hWin.HAPI4.baseURL+'hsapi/utilities/captcha.php?id='+id);
                    }else{
                        $dd.empty(); //find("#img").remove();
                        $('<img id="img" src="hsapi/utilities/captcha.php?img='+id+'"/>').appendTo($dd);
                    }
                }

                $dlg.find('#imgdiv').show();
                $dlg.find('#btnCptRefresh')
                .button({text:false, icons:{ secondary: "ui-icon-refresh" }})
                .show()
                .click( __refreshCaptcha );

                __refreshCaptcha();
            }


            var allFields = $dlg.find('input');
            
            var isreset = false;

            function __doLogin(){

                allFields.removeClass( "ui-state-error" );
                var message = login_dialog.find('.messages');

                if(isreset){
                    
                    var rusername = $dlg.find('#reset_username');
                    if(window.hWin.HEURIST4.msg.checkLength( rusername, "username", null, 1, 0 ))
                    {
                        login_dialog.parents('.ui-dialog').find('#btn_login2').hide();
                        
                        window.hWin.HEURIST4.msg.bringCoverallToFront(login_dialog.parents('.ui-dialog'),null,'sending...'); 
                        
                        window.hWin.HAPI4.SystemMgr.reset_password({username: rusername.val()}, function(response){
                            
                            window.hWin.HEURIST4.msg.sendCoverallToBack();
                            
                            if(response.status == window.hWin.ResponseStatus.OK){
                                
                                $dlg2 = window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Password_Reset'), null, ""); // Title was an unhelpful and inelegant "Info"
                                
                                $dlg2.dialog('option','close',function(){ login_dialog.dialog('close'); });
                                
                            }else{
                                login_dialog.parents('.ui-dialog').find('#btn_login2').show();
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        });
                    }
                }else{

                    var username = $dlg.find('#username');
                    var password = $dlg.find('#password');
                    var session_type = $dlg.find('input[name="session_type"]');
                    if(session_type.is("input[type=checkbox]")){
                        session_type = 'remember';
                    }else{
                        session_type = session_type.val();
                    }

                    var bValid = window.hWin.HEURIST4.msg.checkLength( username, "username", null, 1 )
                    && window.hWin.HEURIST4.msg.checkLength( password, "password", null, 1 );         //3,63

                    if ( bValid ) {

                        //get hapi and perform login
                        window.hWin.HAPI4.SystemMgr.login({username: username.val(), password:password.val(), 
                                                session_type:session_type},
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){

                                    window.hWin.HAPI4.setCurrentUser(response.data.currentUser);
                                    window.hWin.HAPI4.sysinfo = response.data.sysinfo;

                                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS, 
                                                    [window.hWin.HAPI4.currentUser]);

                                    $dlg.dialog( "close" );
                                    
                                    if( window.hWin.HAPI4.SystemMgr.versionCheck() ) {
                                        //version is old 
                                        return;
                                    }
                                    /*
                                    if($.isFunction(callback)){
                                            callback(true);
                                    }
                                    */
                                    
                                    //that._refresh();
                                }else if(response.status == window.hWin.ResponseStatus.REQUEST_DENIED){
                                    _setMessage(response.message);
                                    setTimeout(function(){ _setMessage(); }, 2000);
                                }else {
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }
                            }

                        );

                    }
                }
            }

            //start login on enter press
            allFields.on("keypress",function(event){
                var code = (event.keyCode ? event.keyCode : event.which);
                if (code == 13) {
                    __doLogin();
                }
            });

            $dlg.find("#link_restore").on("click", function(){
                isreset = true;
                $dlg.dialog("option","title",window.hWin.HR('Reset password'));
                $("#btn_login2").button("option","label",window.hWin.HR('Reset password'));
                //$dlg.find("#btn_login2").button("option","label",window.hWin.HR('Reset password'));
                $dlg.find("#fld_reset").show();
                $dlg.find("#fld_login").hide();
                _setMessage();
            });

            var arr_buttons = [{html:('<b>'+window.hWin.HR('Login')+'</b>'), click: __doLogin, id:'btn_login2'}];
            //!is_secondary_parent && 
            if(window.hWin.HAPI4.sysinfo.registration_allowed==1){ //isforsed && 
                arr_buttons.push({text:window.hWin.HR('Register'), click: function(){
                    doRegister( parentwin );   
                }, id:'btn_register'});
            }
            arr_buttons.push({text:window.hWin.HR('Cancel'), click: function() {    //isforsed?'Change database':
                $( this ).dialog( "close" );
            }});


            // login dialog definition
            $dlg.dialog({
                autoOpen: false,
                //height: 300,
                width: 450,
                modal: true,
                resizable: false,
                title: window.hWin.HR('Heurist Login'),
                buttons: arr_buttons,
                close: function() {
                    allFields.val( "" ).removeClass( "ui-state-error" );
                    
                    if($.isFunction(callback)){
                        callback(window.hWin.HAPI4.has_access());
                    }else
                    if( isforsed && !window.hWin.HAPI4.has_access() ){
                        //redirects to startup page - list of all databases
                        window.hWin.location  = window.HAPI4.baseURL; //startup page 
                    }
                    $dlg.remove();
                },
                open: function() {
                    isreset = false;
                    $("#btn_login2").button("option","label",'<b>'+window.hWin.HR('Login')+'</b>');
                    //$dlg.find("#btn_login2").button("option","label",window.hWin.HR('Login'));
                    $dlg.find("#fld_reset").hide();
                    $dlg.find("#fld_login").show();
                    _setMessage();
                }
                //position:{ my: "center center", at: "center center", of: $(top.document) }
            });

            $dlg.dialog("open");
            if(is_secondary_parent)$dlg.addClass('ui-dialog-heurist').css({'font-size':'0.8em'});
            $dlg.parent().position({ my: "center center", at: "center center", of: $(top.document) });

            /*if(isforsed){
            var left_pane = $("div").css('float','left').appendTo( $dlg.find(".ui-dialog-buttonpane") );
            var btn_db = $( "<button>" ).appendTo( left_pane )
            .button( {title: window.hWin.HR("Change database")} ).click( function() { $dlg.dialog( "close" ); } );
            }*/

        });//load html
    }else{
        //show dialogue
        login_dialog.dialog("open");
        login_dialog.parent().addClass('ui-dialog-heurist');
    }
}

function doRegister( parentwin ){

    var is_secondary_parent = false;
    if(!parentwin){
        parentwin = window.hWin;  
    }else{
        is_secondary_parent = true;
    } 
    var $doc = $(parentwin.document['body']);

    if($.isFunction($doc.profile_edit)){

        var profile_edit_dialog = $('#heurist-profile-dialog');
        if(profile_edit_dialog.length<1){
            profile_edit_dialog = $( '<div id="heurist-profile-dialog">' ).addClass('ui-heurist-bg-light').appendTo( $doc );
        }
        profile_edit_dialog.profile_edit({'ugr_ID': window.hWin.HAPI4.currentUser.ugr_ID, 'parentwin': parentwin});
        
    }else{
        $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/profile/profile_edit.js', function() {
            if($.isFunction($doc.profile_edit)){
                doRegister( parentwin );
            }else{
                window.hWin.HEURIST4.msg.showMsgErr('Widget "Profile edit" cannot be loaded!');
            }
        });
    }

}

