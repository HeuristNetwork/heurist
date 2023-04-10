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

    $sp = $system->is_saml_authorisation($_REQUEST['db']); //'default-sp';
    
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
            $attr = array();
        }else{
            $attr = $as->getAttributes();
        }

            if(count($attr)==0){
                $attr = 
                array
                (
                    'uid' => array
                    (
                        0 => 'BNF0017879'
                    ),

                    'mail' => array
                    (
                        0 => 'philippe.yard@bnf.fr'
                    ),

                    'displayName' => array
                    (
                        0 => 'Philippe YARD'
                    ),

                    'givenName' => array
                    (
                        0 => 'Philippe (PFVD)'
                    ),

                    'sn' => array
                    (
                        0 => 'YARD (PFVD)'
                    ),

                    'department' => array
                    (
                        0 => 'DSR/DSI/SED/B2I'
                    ),
                );      
            }      

            //$idp = $as->getAuthData('saml:sp:IdP');
            //$nameId = $as->getAuthData('saml:sp:NameID')['Value'];

            //find user in sysUGrps by email, if not found add new one
            if( $system->init( @$_REQUEST['db'] ) ){ 

                $user_id = mysql__select_value($system->get_mysqli(),'SELECT ugr_ID FROM sysUGrps WHERE ugr_eMail="'
                    .$attr['mail'][0].'"');

                if(!($user_id>0)){
                    //add new user
                    //$attr['uid'][0]
                    // displayName, givenName, sn, department
                    $record = array('ugr_ID'=>-1, 'ugr_Type'=>'user', 
                        'ugr_Name'=>$attr['uid'][0], //login
                        'ugr_eMail'=>$attr['mail'][0], 'ugr_Password'=>$attr['uid'][0], 
                        'ugr_FirstName'=>$attr['givenName'][0],
                        'ugr_LastName'=>$attr['sn'][0],
                        'ugr_Department'=>@$attr['department'][0],
                        'ugr_Organisation'=>'na',
                        'ugr_Interests'=>'na',
                        'ugr_Enabled'=>'y' );
                    $user_id = user_Update($system, $record, true);

                }
                if($user_id>0){
                    if($system->doLogin($user_id, 'x', 'remember', true)){
                        //after login - close parent dialog and reload CurrentUserAndSysInfo
                        //$res = $system->getCurrentUserAndSysInfo();
                        //$res = json_encode($res);
                        ?>
                        <html>
                        <body>
                        <script>
                            window.onload = function(){
                                setTimeout(function(){window.close('<?php echo 'ok';?>'); }, 500);    
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
if($msg && @$msg['message']){
    print $msg['message'];     
}else{
    print 'Indefenite error';     
}
?>
