<?php
/**
* Simplesaml authentification
* 
* parameters:
* db - database name
* sp - service prodiver
* a - logout -  logout heurist and simplesaml
*   - login  - does saml authentication, closes dialog (javascript), returns user id as context
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/../dbaccess/db_users.php');
require_once (dirname(__FILE__).'/../utilities/utils_saml.php');

$action = @$_REQUEST['a']; //$system->getError();

$system = new System();
$dbname = @$_REQUEST['db'];
$error = System::dbname_check($dbname);

if($error){
    $system->addError(HEURIST_INVALID_REQUEST, $error);
}else{

    $sp = @$_REQUEST['sp'];
    if(!$sp) $sp = 'default-sp';
    
    if(!$sp){
        $system->addError(HEURIST_INVALID_REQUEST, 'Database '.$dbname.' does not support SAML authorisation');
    }else
    if ($action == "logout"){ //save preferences into session
    
        if($system->set_dbname_full($dbname)){

            $system->initPathConstants($dbname);
            
            samlLogout($system, $sp, $_SERVER['PHP_SELF']);
            exit();
        }
    }else if ($action == "login"){

            $user_id = samlLogin($system, $sp, $dbname, true);
            
            if($user_id>0){
                $msg = $user_id;
/*                
                if($system->doLogin($user_id, 'x', 'remember', true)){
                    //after login - close parent dialog and reload CurrentUserAndSysInfo
                    $res = $system->getCurrentUserAndSysInfo();
                    $res2 = json_encode($res);
                    //pass to window.close('echo $res;');
                    ?>
                    <html>
                    <head>
                    <script type="text/javascript" src="../../hclient/core/detectHeurist.js"></script>
                    <script>
                        window.hWin.HAPI4.currentUser = <?php echo json_encode($res['currentUser']); ?>;
                        window.hWin.HAPI4.sysinfo = <?php echo json_encode($res['sysinfo']); ?>;
                    
                        window.onload = function(){
console.log('Authentification completed ','<?php echo intval($user_id);?>');                                
                            setTimeout(function(){window.close(<?php echo intval($user_id);?>); }, 1000);    
                        }                 
                    </script>
                    </head>
                    <body>
                        Authentification is completed successfull.
                        <br>
                        <br>
                        ID: <?php echo htmlspecialchars($res['currentUser']['ugr_ID']);?>
                        User: <?php echo htmlspecialchars($res['currentUser']['ugr_FullName']);?>
                    </body>
                    </html>
                    
                    <?php
                    exit();                
                }
*/                
            }
    }else{
        //after logout - close parent window
        $msg = 'You are logged out';
    }

}
//show error
if(!isset($msg)){
    $msg = $system->getError();
    if(!($msg && @$msg['message'])){
        $msg = 'Indefenite error';     
    }
}
?>
<html>
<head>
<script>
    window.onload = function(){
console.log('Authentification completed ','<?php echo htmlspecialchars($msg);?>');                                
        setTimeout(function(){window.close("<?php echo htmlspecialchars($msg);?>");}, 1000);    
    }                 
</script>
</head>
<body>
    Authentification is completed
</body>
</html>