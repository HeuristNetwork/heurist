<?php
/**
* Main setup sequence page
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

require_once(dirname(__FILE__)."/../../hsapi/System.php");

define('PDIR','../../');

// init main system class
//$system = new System();
//$system->defineConstants();
?>
<!--[if IE 9]><html class="lt-ie10" lang="en" > <![endif]-->
<html>
<head>

<title>Heurist Academic Collaborative Database (C) 2005 - 2020, University of Sydney</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

<meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE" />
<meta content="telephone=no" name="format-detection">

<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">
<link rel="shortcut icon" href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

<?php
if(($_SERVER["SERVER_NAME"]=='localhost'||$_SERVER["SERVER_NAME"]=='127.0.0.1')){
    ?>
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>
    <?php
}else{
    ?>
    <script src="https://code.jquery.com/jquery-1.12.2.min.js" integrity="sha256-lZFHibXzMHo3GGeehn1hudTAP3Sc0uKXBXAzHX1sjtk=" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>
<?php
}
?>

<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/localization.js"></script>
<script>
    window.hWin = window;
     //stub
    window.hWin.HR = function(res){ return regional['en'][res]?regional['en'][res]:res; } 
</script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
<!--
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
-->
<!-- CSS -->
<link rel="stylesheet" type="text/css" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<?php 
//include dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php'; 
?>

<script type="text/javascript">

    var baseURL = '<?php echo HEURIST_BASE_URL; ?>';
    var sysadmin_email = '<?php echo HEURIST_MAIL_TO_ADMIN; ?>';
    var all_databases = {};

    function _showStep2(){

        $('.center-box').hide();

        if(!$('#btnRegisterCancel').button('instance')){

            $('.registration-form').children('div').css('padding-top','5px');

            $('.registration-form').find('.header').each(function(idx, item){

                item = $(item);
                var ele = item.next('input');
                if(ele.length==0) ele = item.next('textarea');
                if(ele.length==1){
                    ele.attr('autocomplete','off')
                        .attr('autocorrect','off')
                        .attr('autocapitalize','none')
                        .attr('placeholder',item.text());
                    item.hide();
                    //ele.value(item.text()).attr('data-heder',item.text());
                    //ele.on({focus:function(){ if(this.value==$(this).attr('data-heder')) this.value = '';}});
                }

            });

            var ele = $('.center-box.screen2 label[for="ugr_Captcha"]')
            ele.parent().css({
                display: 'inline-block', float: 'left', 'min-width': 90, width: 90});            

            $('#btnRegisterDo').button().on({click: validateRegistration});
            $('#btnRegisterCancel').button().on({click: _showStep});


            $("#contactDetails").html('Email to: System Administrator<br>'+
                '<a style="padding-left: 60px;" href="mailto:'+sysadmin_email+'">'+sysadmin_email+'</a>');

            $('#cbAgree').on({'change':function(){
                var ele = $('#btnRegisterDo');
                if(ele){
                    if($(this).is(':checked')){
                        ele.removeAttr("disabled");
                        ele.removeClass("ui-button-disabled ui-state-disabled");
                        ele.addClass('ui-button-action');
                    } else {
                        ele.attr("disabled", "disabled");
                        ele.removeClass('ui-button-action');
                        ele.addClass("ui-button-disabled ui-state-disabled");
                    }
                }
            }});
            
            $('#ugr_eMail').on({'blur':function(){
                if($('#ugr_Name').val()=='') $('#ugr_Name').val(this.value)
            }});
            
            $('#showConditions').on({click: function(){ 
                if($('#divConditions').is(':empty')){
                    $('#divConditions').load('<?php echo PDIR; ?>documentation_and_templates/terms_and_conditions.html #content');
                }
                _showStep(7);
                return false;
            }});
            
            $('#btnTermsOK').button().on({click: function(){ $('#cbAgree').prop('checked',true).change(); _showStep2(); }});
            $('#btnTermsCancel').button().on({click: function(){ $('#cbAgree').prop('checked',false).change(); _showStep2(); }});

        }
        
        refreshCaptcha();
        
        $('.center-box.screen2').show();


    }

    function _showStep(arg){
        var step_no = 1;
        if(window.hWin.HEURIST4.util.isNumber(arg)){
            step_no = arg
        }else{
            step_no = $(arg.target).attr('data-step');
        }
        
        $('.center-box').hide();
        $('.center-box.screen'+step_no).show();
    }
   
    //
    //allow only alphanumeric characters for db name
    //
    function onKeyPress(event){

        event = event || window.event;
        var charCode = typeof event.which == "number" ? event.which : event.keyCode;
        if (charCode && charCode > 31)
        {
            var keyChar = String.fromCharCode(charCode);
            if(!/^[a-zA-Z0-9$_]+$/.test(keyChar)){
                event.cancelBubble = true;
                event.returnValue = false;
                event.preventDefault();
                if (event.stopPropagation) event.stopPropagation();
                return false;
            }
        }
        return true;
    }    
 
    //
    //
    //
    function refreshCaptcha(){
        $('#ugr_Captcha').val('');
        var $dd = $('#imgdiv');
        var id = window.hWin.HEURIST4.util.random();
        if(true){  //simple captcha
            var ele = $('.center-box.screen2 label[for="ugr_Captcha"]')
            ele.load('<?php echo PDIR; ?>hsapi/utilities/captcha.php?id='+id);
        }else{ //image captcha
            $dd.empty(); //find("#img").remove();
            $('<img id="img" src="hsapi/utilities/captcha.php?img='+id+'"/>').appendTo($dd);
        }
    }
    
    //
    //
    //
    function validateRegistration(){
        
        var regform = $('.registration-form');
        var allFields = regform.find('input, textarea');
        var err_text = '';

        // validate mandatory fields
        allFields.each(function(){
            var input = $(this);
            if(input.hasClass('mandatory') && input.val()==''){
                input.addClass( "ui-state-error" );
                err_text = err_text + ', '+regform.find('label[for="' + input.attr('id') + '"]').html();
            }
        });

        //remove/trim spaces
        var ele = regform.find("#ugr_Captcha");
        var val = ele.val().trim().replace(/\s+/g,'');
        if(val!=''){
            ele.val(val);
        }else{
            err_text = err_text + ', Humanity check';
        }

        if(err_text==''){
            // validate email
            // From jquery.validate.js (by joern), contributed by Scott Gonzalez: http://projects.scottsplayground.com/email_address_validation/
            var email = regform.find("#ugr_eMail");
            var bValid = window.hWin.HEURIST4.util.checkEmail(email);
            if(!bValid){
                err_text = err_text + ', '+window.hWin.HR('Email does not appear to be valid');
            }

            // validate login
            var login = regform.find("#ugr_Name");
            if(!window.hWin.HEURIST4.util.checkRegexp( login, /^[a-z]([0-9a-z_@.])+$/i)){
                err_text = err_text + ', '+window.hWin.HR('User name should contain ')
                    +'a-z, 0-9, _, @ and begin with a letter';   // "Username may consist of a-z, 0-9, _, @, begin with a letter."
            }else{
                var ss = window.hWin.HEURIST4.msg.checkLength2( login, "User name", 3, 60 );
                if(ss!=''){
                    err_text = err_text + ', '+ss;
                }
            }
            // validate passwords
            var password = regform.find("#ugr_Password");
            var password2 = regform.find("#password2");
            if(password.val()!=password2.val()){
                err_text = err_text + ', '+window.hWin.HR(' Passwords do not match');
                password.addClass( "ui-state-error" );
            }else  if(password.val()!=''){
                /* restrict password to alphanumeric only - removed at 2016-04-29
                if(!window.hWin.HEURIST4.util.checkRegexp( password, /^([0-9a-zA-Z])+$/)){  //allow : a-z 0-9
                    err_text = err_text + ', '+window.hWin.HR('Wrong password format');
                }else{*/
                var ss = window.hWin.HEURIST4.msg.checkLength2( password, "password", 3, 16 );
                if(ss!=''){
                    err_text = err_text + ', '+ss;
                }
                
            }

            if(err_text!=''){
                err_text = err_text.substring(2);
            }


        }else{
            err_text = window.hWin.HR('Missing required field(s)')+': '+err_text.substring(2);
        }        
        
        
        if(err_text==''){
            

            var user_name = $('#ugr_Name').val();
            var ele = document.getElementById("uname");
            ele.value = user_name.substr(0,5).replace(/[^a-zA-Z0-9$_]/g,'');
            
            _showStep(3);
            $("#dbname").focus();        
        }else{
            window.hWin.HEURIST4.msg.showMsgErr(err_text);
        }
    }
    
    //
    //
    //
    function doCreateDatabase(){

        var err_text = window.hWin.HEURIST4.msg.checkLength2( $("#dbname"), 'Database name', 1, 60 );
        
        if(err_text==''){
            
            var request = {};
            
            request['uname'] = $('#uname').val();
            request['dbname'] = $('#dbname').val();
            
            //get user registration data
            var inputs = $(".registration-form").find('input, textarea');
            inputs.each(function(idx, inpt){
                inpt = $(inpt);
                if(inpt.attr('name') && inpt.val()){
                    request[inpt.attr('name')] = inpt.val();
                }
            });
            
            var url = '<?php echo PDIR; ?>admin/setup/dboperations/createDB.php';
            
            _showStep(4);
            
            window.hWin.HEURIST4.util.sendRequest(url, request, null,
                function(response){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        
                        $('#newdbname').text(response.newdbname);
                        $('#newusername').text(response.newusername);
                        $('#newdblink').attr('href',response.newdblink).text(response.newdblink);
                        
                        if(response.warnings && response.warnings.length>0){
                            $('#div_warnings').html(response.warnings.join('<br><br>')).show();
                        }else{
                            $('#div_warnings').hide()
                        }

                        _showStep(5);
                           
                    }else{
                        //either wrong captcha or invalid registration values
                        if(response.status == window.hWin.ResponseStatus.ACTION_BLOCKED){
                            _showStep2(); //back to registration
                        }else{
                            _showStep(3); //back to db form
                        }
                        
                        window.hWin.HEURIST4.msg.showMsgErr(response, false);
                    }
                });
            
        }else{
            window.hWin.HEURIST4.msg.showMsgErr(err_text);
        }
        
    }
    
    //
    //
    //    
    function _getDatabases(){
        
        
            var url = '<?php echo PDIR; ?>hsapi/utilities/list_databases.php';
            
            var request = {format:'json'};
        
            window.hWin.HEURIST4.util.sendRequest(url, request, null,
                function(response){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        
                        all_databases = response.data;
                        
                    }else{
                        all_databases = {};
                        window.hWin.HEURIST4.msg.showMsgErr(response, false);
                    }
                    
                    if(Object.keys(all_databases).length>0){
                        _lookupDatabases();
                    }else{
                        $('.existing-user').hide();    
                    }
                    
                });
    }
    
    //
    // init db lookup
    //
    function _lookupDatabases(){
        $('#search_database')
            .attr('autocomplete','off')
            .attr('autocorrect','off')
            .attr('autocapitalize','none')
            .on({'keyup': function(event){
                
            var list_div = $('.list_div');
            
            var inpt = $(event.target);
            var sval = inpt.val().toLowerCase();

            if(sval.length>1){
                list_div.empty(); 
                var is_added = false;
                var len = Object.keys(all_databases).length;
                for (var idx=0;idx<len;idx++){
                    if(all_databases[idx].toLowerCase().indexOf(sval)>=0){
                        is_added = true; //res.push( all_databases[idx] );
                        $('<div class="truncate">'+all_databases[idx]+'</div>').appendTo(list_div);
                    }
                }
                
                list_div.addClass('ui-widget-content').position({my:'left top', at:'left bottom', of:inpt})
                    //.css({'max-width':(maxw+'px')});
                    .css({'max-width':inpt.width()+60});
                    if(is_added){
                        list_div.show();    
                    }else{
                        list_div.hide();
                    }
            }else{
                list_div.hide();    
            }                
                
                
        }   }); 
        
        $('#btnOpenDatabase').on({click:function(){
            
                var sval = $('#search_database').val().trim();
                if(sval==''){
                    window.hWin.HEURIST4.msg.showMsgFlash('Define database name'); 
                }else{
                    var len = Object.keys(all_databases).length;
                    for (var idx=0;idx<len;idx++){
                        if(all_databases[idx] == sval){
                            location.href = baseURL + '?db='+sval;
                            return;  
                        }
                    }
                    window.hWin.HEURIST4.msg.showMsgFlash('Database "'+sval+'" not found'); 
                }
            
        }});
    }
    
    //
    // init db lookup
    //
    function _showDatabaseList(){

        var list_div = $('.center-box.screen8').find('.db-list');
        list_div.empty();
        var len = Object.keys(all_databases).length;
        for (var idx=0;idx<len;idx++){
            $('<li class="truncate">'+all_databases[idx]+'</li>').appendTo(list_div);
        }

        $('.center-box.screen8').find('span.ui-icon').parent().hide();
        list_div.show();
        
        list_div.find('li').css('cursor','pointer').on({click:function(e){
                var dbname  = $(e.target).text();
                location.href = baseURL + '?db='+dbname;
        }});
        
        _showStep(8);

    }
 
    // if hAPI is not defined in parent(top most) window we have to create new instance
    $(document).ready(function() {
        
        $('.button-registration').button().on({click:_showStep2}); //goto step2
        
        $('#btnCreateDatabase').button().on({click: doCreateDatabase});
        $('#btnGetStarted').button().on({click: _showStep });  //goto step6
        $('#btnOpenHeurist').button({icon:'ui-icon-arrow-1-e',iconPosition:'end'}).on({click:function(){
            var url = $('#newdblink').text();
            $('.ent_wrapper').effect('drop',null,500,function(){
                location.href = url;    
            });
        }});
        
        $('#showDatabaseList').on({click: _showDatabaseList});  //goto step8
        
        $(document).on({click: function(event){
           if($(event.target).parents('.list_div').length==0) { $('.list_div').hide(); };
        }});
        
        _getDatabases();
        
        $('.list_div').on({click:function(e){
                    $(e.target).hide();
                    if($(e.target).hasClass('truncate')){
                        //navigate to database
                        $('#search_database').val($(e.target).text());
                        $('.list_div').hide();
                        //console.log($(e.target).text());
                    }
                }});
         
        _showStep(1);
    });
