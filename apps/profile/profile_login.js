/**
* Login dialogue
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
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
function doLogin(isforsed){

    
        var login_dialog = $('#heurist-login-dialog');
    

        if(login_dialog.length<1)  // login_dialog.is(':empty') )
        {
    
            login_dialog = $( '<div id="heurist-login-dialog">' ).addClass('ui-heurist-bg-light').appendTo( $('body') );
            
            var $dlg = login_dialog;

            //load login dialogue
            $dlg.load(top.HAPI4.basePath + "apps/profile/profile_login.html", function(){  //?t="+(new Date().getTime())

                //find all labels and apply localization
                $dlg.find('label').each(function(){
                    $(this).html(top.HR($(this).html()));
                });

                var allFields = $dlg.find('input');
                var message = $dlg.find('.messages');
                var isreset = false;

                function __doLogin(){

                    allFields.removeClass( "ui-state-error" );

                    if(isreset){
                        var rusername = $dlg.find('#reset_username');
                        if(top.HEURIST4.msg.checkLength( rusername, "username", message, 1, 0 ))
                        {
                            top.HAPI4.SystemMgr.reset_password({username: rusername.val()}, function(response){
                                if(response.status == top.HAPI4.ResponseStatus.OK){
                                    $dlg.dialog( "close" );
                                    top.HEURIST4.msg.showMsgDlg(top.HR('Your password has been reset. You should receive an email shortly with your new password'), null, "Info");
                                }else{
                                    top.HEURIST4.msg.showMsgErr(response);
                                }
                            });
                        }
                    }else{

                        var username = $dlg.find('#username');
                        var password = $dlg.find('#password');
                        var session_type = $dlg.find('input[name="session_type"]');

                        var bValid = top.HEURIST4.msg.checkLength( username, "username", message, 1 )
                        && top.HEURIST4.msg.checkLength( password, "password", message, 1 );         //3,63

                        if ( bValid ) {

                            //get hapi and perform login
                            top.HAPI4.SystemMgr.login({username: username.val(), password:password.val(), session_type:session_type.val()},
                                function(response){
                                    if(response.status == top.HAPI4.ResponseStatus.OK){

                                        top.HAPI4.setCurrentUser(response.data.currentUser);
                                        top.HAPI4.sysinfo = response.data.sysinfo;

                                        $(document).trigger(top.HAPI4.Event.LOGIN, [top.HAPI4.currentUser]);

                                        $dlg.dialog( "close" );
                                        //that._refresh();
                                    }else if(response.status == top.HAPI4.ResponseStatus.REQUEST_DENIED){
                                        message.addClass( "ui-state-highlight" );
                                        message.text(response.message);
                                    }else {
                                        top.HEURIST4.msg.showMsgErr(response);
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
                    $dlg.dialog("option","title",top.HR('Reset password'));
                    $("#btn_login2").button("option","label",top.HR('Reset password'));
                    //$dlg.find("#btn_login2").button("option","label",top.HR('Reset password'));
                    $dlg.find("#fld_reset").show();
                    $dlg.find("#fld_login").hide();
                    $dlg.find(".messages").removeClass( "ui-state-highlight" ).text('');
                });

                var arr_buttons = [{text:'<b>'+top.HR('Login')+'</b>', click: __doLogin, id:'btn_login2'}];
                if(isforsed && top.HAPI4.sysinfo.registration_allowed==1){
                    arr_buttons.push({text:top.HR('Register'), click: doRegister, id:'btn_register'});
                }
                arr_buttons.push({text:top.HR('Cancel'), click: function() {    //isforsed?'Change database':
                            $( this ).dialog( "close" );
                        }});
                
                
                // login dialog definition
                $dlg.dialog({
                    autoOpen: false,
                    //height: 300,
                    width: 450,
                    modal: true,
                    resizable: false,
                    title: top.HR('Login'),
                    buttons: arr_buttons,
                    close: function() {
                        allFields.val( "" ).removeClass( "ui-state-error" );
                        if( isforsed && !top.HAPI4.is_logged() ){
                            //redirect to select database
                            window.location  = window.HAPI4.basePath + "php/databases.php";
                        }
                    },
                    open: function() {
                        isreset = false;
                        $dlg.dialog("option","title",top.HR('Login'));
                        $("#btn_login2").button("option","label",'<b>'+top.HR('Login')+'</b>');
                        //$dlg.find("#btn_login2").button("option","label",top.HR('Login'));
                        $dlg.find("#fld_reset").hide();
                        $dlg.find("#fld_login").show();
                        $dlg.find(".messages").removeClass( "ui-state-highlight" ).text('');
                    }
                });
                
                $dlg.dialog("open");
                $dlg.parent().addClass('ui-dialog-heurist');

                /*if(isforsed){
                     var left_pane = $("div").css('float','left').appendTo( $dlg.find(".ui-dialog-buttonpane") );
                     var btn_db = $( "<button>" ).appendTo( left_pane )
                        .button( {title: top.HR("Change database")} ).click( function() { $dlg.dialog( "close" ); } );
                }*/

            });//load html
        }else{
            //show dialogue
            login_dialog.dialog("open");
            login_dialog.parent().addClass('ui-dialog-heurist');
        }
}

function doRegister(){
        
        if($.isFunction($('body').profile_edit)){

            var profile_edit_dialog = $('#heurist-profile-dialog');
            if(profile_edit_dialog.length<1){
                profile_edit_dialog = $( '<div id="heurist-profile-dialog">' ).addClass('ui-heurist-bg-light').appendTo( $('body') );               
            }
            profile_edit_dialog.profile_edit({'ugr_ID': top.HAPI4.currentUser.ugr_ID});

        }else{
            $.getScript(top.HAPI4.basePath+'apps/profile/profile_edit.js', function() {
                if($.isFunction($('body').profile_edit)){
                    doRegister();
                }else{
                    top.HEURIST4.msg.showMsgErr('Widget profile edit not loaded!');
                }        
            });          
        }
        
}