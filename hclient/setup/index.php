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
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
<!--
<script>window.hWin = window;</script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/localization.js"></script>
-->
<!-- CSS -->
<?php include dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php'; ?>

<script type="text/javascript">

    var sysadmin_email = '<?php echo HEURIST_MAIL_TO_ADMIN; ?>';
    
    function _showStep2(){

        $('.center-box').hide();

        if(!$('#btnRegisterCancel').button('instance')){

            $('.registration-form').children('div').css('padding-top','5px');

            $('.registration-form').find('.header').each(function(idx, item){

                item = $(item);
                var ele = item.next('input');
                if(ele.length==0) ele = item.next('textarea');
                if(ele.length==1){
                    ele.attr({autocomplete:'nope', placeholder:item.text()});
                    item.hide();
                    //ele.value(item.text()).attr('data-heder',item.text());
                    //ele.on({focus:function(){ if(this.value==$(this).attr('data-heder')) this.value = '';}});
                }

            });

            var ele = $('.center-box.screen2 label[for="ugr_Captcha"]')
            ele.text('1+4+5=');
            ele.parent().css({
                display: 'inline-block', float: 'left', 'min-width': 90, width: 90});            

            $('#btnRegisterDo').button().on({click:_showStep3});
            $('#btnRegisterCancel').button().on({click:function(){
                $('.center-box').hide();
                $('.center-box.screen1').show();
            }});


            $("#contactDetails").html('Email to: System Administrator '+
                '<a href="mailto:'+sysadmin_email+'">'+sysadmin_email+'</a>');

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


        }
        $('.center-box.screen2').show();


    }
    
    function _showStep3(){
        
        //@todo verify registration form
        
        $('.center-box').hide();

        var user_name = $('#ugr_Name').val();
        var ele = document.getElementById("uname");
        ele.value = user_name.substr(0,5).replace(/[^a-zA-Z0-9$_]/g,'');
        $("#dbname").focus();        
        
        $('.center-box.screen3').show();
    }
    
    function _showStep4(){

        //@todo verify database name
        
        $('.center-box').hide();
        $('.center-box.screen4').show();
        
        setTimeout(_showStep5, 3000);        
        
    }
    

    function _showStep5(){
        $('.center-box').hide();
        $('.center-box.screen5').show();
    }

    function _showStep6(){
        $('.center-box').hide();
        $('.center-box.screen6').show();
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
    
    // if hAPI is not defined in parent(top most) window we have to create new instance
    $(document).ready(function() {
        
        $('.button-registration').button().on({click:_showStep2});
        
        $('#btnCreateDatabase').button().on({click:_showStep4});
        $('#btnGetStarted').button().on({click:_showStep6});

    });
</script>
<style>
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
.center-box{
    background: #FFFFFF 0% 0% no-repeat padding-box;
    box-shadow: 0px 1px 3px #00000033;
    border: 1px solid #707070;
    border-radius: 4px;
    
    width: 800px;
    height: 480px;
    margin: 3% auto;  
    
    padding: 5px 30px 10px;  
}
.center-box h1, .center-box h3, .center-box .header{
    color: #7B4C98;
    font-weight:bold;
}
.center-box .helper{
    color: #00000099    
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


</style>


</head>
<body>
    <div class="ent_wrapper" style="min-width:1400px;min-height:675px;">
        <div class="ent_header" style="height:90px;padding: 10px 40px;">
            <div class="logo_intro" style="float:left"></div>
            <div style="float:left;font-style:italic;padding:34px">
                Designed by researchers, for researchers, Heurist reduces complex underlying decisions to simple, logical choices
            </div>
            <div style="float:right;padding:34px">
                <a href="https://heuristnetwork.org" target="_blank">Heurst Network website</a>
            </div>
        </div>
        <div class="ent_content_full bg_intro" style="top:120px;border-top:2px solid #323F50">
            
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
                
                <div class="entry-box">
                    <h3>Existing Users</h3>
                    <div style="display: inline-block;width:50%">
                        If you are already a user of another database on this server, we suggest logging into that database and creating your new database via the Administration menu, as this will carry over your login information from the existing database.
                    </div>
                    <div style="display: inline-block;line-height: 16px;padding-left: 20px;">
                        <div class="header" style="font-size:smaller">Find your database</div>
                        <div><input type="text"><button class="ui-button-action">Go</button></div>
                        <div style="font-size:smaller">You will be redirected to the Heurist database upon your selection</div>
                        <div style="font-size:smaller"><a href="#">See all databases on server</a></div>
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
                        <!-- <label class="mode-edit">Leave blank for no change</label> -->
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
                        <div class="header" style="text-align:right;padding-right:4px"><label for="ugr_Captcha">
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
                        <textarea id="ugr_Interests" name="ugr_Interests" rows="2" cols="80" style="width:35em;"
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
                    <a href="#" style="padding-left:20px;"> Terms and Conditions of Use</a>
                </div>

                <div style="text-align:center;padding:20px">
                
                    <button id="btnRegisterDo" disabled class="ui-button-disabled ui-state-disabled">Register</button>
                    <button id="btnRegisterCancel" >Cancel</button>
                
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
                    <span class="ui-icon ui-icon-loading-status-planet rotaste" style="height: 300px;width: 300px;font-size: 800%;color: rgb(79, 129, 189);"></span>
                </div>
            </div>

            <!-- SCREEN#5 Success  --> 
            <div class="center-box screen5">
                <h1>Welcome</h1>
            
                <div class="entry-box">
                    <h3>Congratulations, your new database Casey_and_Lowe has been created</h3>
                    
                    <div style="padding:3px 0px">
                        <label style="text-align:right;min-width:180px;display:inline-block">Owner:</label>
                        <span style="font-weight:bold">johnson</span>
                    </div>
                    
                    <div style="padding:3px 0px">
                        <label style="text-align:right;min-width:180px;display:inline-block">Your new database address:</label>
                        <span style="font-weight:bold">https://heuristplus.sydney.edu.au/h5-alpha?db=Casey_and_Lowe</span>
                    </div>
                    
                    <div style="font-weight:bold;padding:3px 0px 10px 180px">
                        We suggest bookmarking this address for future access
                    </div>
                    
                    <div style="text-align:center;">
                        <button class="ui-button-action" id="btnGetStarted">Get Started</button>
                    </div>
                </div>

                <div class="helper">                
                    After logging in to your new database, we suggest visiting the Design menu to customise the structure of your database. You can modify the database structure repeatedly as your needs evolve without invalidating data already entered.
                </div>
            
            </div>     

            <!-- SCREEN#6 Getting started --> 
            <div class="center-box screen6">
                <h1>Getting started</h1>
                
                    <div style="text-align:center;">
                        <button class="ui-button-action" id="btnGetStarted">Continue</button>
                    </div>
            </div>
            
            <!-- SCREEN#7 Error  --> 

            
        </div>
    </div>
</body>
</html>