/**
* Dialog to edit user's profile (logged in) or register new user (not logged in)
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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


$.widget( "heurist.profile_edit", {

    // default options
    options: {
        isdialog: true,

        ugr_ID:0,
        edit_data: {},
        isregistration: false,

        needclear: true, //clear input everytime for registration

        // callbacks
        callback:null
    },

    // the widget's constructor
    _create: function() {

        // prevent double click to select text
        this.element.disableSelection();

        this.edit_form = $( "<div>" ).addClass('ui-heurist-bg-light')
        .appendTo( this.element );

        this.reg_message = $( "<div>" )
        .appendTo( this.element );

        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);

        this._refresh();

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash,
    // the widget is initialized; this includes when the widget is created.
    _init: function() {

        if(this.edit_form.is(':empty') ){

            var that = this;

            this.edit_form.load(top.HAPI4.basePathV4+"hclient/widgets/profile/profile_edit.html?t="+(new Date().getTime()),
                function(){
                    
                    that.edit_form.css('overflow','hidden');
                    
                    //find all labels and apply localization
                    that.edit_form.find('label').each(function(){
                        $(this).html(top.HR($(this).html()));
                    });
                    var allFields = that.edit_form.find('input');

                    that.edit_form.find( "#accordion" ).accordion({collapsible: true, heightStyle: "content", active: false});

                    function __doSave(){
                        that._doSave();
                    }

                    that.options.isregistration = !(Number(that.options.ugr_ID)>0 || top.HAPI4.is_admin());
                    if(that.options.isregistration){
                        
                        if(top.HEURIST4.util.isempty(top.HAPI4.sysinfo.dbowner_email)){ //new db creation and not logged in
                            $("#contactDetails").html(top.HR('Email to')+': System Administrator '+
                                '<a href="mailto:'+top.HAPI4.sysinfo.sysadmin_email+'">'+top.HAPI4.sysinfo.sysadmin_email+'</a>');
                        }else{
                            $("#contactDetails").html(top.HR('Email to')+': '+top.HAPI4.sysinfo.dbowner_name+'  '+
                                '<a href="mailto:'+top.HAPI4.sysinfo.dbowner_email+'">'+top.HAPI4.sysinfo.dbowner_email+'</a>');
                        }
                        
                    }else{
                        $("#contactDetails").html('');
                    }

                    if(that.options.isdialog){

                        that.edit_form.dialog({
                            autoOpen: false,
                            height: 630,
                            width: 740,
                            modal: true,
                            resizable: true,
                            title: '',
                            buttons: [
                                {text:top.HR(that.options.isregistration?'Register':'Save'), click: __doSave, id:'btn_save'}, //, disabled:(isreg)?'disabled':''
                                {text:top.HR('Cancel'), click: function() {
                                    $( this ).dialog( "close" );
                                }}
                            ],
                            close: function() {
                                if(that.options.needclear){
                                    allFields.val( "" ).removeClass( "ui-state-error" );
                                }
                            },
                            open: function(){
                                enable_register(!that.options.isregistration);
                            }
                        });
                    }

                    that._init();

            });

        }else{

            this._fromDataToUI();

        }

    },

    _setOption: function( key, value ){
        if(key==='ugr_ID'){
            this.options.ugr_ID = value;
            this._init();
        }
    },

    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

    },
    //
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        //this.select_rectype.remove();
    },

    //----
    _fromDataToUI: function(){

        var allFields = this.edit_form.find('input, textarea');

        allFields.val( "" ).removeClass( "ui-state-error" );

        /*if(this.options.ugr_ID == top.HAPI4.currentUser.ugr_ID){

        this.options.edit_data = top.HAPI4.currentUser;

        }else */
        if(Number(this.options.ugr_ID)>0){

            if(this.options.edit_data && this.options.edit_data['ugr_ID']==this.options.ugr_ID) {
                this.options.edit_data['ugr_Password']='';
            }else{
                var that = this;
                top.HAPI4.SystemMgr.user_get( { UGrpID: this.options.ugr_ID},
                    function(response){
                        var  success = (response.status == top.HAPI4.ResponseStatus.OK);
                        if(success){
                            that.options.edit_data = response.data;
                            if(that.options.edit_data && that.options.edit_data['ugr_ID']==that.options.ugr_ID){
                                that._fromDataToUI();
                            }else{
                                top.HEURIST4.msg.showMsgErr("Unexpected user data obtained from server");
                            }
                        }else{
                            top.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
                return;
            }

            this.edit_form.find("#ugr_Password").removeClass('mandatory');
            this.edit_form.find(".mode-registration").hide();
            this.edit_form.find(".mode-edit").show();
        }else{
            if(!this.options.edit_data) this.options.edit_data = {};
            this.edit_form.find("#ugr_Password").addClass('mandatory');
            this.edit_form.find(".mode-edit").hide();

            if(Number(this.options.ugr_ID)>0 || top.HAPI4.is_admin()){ //create new user by admin
                this.edit_form.find(".mode-registration").hide();
            }else{ //registration
                this.edit_form.find(".mode-registration").show();

                //init captcha
                this.edit_form.find('#imgdiv').show();
                /*that.edit_form.find('#btnCptRefresh')
                .button({text:false, icons:{ secondary: "ui-icon-refresh" }})
                .show()
                .click( __refreshCaptcha );*/

                this._refreshCaptcha();
            }
        }

        //hide enable field
        if(top.HAPI4.is_admin()){
            this.edit_form.find(".mode-admin").show();
        }else{
            this.edit_form.find(".mode-admin").hide();
        }

        for(id in this.options.edit_data){
            if(!top.HEURIST4.util.isnull(id)){
                var inpt = this.edit_form.find("#"+id).val(this.options.edit_data[id]);
                //if(inpt){                    inpt.val(this.options.edit_data[id]);                  }
            }
        }


        if(this.options.isdialog){
            this.edit_form.dialog('option', 'title',
                Number(this.options.ugr_ID)>0 ? top.HR('Edit profile')+': '+this.options.edit_data['ugr_FirstName'] + ' ' + this.options.edit_data['ugr_LastName'] //this.options.edit_data['ugr_FullName']
                : top.HR('Registration')  );
            this.edit_form.dialog("open");
            this.edit_form.parent().addClass('ui-dialog-heurist');

        }
    },

    _refreshCaptcha: function(){
        var that = this;
        var $dd = that.edit_form.find('#imgdiv');
        var id = top.HEURIST4.util.random();
        if(true){
            $dd.load(top.HAPI4.basePathV4+'hserver/utilities/captcha.php?id='+id);
        }else{
            $dd.empty(); //find("#img").remove();
            $('<img id="img" src="hserver/utilities/captcha.php?img='+id+'"/>').appendTo($dd);
        }
    },

    _doSave: function(){

        var that = this;
        var allFields = this.edit_form.find('input, textarea');
        var err_text = '';

        // validate mandatory fields
        allFields.each(function(){
            var input = $(this);
            if(input.hasClass('mandatory') && input.val()==''){
                input.addClass( "ui-state-error" );
                err_text = err_text + ', '+that.edit_form.find('label[for="' + input.attr('id') + '"]').html();
            }
        });
        if(this.options.isregistration){
            var ss = top.HEURIST4.msg.checkLength2( this.edit_form.find("#ugr_Captcha"), '', 1, 0 );
            if(ss!=''){
                err_text = err_text + ', Answer question below to prove you are human';
            }
        }

        if(err_text==''){
            // validate email
            // From jquery.validate.js (by joern), contributed by Scott Gonzalez: http://projects.scottsplayground.com/email_address_validation/
            var email = this.edit_form.find("#ugr_eMail");
            var bValid = top.HEURIST4.msg.checkRegexp( email, /^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i );
            if(!bValid){
                err_text = err_text + ', '+top.HR('Wrong email format');
            }

            // validate login
            var login = this.edit_form.find("#ugr_Name");
            if(!top.HEURIST4.msg.checkRegexp( login, /^[a-z]([0-9a-z_@.])+$/i)){
                err_text = err_text + ', '+top.HR('Wrong user name format. It may consist of ')
                    +'a-z, 0-9, _, @ begin with a letter';   // "Username may consist of a-z, 0-9, _, @, begin with a letter."
            }else{
                var ss = top.HEURIST4.msg.checkLength2( login, "user name", 3, 60 );
                if(ss!=''){
                    err_text = err_text + ', '+ss;
                }
            }
            // validate passwords
            var password = this.edit_form.find("#ugr_Password");
            var password2 = this.edit_form.find("#password2");
            if(password.val()!=password2.val()){
                err_text = err_text + ', '+top.HR(' Passwords are different');
                password.addClass( "ui-state-error" );
            }else  if(password.val()!=''){
                /* restict password to alphanumeric only - removed at 2016-04-29
                if(!top.HEURIST4.msg.checkRegexp( password, /^([0-9a-zA-Z])+$/)){  //allow : a-z 0-9
                    err_text = err_text + ', '+top.HR('Wrong password format');
                }else{*/
                var ss = top.HEURIST4.msg.checkLength2( password, "password", 3, 16 );
                if(ss!=''){
                    err_text = err_text + ', '+ss;
                }
                
            }

            if(err_text!=''){
                err_text = err_text.substring(2);
            }


        }else{
            err_text = top.HR('Missing required field(s)')+': '+err_text.substring(2);
        }

        if(err_text==''){
            // fill data with values from UI
            allFields.each(function(){
                var input = $(this);
                if(input.attr('id').indexOf("ugr_")==0){ // that.options.edit_data[input.attr('id')]!=undefined)

                    if(input.attr('type') === "checkbox"){
                        that.options.edit_data[input.attr('id')] = input.is(':checked')?'y':'n';
                    }else{
                        that.options.edit_data[input.attr('id')] = input.val();
                    }
                }
            });

            that.options.edit_data['ugr_Type'] = 'user';
            that.options.edit_data['ugr_IsModelUser'] = (that.options.edit_data['ugr_IsModelUser']=='y')?1:0;

            if( !top.HEURIST4.util.isnull(that.options.callback) && $.isFunction(that.options.callback) ){

                that.edit_form.dialog("close");
                that.options.callback.call(that);

            }else{

                top.HAPI4.SystemMgr.user_save( that.options.edit_data,
                    function(response){
                        var  success = (response.status == top.HAPI4.ResponseStatus.OK);
                        if(success){
                            if(that.options.isdialog){
                                that.edit_form.dialog("close");
                                if(that.options.isregistration){
                                    top.HEURIST4.msg.showMsgDlgUrl(top.HAPI4.basePathV4+"hclient/widgets/profile/profile_regmsg.html?t="+(new Date().getTime()),null,'Confirmation');
                                }else{
                                    top.HEURIST4.msg.showMsgDlg("User information has been saved successfully");
                                }

                            }
                        }else{
                            top.HEURIST4.msg.showMsgErr(response);
                            if(that.options.isregistration){
                                that.edit_form.find("#ugr_Captcha").val('');
                                that._refreshCaptcha();
                            }
                        }
                    }
                );

            }

        }else{
            top.HEURIST4.msg.showMsgErr(err_text);
            /*var message = $dlg.find('.messages');
            message.html(err_text).addClass( "ui-state-highlight" );
            setTimeout(function() {
            message.removeClass( "ui-state-highlight", 1500 );
            }, 500 );*/
        }

    },

    open:function(){
        this.edit_form.dialog("open");
    }


});

//these functions is used in edit_profile.html
function autofill_login(value){
    var ele = $('#ugr_Name');
    if(ele && ele.val()==''){
        ele.val(value);
    }
}
function enable_register(value){
    var that = this;
    var ele = $('#btn_save');
    if(ele){
        if(value){
            ele.removeAttr("disabled");
            ele.removeClass("ui-button-disabled ui-state-disabled");
        } else {
            ele.attr("disabled", "disabled");
            ele.addClass("ui-button-disabled ui-state-disabled");
        }
    }
}