</script>
<style>
body {
    font-family: Helvetica,Arial,sans-serif;
    font-size: 14px;
    overflow:hidden;
}
a{
    outline: 0;
}
.ui-widget {
    font-size: 0.9em;
}
.text{
    padding: 0.2em;
}
.truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-wdith:230px;
}
.logo_intro{
    background-image: url("<?php echo PDIR;?>hclient/assets/v6/h6logo_intro.png");
    background-repeat: no-repeat !important;
    background-size: contain;
    width: 320px;
    height: 90px;
}
.bg_intro{
    background-color: rgba(218, 208, 228, 0.15); /*#DAD0E4*/
    background-image: url("<?php echo PDIR;?>hclient/assets/v6/h6logo_bg_200.png");
    background-repeat: no-repeat !important;
    background-position-x:right;       
    background-position-y:bottom;
    background-size: 400px;       
}
.center-box, .gs-box{
    background: #FFFFFF 0% 0% no-repeat padding-box;
    box-shadow: 0px 1px 3px #00000033;
    border: 1px solid #707070;
    border-radius: 4px;
    padding: 5px 30px 10px;  
}    
.center-box{
    width: 800px;
    height: 480px;
    margin: 3% auto;  
}
.center-box h1, .center-box h3, .center-box .header{
    color: #7B4C98;
    font-weight:bold;
}
.center-box .helper{
    color: #00000099;
    margin: 10px 0px;
}
.center-box .entry-box{
    background: #EBEBEB 0% 0% no-repeat padding-box;
    box-shadow: 0px 1px 3px #00000033;    
    border-radius: 4px;    
    padding: 5px 20px 20px;
    margin:20px 0;
}
.button-registration{
    background: #4477B9;
    color: #FFFFFF;
    font-size: 1em;   
    display: inline-block;
    vertical-align: bottom;    
    margin-left: 20px; 
}
.button-registration:hover{
    background: #3D9946;
    color: #FFFFFF;
}
.ui-button-action{
    font-weight:bold  !important;
    text-transform: uppercase;
    background: #3D9946;
    color: #FFFFFF;
}
.ui-button-action:hover{
    background: #4477B9;
    color: #FFFFFF;
}

