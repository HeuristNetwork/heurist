<?php
/**
* USaml.php - SimpleSAMLphp Utilities
*
* This file provides functions to integrate SimpleSAMLphp for Single Sign-On (SSO)
* capabilities within the Heurist application. It handles SAML-based login
* and logout procedures.
*
* Key functions include:
* - samlLogin: Initiates SAML authentication, registers/logs in users based on SAML attributes.
* - samlLogout: Terminates the local Heurist session and initiates SAML logout.
*
* @package     Heurist academic knowledge management system
* @subpackage  hserv\utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

$saml_script = '/var/simplesamlphp/lib/_autoload.php';
$is_debug = true;
if(file_exists($saml_script)){
    require_once $saml_script;
    $is_debug = false;
}

/**
 * Handles SAML logout.
 * Destroys the local Heurist session and then redirects to the SimpleSAMLphp logout endpoint.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param string $sp The Service Provider identifier for SimpleSAMLphp.
 * @param string $back_url The URL to return to after SAML logout is complete.
 * @return void This function typically causes a redirect and does not return.
 */
function samlLogout($system, $sp, $back_url)
{
    if($system->doLogout()){ //destroy session
        $system->userLogActivity('Logout');
        $as = new \SimpleSAML\Auth\Simple($sp);
        //$as = new SimpleSAML_Auth_Simple($sp);
        $as->logout(["ReturnTo" => $back_url]);
    }
}

/**
 * Handles SAML login.
 * Checks if the user is authenticated via SAML. If not, and $require_auth is true,
 * it initiates the SAML authentication process.
 * If authenticated, it retrieves user attributes, attempts to find or register a local Heurist user
 * based on these attributes (email, uid), and logs them in.
 *
 * @global bool $is_debug Debug flag (though not directly used in logic, it's declared global).
 * @param \hserv\System $system The Heurist system object.
 * @param string $sp The Service Provider identifier for SimpleSAMLphp.
 * @param string $dbname The name of the Heurist database.
 * @param bool $require_auth If true, redirects to SAML IdP for authentication if not already authenticated.
 *                           If false, returns 0 if not authenticated.
 * @param bool $noframe Optional. If true, loads Heurist again after successful login (intended for non-framed context).
 *                      Otherwise, handles login within the existing page flow. Defaults to false.
 * @return int The Heurist user ID (ugr_ID) if login is successful, or 0 if not authenticated (and $require_auth is false) or on error.
 *             If $require_auth is true and user is not authenticated, this function will cause a redirect.
 *             If $noframe is true and login fails, it includes an info page.
 */
