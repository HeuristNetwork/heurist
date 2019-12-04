/**
* Login dialogue
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

//isforsed - if true - it is not possible to get out from login other than switch database
function doLogin(isforsed){


    var login_dialog = $('#heurist-login-dialog');

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

        login_dialog = $( '<div id="heurist-login-dialog">' ).addClass('ui-heurist-bg-light').appendTo( $('body') );

        var $dlg = login_dialog;

        //load login dialogue
        $dlg.load(window.hWin.HAPI4.baseURL + "hclient/widgets/profile/profile_login.html?t="
                            +window.hWin.HEURIST4.util.random(), 
            function(){ 

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
            
            $dlg.find('#span-reccount').text(window.hWin.HAPI4.sysinfo.db_total_records+' records');
            

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
                    if(window.hWin.HEURIST4.msg.checkLength( rusername, "username", message, 1, 0 ))
                    {
                        window.hWin.HAPI4.SystemMgr.reset_password({username: rusername.val()}, function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Password_Reset'), null, ""); // Title was an unhelpful and inelegant "Info"
                            }else{
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

                    var bValid = window.hWin.HEURIST4.msg.checkLength( username, "username", message, 1 )
                    && window.hWin.HEURIST4.msg.checkLength( password, "password", message, 1 );         //3,63

                    if ( bValid ) {

                        //get hapi and perform login
                        window.hWin.HAPI4.SystemMgr.login({username: username.val(), password:password.val(), 
                                                session_type:session_type},
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){

                                    window.hWin.HAPI4.setCurrentUser(response.data.currentUser);
                                    window.hWin.HAPI4.sysinfo = response.data.sysinfo;

                                    $(document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS, [window.hWin.HAPI4.currentUser]);

                                    $dlg.dialog( "close" );
                                    
                                    if( window.hWin.HAPI4.SystemMgr.versionCheck() ) {
                                        //version is old 
                                        return;
                                    }
                                    
                                    var lt = window.hWin.HAPI4.sysinfo['layout']; 
                                    if(!(lt=='DigitalHarlem' || lt=='DigitalHarlem1935' || lt=='WebSearch')){
                                    
                                        var init_search = window.hWin.HAPI4.get_prefs('defaultSearch');
                                        if(!window.hWin.HEURIST4.util.isempty(init_search)){
                                            var request = {q: init_search, w: 'a', f: 'map', source:'init' };
                                            setTimeout(function(){
                                                window.hWin.HAPI4.SearchMgr.doSearch(document, request);
                                            }, 3000);
                                        }
                                        
                                        
                                        if(window.hWin.HAPI4.sysinfo.db_has_active_dashboard>0) {
                                           //show dashboard
                                           var prefs = window.hWin.HAPI4.get_prefs_def('prefs_sysDashboard', {showonstartup:1});
                                           if(prefs.showonstartup==1)
                                                    window.hWin.HEURIST4.ui.showEntityDialog('sysDashboard');
                                        }
                                        
                                    }
                                    
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

            var arr_buttons = [{text:'<b>'+window.hWin.HR('Login')+'</b>', click: __doLogin, id:'btn_login2'}];
            if(isforsed && window.hWin.HAPI4.sysinfo.registration_allowed==1){
                arr_buttons.push({text:window.hWin.HR('Register'), click: doRegister, id:'btn_register'});
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
                title: window.hWin.HR('Login'),
                buttons: arr_buttons,
                close: function() {
                    allFields.val( "" ).removeClass( "ui-state-error" );
                    if( isforsed && !window.hWin.HAPI4.has_access() ){
                        //redirect to select database
                        window.location  = window.HAPI4.baseURL; //+ "hsapi/utilities/list_databases.php";
                    }
                },
                open: function() {
                    isreset = false;
                    $dlg.dialog("option","title",window.hWin.HR('Login'));
                    $("#btn_login2").button("option","label",'<b>'+window.hWin.HR('Login')+'</b>');
                    //$dlg.find("#btn_login2").button("option","label",window.hWin.HR('Login'));
                    $dlg.find("#fld_reset").hide();
                    $dlg.find("#fld_login").show();
                    _setMessage();
                }
            });

            $dlg.dialog("open");
            $dlg.parent().addClass('ui-dialog-heurist');

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

function doRegister(){

    if($.isFunction($('body').profile_edit)){

        var profile_edit_dialog = $('#heurist-profile-dialog');
        if(profile_edit_dialog.length<1){
            profile_edit_dialog = $( '<div id="heurist-profile-dialog">' ).addClass('ui-heurist-bg-light').appendTo( $('body') );
        }
        profile_edit_dialog.profile_edit({'ugr_ID': window.hWin.HAPI4.currentUser.ugr_ID});

    }else{
        $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/profile/profile_edit.js', function() {
            if($.isFunction($('body').profile_edit)){
                doRegister();
            }else{
                window.hWin.HEURIST4.msg.showMsgErr('Widget "Profile edit" cannot be loaded!');
            }
        });
    }

}