.ent_wrapper{position:absolute;top:0;bottom:0;left:0;right: 0px;overflow:hidden;}
.ent_header,.ent_content_full{position:absolute; left:0; right:0;}
.ent_header{top:0;height:90px; padding:10px 40px;}
.ent_content_full{position:absolute;top:120px;bottom: 0;left:0;right: 1;overflow-y:auto;border-top:2px solid #323F50}

.video_lightbox_anchor_image{
    height:106px;
}
.db-list{
    column-width: 250px;
}
.db-list li {
    list-style: none;
    background-image: url("<?php echo PDIR;?>hclient/assets/database.png");
    background-position: left;
    background-repeat: no-repeat;
    padding-left: 30px;
    line-height: 17px;
}
</style>


</head>
<body>
    <div class="ent_wrapper" style="min-height:675px;">
        <div class="ent_header" style="min-width:1330px;">
            <div class="logo_intro" style="float:left"></div>
            <div style="float:left;font-style:italic;padding:34px">
                Designed by researchers, for researchers, Heurist reduces complex underlying decisions to simple, logical choices
            </div>
            <div style="float:right;padding:34px">
                <a href="https://heuristnetwork.org" target="_blank">Heurst Network website</a>
            </div>
        </div>
        <div class="ent_content_full bg_intro">
            
            <!-- SCREEN#1 --> 
            <div class="center-box screen1">
                <h1>Set Up a New Database</h1>

                <div class="helper">                
                    Create your first database on this Heurist server by registering as a user.<br>
                    As creator of a database you becomes the database owner and can manage the database and other database users.
                </div>
            
                <div class="entry-box">
                    <h3>New Users</h3>
                    <div style="display: inline-block">
                        Please register in order to define the user who will become the database owner and administrator.
                    </div>
                    <button class="button-registration">Register</button>
                </div>
                
                <div class="entry-box existing-user">
                    <h3>Existing Users</h3>
                    <div style="display: inline-block;width:50%">
                        If you are already a user of another database on this server, we suggest logging into that database and creating your new database via the Administration menu, as this will carry over your login information from the existing database.
                    </div>
                    <div style="display: inline-block;line-height: 16px;padding-left: 20px;">
                        <div class="header" style="font-size:smaller">Find your database</div>
                        <div>
                            <input id="search_database" class="text ui-widget-content ui-corner-all" value="" autocomplete="off"/>
                            <button class="ui-button-action" id="btnOpenDatabase">Go</button>
                        </div>
                        <div style="font-size:smaller">You will be redirected to the Heurist database upon your selection</div>
                        <div style="font-size:smaller"><a href="#" id="showDatabaseList" data-step="8">See all databases on server</a></div>
                    </div>
                    
                </div>

            
            </div>                        
            
            <!-- SCREEN#2 Registration form --> 
            <div class="center-box screen2">

                <h1>Register for Heurist</h1>

                <div class="registration-form"  style="display: inline-block;width:60%">
                    <div>
                        <div class="header"><label for="ugr_FirstName">Given name</label></div>
                        <input type="text" name="ugr_FirstName" id="ugr_FirstName" class="text ui-widget-content ui-corner-all mandatory"  maxlength="40" style="width:35em"/>
                    </div>
                    <div>
                        <div class="header"><label for="ugr_LastName">Family name</label></div>
                        <input type="text" name="ugr_LastName" id="ugr_LastName" class="text ui-widget-content ui-corner-all mandatory" maxlength="63" style="width:35em"/>
                    </div>
                    <div>
                        <div class="header"><label for="ugr_eMail">Email</label></div>
                        <input type="text" name="ugr_eMail" id="ugr_eMail" class="text ui-widget-content ui-corner-all mandatory" style="width:35em"/>
                    </div>
                    <div>
                        <div class="header"><label for="ugr_Name">Login name</label></div>
                        <input type="text" name="ugr_Name" id="ugr_Name" class="text ui-widget-content ui-corner-all mandatory" 
                            style="width:35em" maxlength="60" />
                        <div class="heurist-helper1" style="display:none;">
                            The login field is auto-populated with your email address.
                            <br />You may change it to a shorter name, eg. your given name or family name.
                        </div>
                    </div>
                    <div>
                        <div class="header"><label for="ugr_Password">Password</label></div>
                        <input type="password" name="ugr_Password" id="ugr_Password" class="text ui-widget-content ui-corner-all" style="width:10em;" maxlength="16" />&nbsp;
                        <i class="helper">3 - 16 characaters.</i>
                        <div style="font-size: smaller; margin-top: 2px;">
                        </div>
                    </div>
                    <div>
                        <div class="header"><label for="password2">Repeat password</label></div>
                        <input type="password" name="password2" id="password2" class="text ui-widget-content ui-corner-all" style="width:10em;" maxlength="16"/>
                        <div class="heurist-helper1"  style="display:none;">
                            Note: http:// web traffic is non-encrypted. You should not assume this server is secure.<br />
                            Therefore, please don't use an important password such as institutional or banking login.<br />
                            If you need to store data of a confidential nature, such as personal records, please<br />
                            contact the Heurist team or your system administrator about setting up a secured server.
                        </div>
                    </div>

                    <div class="mode-registration">
                        <div class="header" style="text-align:right;padding:4px"><label for="ugr_Captcha">
                            <span id="imgdiv" style="float:right;display:inline-block;display:none;"></span></label>
                        </div>
                        <div class="heurist-helper1">
                        </div>
                        <input type="text" name="ugr_Captcha" id="ugr_Captcha" class="text ui-widget-content ui-corner-all" style="width:10em;" maxlength="16" />
                        <i class="helper">&nbsp;&nbsp;&nbsp;to prove you're human</i>
                        <!--
                        -->
                    </div>

                    <div>
                        <div class="header"><label for="ugr_Organisation">Institution/company</label></div>
                        <input type="text" name="ugr_Organisation" id="ugr_Organisation" class="text ui-widget-content ui-corner-all mandatory" style="width:35em;" maxlength="120"/>
                        <div class="heurist-helper1" style="display:none;">
                            Enter 'None' if not affiliated with an institution or company.
                        </div>
                    </div>

                    <div>
                        <div class="header" style="vertical-align:top;"><label for="ugr_Interests">Research interests</label></div>
                        <textarea id="ugr_Interests" name="ugr_Interests" rows="2" cols="80" style="width:35em;resize:none"
                            class="text ui-widget-content ui-corner-all mandatory"></textarea>
                        <div class="heurist-helper1" style="display:none;">
                            Enter a concise description (up to 250 characters) of your research interests.
                        </div>
                    </div>

                    <div>
                        <div class="header" style="vertical-align:top;"><label for="ugr_URLs">Personal Website URLs</label></div>
                        <textarea id="ugr_URLs" name="ugr_URLs" rows="3" cols="80" style="width:35em"
                            class="text ui-widget-content ui-corner-all"></textarea>
                    </div>

                </div>
                <div class="entry-box" style="float: right;max-width: 270px;min-height: 229px;padding-top: 20px;">
                    Note: This system should not be used for the storage of confidential and/or personally-identifiable information, such as health information, without the express permission of the system managers.
                    <br><br>
                    <div id="contactDetails"></div>
                    <br><br>
                    <input type="checkbox" id="cbAgree"/><label for="cbAgree">I have read and agree to the</label><br>
                    <a href="#" style="padding-left:20px;" id="showConditions"> Terms and Conditions of Use</a>
                </div>

                <div style="text-align:center;padding:20px">
                
                    <button id="btnRegisterDo" disabled class="ui-button-disabled ui-state-disabled">Register</button>
                    <button id="btnRegisterCancel" data-step="1">Cancel</button>
                
                </div>

            </div>  

            <!-- SCREEN#3 Enter database name --> 
            <div class="center-box screen3">
                <h1>Set Up a New Database</h1>

                <div class="helper">                
                    Create your first database on this Heurist server by registering as a user.<br>
                    As creator of a database you becomes the database owner and can manage the database and other database users.
                </div>
            
                <div class="entry-box">
                    <h3>Enter a name for the database</h3>
                    
                    <div>
                        hdb_<input type="text" id="uname"  name="uname" class="text ui-widget-content ui-corner-all" 
                                maxlength="30" size="6" onkeypress="{onKeyPress(event)}"/>
                        _<input type="text" id="dbname"  name="dbname" class="text ui-widget-content ui-corner-all"
                                maxlength="64" size="30" onkeypress="{onKeyPress(event)}"/>
                        <button class="ui-button-action" id="btnCreateDatabase">Create Database</button>
                    </div>
                    
                </div>
                
                <div class="helper">                
                    Do not use punctuation except underscore, names are case sensitive.<br><br> 
                    <i>The user name prefix is editable, and may be left blank, but we suggest using a consistent prefix for<br> 
                       personal databases so that they are easily identified and appear together in the search for databases.</i>
                </div>
            
            </div>                        


            <!-- SCREEN#4 In progress --> 
            <div class="center-box screen4">
                <h1>Database is creating</h1>
                
                <div style="text-align: center;padding: 60px 0;">
                    <span class="ui-icon ui-icon-loading-status-circle rotate" style="height: 300px;width: 300px;font-size: 800%;color: rgb(79, 129, 189);"></span>
                </div>
            </div>

            <!-- SCREEN#5 Success  --> 
            <div class="center-box screen5">
                <h1>Welcome</h1>
            
                <div class="entry-box">
                    <h3>Congratulations, your new database <span id="newdbname"></span> has been created</h3>
                    
                    <div style="padding:5px 0px">
                        <label style="text-align:right;min-width:180px;display:inline-block">Owner:</label>
                        <span style="font-weight:bold" id="newusername"></span>
                    </div>
                    
                    <div style="padding:5px 0px">
                        <label style="text-align:right;min-width:180px;display:inline-block">Your new database address:</label>
                        <span style="font-weight:bold" id="newdblink"></span>
                    </div>
                    
                    <div style="font-weight:bold;padding:5px 0px 5px 183px">
                        We suggest bookmarking this address for future access
                    </div>
                    
                    <div class="ui-state-error" id="div_warnings" style="display:none;padding:10px;margin: 10px 0;">
                    </div>
                    
                    <div style="text-align:center;">
                        <button class="ui-button-action" id="btnGetStarted" data-step="6">Get Started</button>
                    </div>
                </div>

                <div class="helper">                
                    After logging in to your new database, we suggest visiting the Design menu to customise the structure of your database. You can modify the database structure repeatedly as your needs evolve without invalidating data already entered.
                </div>
            
            </div>     

            <!-- SCREEN#6 Getting started --> 
            <div class="center-box screen6" style="padding:0;border:none;width:1330;height:auto;margin:10px auto;background:none;box-shadow:none">

                <div class="gs-box" style="float:left;width:400px;height:538px">
                    <h1>Getting started</h1>

                    <div class="helper">                
                        The rich capabilities of Heurist are structured in the following four functional sets. These videos will give you an overview of each capability in Heurist. 
                    </div>

                    <div class="helper">                
                        This help will assist you in using Heurist to quickly create and populate databases, analyse datasets in multiple ways, and publish to external media. For additional help, refer to the <a href="https://heuristnetwork.org" target="_blank">Heurst website</a>.
                    </div>

                    <h3>The Iterative Database</h3>
                    <div style="text-align:center">
                        <img src="<?php echo PDIR;?>hclient/assets/v6/gs_diagram.png" width="320"/>
                    </div>
                    <br>

                    <div class="helper">                
                        Heurist’s comprehensive browser-based database-design environment lets you manage complex Humanities data through iterative revision. 
                    </div>
                    <div class="helper">                
                        Get started immediately with something small and simple, and then modify and extend your database structure as you become more comfortable with it or your data model and needs evolve.                
                    </div>

                </div>

                <div style="float:right;width:138px">
                    <button style="height:554px;width:100%" class="ui-button-action" id="btnOpenHeurist">Continue</button>
                </div>
                
                
                <div class="gs-box" style="margin:0px 10px;display: inline-block;height: auto;width:auto">

                    <div style="float:left">
                        <img src="<?php echo PDIR;?>hclient/assets/v6/gs_design.png" width="110"/>
                        <div>
                            <span style="font-size: large;width: 80px;padding-top: 6px;float: left;">Design</span>
                            <span class="header" style="max-width: 170px;display: inline-block;font-weight: normal;">
                                Extend / modify the database structure at any time
                            </span>         
                        </div>
                    </div>

                    <div class="entry-box" style="float:right;margin:0 0 0 10px;padding: 4px;width:380px">

                        <a style="float:left;margin-right: 10px;" href="https://www.youtube.com/watch?v=wuh9SRtE8eE&amp;width=640&amp;height=480" title="" target="_blank">
                            <img src="<?php echo HEURIST_BASE_URL;?>hclient/assets/v6/PresentationThumbnailIanJohnsonPlay-1.png" class="video_lightbox_anchor_image" alt="">
                        </a>                    

                        <h4 style="margin:0.5em">Design walkthrough</h4>
                        <div>
                            Introduces the basics of setting up a database and modifying it as your needs evolve
                        </div>                
                    </div>

                </div>

                <div class="gs-box" style="margin:10px 10px 0px 10px;display: inline-block;height: auto;width:auto">

                    <div style="float:left">
                        <img src="<?php echo PDIR;?>hclient/assets/v6/gs_import.png" width="110"/>
                        <div>
                            <span style="font-size: large;width: 80px;padding-top: 6px;float: left;">Populate</span>
                            <span class="header" style="max-width: 170px;display: inline-block;font-weight: normal;">
                                Enter data, Import files, synchronise with web services
                            </span>         
                        </div>
                    </div>

                    <div class="entry-box" style="float:right;margin:0 0 0 10px;padding: 4px;width:380px">

                        <a style="float:left;margin-right: 10px;" href="https://www.youtube.com/watch?v=wuh9SRtE8eE&amp;width=640&amp;height=480" title="" target="_blank">
                            <img src="<?php echo HEURIST_BASE_URL;?>hclient/assets/v6/PresentationThumbnailIanJohnsonPlay-1.png" class="video_lightbox_anchor_image" alt="">
                        </a>                    

                        <h4 style="margin:0.5em">Import walkthrough</h4>
                        <div>
                            How to import data from existing files and other data management systems
                        </div>                
                    </div>

                </div>

                <div class="gs-box" style="margin:10px 10px 0px 10px;display: inline-block;height: auto;width:auto">

                    <div style="float:left">
                        <img src="<?php echo PDIR;?>hclient/assets/v6/gs_explore.png" width="110"/>
                        <div>
                            <span style="font-size: large;width: 80px;padding-top: 6px;float: left;">Explore</span>
                            <span class="header" style="max-width: 170px;display: inline-block;font-weight: normal;">
                                Search, filter, list, visualise, analyse and create feeds
                            </span>         
                        </div>
                    </div>

                    <div class="entry-box" style="float:right;margin:0 0 0 10px;padding: 4px;width:380px">

                        <a style="float:left;margin-right: 10px;" href="https://www.youtube.com/watch?v=wuh9SRtE8eE&amp;width=640&amp;height=480" title="" target="_blank">
                            <img src="<?php echo HEURIST_BASE_URL;?>hclient/assets/v6/PresentationThumbnailIanJohnsonPlay-1.png" class="video_lightbox_anchor_image" alt="">
                        </a>                    

                        <h4 style="margin:0.5em">Explore walkthrough</h4>
                        <div>
                            The basics of adding records, filtering and using data in your database
                        </div>                
                    </div>

                </div>

                <div class="gs-box" style="margin:10px 10px 0px 10px;display: inline-block;height: auto;width:auto">

                    <div style="float:left">
                        <img src="<?php echo PDIR;?>hclient/assets/v6/gs_publish.png" width="110"/>
                        <div>
                            <span style="font-size: large;width: 80px;padding-top: 6px;float: left;">Publish</span>
                            <span class="header" style="max-width: 170px;display: inline-block;font-weight: normal;">
                                Build data-driven pages / websites and export data
                            </span>         
                        </div>
                    </div>

                    <div class="entry-box" style="float:right;margin:0 0 0 10px;padding: 4px;width:380px">

                        <a style="float:left;margin-right: 10px;" href="https://www.youtube.com/watch?v=wuh9SRtE8eE&amp;width=640&amp;height=480" title="" target="_blank">
                            <img src="<?php echo HEURIST_BASE_URL;?>hclient/assets/v6/PresentationThumbnailIanJohnsonPlay-1.png" class="video_lightbox_anchor_image" alt="">
                        </a>                    

                        <h4 style="margin:0.5em">Publish walkthrough</h4>
                        <div>
                            Building a website and selective publication and export of data from the database
                        </div>                
                    </div>

                </div>


            </div>            
 
 
            <!-- SCREEN#7 Terms and conditions --> 
             <div class="center-box screen7">
                <h1>Terms and conditions</h1>
                <div id="divConditions" style="font-size:x-small;max-height:350px"></div>
                <div style="text-align:center;padding:20px">
                    <button id="btnTermsOK" class="ui-button-action">I Agree</button>
                    <button id="btnTermsCancel">Cancel</button>
                </div>
            </div>     
            
            
            <!-- SCREEN#8 Terms and conditions --> 
            <div class="center-box screen8" style="width:1330;height:auto;margin:10px auto;">
                <h1>Databases</h1>
                
                <ul class="db-list" style="display:none">
                </ul>
                <div style="text-align: center;padding: 60px 0;">
                    <span class="ui-icon ui-icon-loading-status-circle rotate" style="height: 300px;width: 300px;font-size: 800%;color: rgb(79, 129, 189);"></span>
                </div>
            </div>            
            
        </div>
    </div>
    
    <div class="list_div ui-heurist-header" 
        style="z-index:999999999; height:auto; max-height:200px; padding:4px;cursor:pointer;display:none;overflow-y: auto"></div>
</body>
</html>