function samlLogin($system, $sp, $dbname, $require_auth, $noframe=false){
    global $is_debug;

    $user_id = 0;
    $errMessage = null;
    $attr = null;

    $as = new \SimpleSAML\Auth\Simple($sp);
    //$as = new SimpleSAML_Auth_Simple($sp);
    if(!$as->isAuthenticated()){

        if($require_auth){
            $as->requireAuth();//after saml login - it returns to this page again
            exit;
        }else{
            $errMessage = 'Not externally authenticated';
        }
    }

    if($errMessage==null){

        $attr = $as->getAttributes();

        if(isEmptyArray($attr)){
            $errMessage = 'External authentication returns empty attributes. Please contact Service provider admin';
        }
    }

    //$idp = $as->getAuthData('saml:sp:IdP');
    //$nameId = $as->getAuthData('saml:sp:NameID')['Value'];

    //find user in sysUGrps by email and/or uid
    if(!isEmptyArray($attr) && ($system->isInited() || $system->init( $dbname )) ){

            $mysqli = $system->getMysqli();

            $attr_mail = @$attr['mail'][0]?$attr['mail'][0]:@$attr['urn:oid:0.9.2342.19200300.100.1.3'][0];
            $attr_uid = @$attr['uid'][0]?$attr['uid'][0]:@$attr['urn:oid:0.9.2342.19200300.100.1.1'][0];

            $query = 'SELECT ugr_ID,ugr_eMail,usr_ExternalAuthentication FROM sysUGrps where usr_ExternalAuthentication is not null';
            $res = $system->getMysqli()->query($query);
            if ($res){
                while ($row = $res->fetch_row()){
                    $prm = json_decode($row[2],true);
                    if( @$prm[$sp]
                        && ($prm[$sp]['uid']=='' || $prm[$sp]['uid']==$attr_uid)
                        && (@$prm[$sp]['mail']=='n' || $row[1]==$attr_mail) ){

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

            $user_id = mysql__select_value($system->getMysqli(), $query);
            */

            //DEBUG  $user_id = 0;

            if(!($user_id>0)){

                if($system->settings->get('sys_AllowRegistration')){
                    //register new user
                    $givenName = @$attr['urn:oid:2.5.4.42'][0]?$attr['urn:oid:2.5.4.42'][0]:@$attr['givenName'][0];
                    $surName = @$attr['urn:oid:2.5.4.4'][0]?$attr['urn:oid:2.5.4.4'][0]:@$attr['sn'][0];

                    list($givenName2, $surName2) = explode(' ',
                        @$attr['displayName'][0]?$attr['displayName'][0]
                                                :@$attr['urn:oid:2.16.840.1.113730.3.1.241'][0]);

                    if(!$givenName){
                        $givenName = $givenName2?$givenName2:'Unknown';
                    }
                    if(!$surName){
                        $surName = $surName2?$surName2:'Unknown';
                    }

                    $ext_auth = array();
                    $ext_auth[$sp] = array('uid'=>$attr_uid, 'mail'=>'y');

                    $bytes = random_bytes(5);
                    $rand_pwd = bin2hex($bytes);

                    // displayName, givenName, sn, department
                    $record = array('ugr_ID'=>-1, 'ugr_Type'=>'user',
                        'ugr_Name'=>$attr_uid, //login
                        'ugr_eMail'=>$attr_mail, 'ugr_Password'=>$rand_pwd,
                        'ugr_FirstName'=>$givenName, //$attr['givenName'][0],
                        'ugr_LastName'=>$surName,  //$attr['sn'][0],
                        'ugr_Department'=>'na',
                        'ugr_Organisation'=>'na',
                        'ugr_Interests'=>'na',
                        //DEBUG 'ugr_IncomingEmailAddresses'=>substr(print_r($attr,true),0,3999),
                        'ugr_Enabled'=>'y',
                        'usr_ExternalAuthentication'=> json_encode($ext_auth) );
                    $user_id = user_Update($system, $record, true);

                }else{
                    $errMessage = 'Heurist Database '.$dbname
                    .' does not have an user with provided attributes ('.$attr_uid.','.$attr_mail.')';
                }
            }

            /*
            REGISTER
            $user_id = mysql__select_value($system->getMysqli(),'SELECT ugr_ID FROM sysUGrps WHERE ugr_eMail="'
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


    if($noframe) { //load heurist again

        if($user_id>0){
            //perform authorization
            $system->doLogin($user_id, null, 'remember', true, false);//skip pwd check
            //reload page
            redirectURL(HEURIST_BASE_URL . '?db=' . HEURIST_DBNAME);
            //DEBUG $params = '&usr='.$attr_uid.'&usrid='.$user_id;
        }else{
            $try_login = true;
            $message = $errMessage.'<br><br> '
            .' Please <a class="login-link" reload="home">try login again</a>';

            //define('ERROR_REDIR', dirname(__FILE__).'/../../hclient/framecontent/infoPage.php');
            include_once dirname(__FILE__).'/../../hclient/framecontent/infoPage.php';
        }


    }elseif($errMessage!=null){

        $system->addError(HEURIST_REQUEST_DENIED, $errMessage );
    }

    return $user_id;

}
?>
