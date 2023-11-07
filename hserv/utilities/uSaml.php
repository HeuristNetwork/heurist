<?php
/**
* Simplesaml utilities
* 
* logout
* login
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
$saml_script = '/var/simplesamlphp/lib/_autoload.php';
$is_debug = true;
if(file_exists($saml_script)){
    require($saml_script);    
    $is_debug = false;
}

//
//
//
function samlLogout($system, $sp, $back_url)
{
    if($system->doLogout()){ //destroy session
        $system->user_LogActivity('Logout');
        $as = new \SimpleSAML\Auth\Simple($sp);
        //$as = new SimpleSAML_Auth_Simple($sp);
        $as->logout(["ReturnTo" => $back_url]);
    }
}

//
// $require_auth - true - opens saml login page 
//                 false -  returns 0 if not authenticated
//
function samlLogin($system, $sp, $dbname, $require_auth=true){
    global $is_debug;
  
    $user_id = 0;
    
    if($is_debug){
        if(!@$_REQUEST['auth'] && $require_auth){ //fake/debug authorization
                ?>
                <html>
                    <?php  echo htmlspecialchars($_SERVER['PHP_SELF']); ?><br>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']).'?a=login&auth=1&db='.htmlspecialchars($dbname); ?>">LOGIN</a>
                </html>
                <?php
                exit;
        }
    }else{    
        $as = new \SimpleSAML\Auth\Simple($sp);
        //$as = new SimpleSAML_Auth_Simple($sp);
        if(!$as->isAuthenticated()){
            
            if($require_auth){
                $as->requireAuth();    //after saml login - it returns to this page again
                exit;
            }else{
                $system->addError(HEURIST_REQUEST_DENIED, 'Not externally authenticated');
                return 0;
            }
        }
    }
    
    
    if($is_debug){
        $attr = array();
    }else{
        $attr = $as->getAttributes();
    }

    if($is_debug){
        //test login data
        $attr = array(
                    'uid'=>array('BNF0017879'),
                    'mail'=>array('aaaa.bbbb@bnf.fr'));
    }
    if(count($attr)==0){
        $system->addError(HEURIST_REQUEST_DENIED, 
            '<p>External authentication returns empty attributes. Please contact Service provider admin</p>');
    }      

        
        //$idp = $as->getAuthData('saml:sp:IdP');
        //$nameId = $as->getAuthData('saml:sp:NameID')['Value'];

    //find user in sysUGrps by email and/or uid
    if(count($attr)>0 && ($system->is_inited() || $system->init( $dbname )) ){
        
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
                $system->addError(HEURIST_REQUEST_DENIED, 'Heurist Database '.$dbname.' does not have an user with provided attributes');
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
        
    }
    
    return $user_id;
}
?>
