<?php
$saml_script = '/var/simplesamlphp/lib/_autoload.php';
$is_debug = true;
if(file_exists($saml_script)){
    require($saml_script);    
    $is_debug = false;
}
require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/../dbaccess/db_users.php');

$action = @$_REQUEST['a']; //$system->getError();

$system = new System();

$error = $system->dbname_check(@$_REQUEST['db']);

if($error){
    $system->addError(HEURIST_INVALID_REQUEST, $error);
}else{

    $sp = @$_REQUEST['sp'];
    if(!$sp) $sp = 'default-sp';
    
    if(!$sp){
        $system->addError(HEURIST_INVALID_REQUEST, 'Database '.$_REQUEST['db'].' does not support SAML authorisation');
    }else
    if ($action == "logout"){ //save preferences into session

        if($system->set_dbname_full(@$_REQUEST['db'])){

            $system->initPathConstants(@$_REQUEST['db']);
            $system->user_LogActivity('Logout');

            if($system->doLogout()){ //destroy session
                $as = new \SimpleSAML\Auth\Simple($sp);
                //$as = new SimpleSAML_Auth_Simple($sp);
                $as->logout(["ReturnTo" => $_SERVER['PHP_SELF']]);
            }
        }
    }else if ($action == "login"){

        if($is_debug){
            if(!@$_REQUEST['auth']){
                    ?>
                    <html>
                        <?php  echo $_SERVER['PHP_SELF']; ?><br>
                        <a href="<?php echo $_SERVER['PHP_SELF'].'?a=login&auth=1&db='.$_REQUEST['db']; ?>">LOGIN</a></html>
                    <?php
                    exit();
            }
        }else{    
            $as = new \SimpleSAML\Auth\Simple($sp);
            //$as = new SimpleSAML_Auth_Simple($sp);
            if(!$as->isAuthenticated()){
                $as->requireAuth();    //after login - it returns to this page again
                exit();
            }
        }
        
        if($is_debug){
            $attr = array('uid'=>array('1111'));//testing
        }else{
            $attr = $as->getAttributes();
        }

            if(count($attr)==0){
                $system->addError(HEURIST_ACTION_BLOCKED, 'External authentication returns empty attributes. Please contact Service provider admin');
            }      

            //$idp = $as->getAuthData('saml:sp:IdP');
            //$nameId = $as->getAuthData('saml:sp:NameID')['Value'];

            //find user in sysUGrps by email, if not found add new one
            if(count($attr)>0 && $system->init( @$_REQUEST['db'] ) ){
            
                $user_id = 0;
                $mysqli = $system->get_mysqli();
                
                $query = 'SELECT ugr_ID,ugr_eMail,usr_ExternalAuthentication FROM sysUGrps where usr_ExternalAuthentication is not null';
                $res = $system->get_mysqli()->query($query);
                if ($res){
                    while ($row = $res->fetch_row()){
                        $prm = json_decode($row[2],true);
                        if( @$prm[$sp] 
                            && ($prm[$sp]['uid']=='' || $prm[$sp]['uid']==$attr['uid'][0])
                            && (@$prm[$sp]['mail']=='n' || $row[1]==$attr['mail'][0]) ){
                        
                            $user_id = $row[0];
                            break;        
                        }
                    }
                    $res->close();
                }
                
                
                /*using MySQL feature to query fields with JSON - unfortunately it does not work for MariaDB
                $spe = $mysqli->real_escape_string($sp);
$query = 'SELECT ugr_ID FROM sysUGrps where usr_ExternalAuthentication is not null '
.' and  (usr_ExternalAuthentication->\'$."'.$spe.'".uid\'="" OR usr_ExternalAuthentication->\'$."'.$spe.'".uid\'="'.$mysqli->real_escape_string($attr['uid'][0]).'")'
.' and  (usr_ExternalAuthentication->\'$."'.$spe.'".mail\'="n" OR ugr_eMail="'.$mysqli->real_escape_string($attr['mail'][0]).'")';            

                $user_id = mysql__select_value($system->get_mysqli(), $query);
                */

                if(!($user_id>0)){
                    //show error
                    $system->addError(HEURIST_ACTION_BLOCKED, 'Heurist Database '.$_REQUEST['db'].' does not have an user with provided attributes');
                }
                /*
                $user_id = mysql__select_value($system->get_mysqli(),'SELECT ugr_ID FROM sysUGrps WHERE ugr_eMail="'
                    .$attr['mail'][0].'"');
                if(false && !($user_id>0)){
                    //add new user
                    //$attr['uid'][0]
                    
                    $bytes = random_bytes(5);
                    $rand_pwd = bin2hex($bytes);
                    
                    list($givenName, $surName) = explode(' ',$attr['displayName'][0]);
                    
                    // displayName, givenName, sn, department
                    $record = array('ugr_ID'=>-1, 'ugr_Type'=>'user', 
                        'ugr_Name'=>$attr['uid'][0], //login
                        'ugr_eMail'=>$attr['mail'][0], 'ugr_Password'=>$rand_pwd, 
                        'ugr_FirstName'=>$givenName, //$attr['givenName'][0],
                        'ugr_LastName'=>$surName,  //$attr['sn'][0],
                        'ugr_Department'=>@$attr['department'][0],
                        'ugr_Organisation'=>'na',
                        'ugr_Interests'=>'na',
                        'ugr_Enabled'=>'y' );
                    $user_id = user_Update($system, $record, true);
                }
                */
                
                if($user_id>0){
                    if($system->doLogin($user_id, 'x', 'remember', true)){
                        //after login - close parent dialog and reload CurrentUserAndSysInfo
                        //$res = $system->getCurrentUserAndSysInfo();
                        //$res = json_encode($res);
                        //pass to window.close('echo $res;');
                        ?>
                        <html>
                        <body>
                        <script>
                            window.onload = function(){
                                setTimeout(function(){window.close('ok'); }, 500);    
                            }                 
                        </script>
                        Authentification completed
                        </body>
                        </html>
                        
                        <?php
                        exit();                
                    }
                }

            }


    }else{
        //after logout - close parent window
        print '<script>window.close(false);</script>';
        exit();  
    }

}
//show error
$msg = $system->getError();
print '<html><body>';
if($msg && @$msg['message']){
    print $msg['message'];     
}else{
    print 'Indefenite error';     
}
?>
</body>
</html>

