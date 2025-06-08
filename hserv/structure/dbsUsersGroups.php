<?php

    /**
    * Library for  User/Groups (sysUGrps) and User Preferences (from SESSION)
    *
    * user_ - prefix for functions
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
    * @author      Artem Osmakov   <osmakov@gmail.com>
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

/**
 * This file provides a library of functions for managing Users and Groups within the Heurist system.
 * These functions interact primarily with the `sysUGrps` table (for user and group definitions)
 * and `sysUsrGrpLinks` table (for user-to-group memberships and roles).
 *
 * Functionality includes:
 * - Retrieving user and group information by various criteria (ID, name, email).
 * - Managing user passwords, including reset mechanisms via PIN or random generation.
 * - Handling user preferences, stored in the session and database.
 * - User registration, profile updates, and associated email notifications.
 * - Synchronizing user credentials across linked Heurist databases.
 * - Managing system notifications for users.
 * - Handling credentials for external data repositories.
 * - Helper functions for access control checks.
 *
 * Most functions are prefixed with `user_`.
 *
 * @package     hserv\structure
 */

    /*
     * Note: The following constants appear to be commented out in the original source
     * and are likely not in active use. They might represent an older design
     * or a planned feature for abstracting table/field names.
     *
     * define('USERS_TABLE', 'sysUGrps');
     * define('USERS_ID_FIELD', 'ugr_ID');
     * // ... and other similar constants ...
     */
    /*
    define('USERS_TABLE', 'sysUGrps');
    define('USERS_ID_FIELD', 'ugr_ID');
    define('USERS_USERNAME_FIELD', 'ugr_Name');
    define('USERS_PASSWORD_FIELD', 'ugr_Password');
    define('USERS_FIRSTNAME_FIELD', 'ugr_FirstName');
    define('USERS_LASTNAME_FIELD', 'ugr_LastName');
    define('USERS_ACTIVE_FIELD', 'ugr_Enabled');
    define('USERS_EMAIL_FIELD', 'ugr_eMail');
    define('GROUPS_TABLE', 'sysUGrps');
    define('GROUPS_ID_FIELD', 'ugr_ID');
    define('GROUPS_NAME_FIELD', 'ugr_Name');
    define('GROUPS_TYPE_FIELD', 'ugr_Type');
    define('USER_GROUPS_TABLE', 'sysUsrGrpLinks');
    define('USER_GROUPS_USER_ID_FIELD', 'ugl_UserID');
    define('USER_GROUPS_GROUP_ID_FIELD', 'ugl_GroupID');
    define('USER_GROUPS_ROLE_FIELD', 'ugl_Role');
    */

    /**
    * user_getByField
    * user_getNamesByIds
    * user_getDbOwner - returns user #2
    * user_ResetPasswordRandom - Generates new passowrd and send it by email
    * user_HandleResetPin - reset password actions
    * user_ResetPassword - reset password via pin
    * user_updateLoginTime
    * user_getWorkgroups - Gets list of groups for given user
    * user_getAllWorkgroups - Gets short list of all groups ID=>Name
    * user_getWorkgroupMembers - Gets list of members for given group
    *
    * user_getDefaultPreferences - minimal set of preferences
    * user_setPreferences - save prefs into database
    * user_getPreferences - loads and returns prefs from database
    *
    * user_isApprovement - returns true if user is not approved
    * user_WorkSet
    * user_Update - adds user on registration
    * user_Validate - validates user info on save
    * user_SyncCommonCredentials - Updates (adds) user info into databases listed in sys_UGrpsDatabase
    *
    * user_EmailAboutNewUser
    * user_EmailApproval
    * user_getNotifications
    *
    * user_getRepositoryList - list of available/writeable external repositories for given user
    * user_getRepositoryCredentials2 - returns credentials for given service_id  (service_name+user_id)
    * user_getRepositoryCredentials - returns read/write credentials for given service and user_id  (for edit on client side)
    * user_saveRepositoryCredentials - Saves repository credentials in ugr_Preferences
    *
    */

    /**
     * Retrieves a user or group record by matching a specific field with a given value.
     *
     * Allows searching in the current database or an optionally specified external database
     * (if the MySQL user has appropriate cross-database permissions).
     *
     * @param \mysqli $mysqli The mysqli database connection object.
     * @param string $field The name of the field in `sysUGrps` to search against (e.g., 'ugr_Name', 'ugr_eMail').
     * @param mixed $value The value to search for in the specified field.
     * @param string|null $database (Optional) The name of an alternative database to query.
     *                              If null, queries the current database.
     * @return array|null An associative array representing the user/group record if found,
     *                    otherwise null.
     */
    function user_getByField($mysqli, $field, $value, $database=null){

        if($database!=null){
            list($database_full, $database ) = mysql__get_names($database);
            $database_full = '`'.$database_full.'`.';
        }else{
            $database_full = '';
        }

        $user = null;
        $query = 'select * from '.$database_full
            .'sysUGrps where '.$field.' = ?';

        $res = mysql__select_param_query($mysqli, $query, array('s',$value));

        if($res){
            $user =$res->fetch_assoc();
            $res->close();
        }
        return $user;

    }

    /**
     * Retrieves a user or group record by its ID (`ugr_ID`).
     * This is a convenience wrapper for `user_getByField`.
     *
     * @param \mysqli $mysqli The mysqli database connection object.
     * @param int $ugr_ID The User/Group ID.
     * @return array|null An associative array of the user/group record, or null if not found.
     */
    function user_getById($mysqli, $ugr_ID){
        return user_getByField($mysqli, 'ugr_ID', $ugr_ID);
    }

    /**
     * Retrieves the display names for a list of user/group IDs.
     *
     * For users, it concatenates `ugr_FirstName` and `ugr_LastName`.
     * For groups, it uses `ugr_Name`.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param array|string $ugr_IDs An array or comma-separated string of User/Group IDs.
     * @return array|false An associative array `[ugr_ID => display_name]` on success,
     *                     or `false` if input IDs are invalid or a database error occurs.
     */
    function user_getNamesByIds($system, $ugr_IDs){

        $ugr_IDs = prepareIds($ugr_IDs);
        if(!empty($ugr_IDs)){
            $mysqli = $system->getMysqli();
            $query = 'SELECT ugr_ID, IF(ugr_Type=\'workgroup\',ugr_Name, concat(ugr_FirstName, \' \', ugr_LastName)) '
            .' FROM sysUGrps WHERE ugr_ID in ('.implode(',',$ugr_IDs).')';
            return mysql__select_assoc2($mysqli, $query);
        }else{
            $system->addError(HEURIST_INVALID_REQUEST,'User ids are not defined');
            return false;
        }
    }


    /**
     * Retrieves the database owner's record (ugr_ID = 2) or a specific field from it.
     *
     * The database owner has special privileges in Heurist.
     *
     * @param \mysqli $mysqli The mysqli database connection object.
     * @param string|null $field (Optional) If specified, returns only the value of this field
     *                           from the owner's record (e.g., 'ugr_eMail'). If null, returns
     *                           the entire user record array.
     * @return array|mixed|null The full user record array, a specific field value, or null if
     *                          user ID 2 is not found or the specified field doesn't exist.
     */
    function user_getDbOwner($mysqli, $field=null)
    {
        $user = user_getById($mysqli, 2);
        if($user){
            if($field){
                if(@$user[$field]){
                    return $user[$field];
                }
            }else{
                return $user;
            }
        }
        return null;
    }

    /**
     * Resets a user's password to a randomly generated one and emails it to the user.
     *
     * The user can be identified by either username or email address.
     * It checks if SMTP is configured before attempting to send an email.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param string $username The username or email address of the user whose password is to be reset.
     * @return bool True on successful password reset and email sending, false otherwise.
     *              Errors are added to `$system`.
     */
    function user_ResetPasswordRandom($system, $username){
        if($username){
            $mysqli = $system->getMysqli();
            $user = user_getByField($mysqli, 'ugr_Name', $username);
            if(null==$user) {
                $user = user_getByField($system->getMysqli(), 'ugr_eMail', $username);
            }

            if(null==$user) {
                $system->addError(HEURIST_NOT_FOUND,  "It is not possible to recover password. Username / email, you specified, not found");

            }else{
                //do not update password if mail is not enabled
                if(false && !checkSmtp()){ //disabled - this function is very slow on intersect server
                    $system->addError(HEURIST_SYSTEM_CONFIG, 'Error_Mail_Recovery');
                    return false;
                }

                $new_passwd = passwordGenerate();

                //, "From: ".$dbowner_Email
                $dbowner_Email = user_getDbOwner($mysqli, 'ugr_eMail');

                $email_title = 'Password reset';
                $email_text = "Dear ".$user['ugr_FirstName'].",\n\n".
                "Your Heurist password has been reset.\n\n".
                "Your username is: ".$user['ugr_Name']."\n".
                "Your new password is: ".$new_passwd."\n\n".
                "To change your password go to Profile -> My User Info in the top right menu.\nYou will first be asked to log in with the new password above.\n\n"
                ."Database Owner: $dbowner_Email";


                $rv = sendEmail($user['ugr_eMail'], $email_title, $email_text);
                if($rv){

                    $res = userUpdatePassword($mysqli, $user['ugr_ID'], hash_it($new_passwd));
                    if(is_numeric($res)>0){
                            return true;
                    }else{
                        $system->addError(HEURIST_DB_ERROR, 'Cannot update record in database', $res);
                    }
                }else{
                    $msg = $system->getError();
                    $system->addError(HEURIST_SYSTEM_CONFIG, 'Error_Password_Reset', $msg?@$msg['message']:null);
                }

            }

        }else{
            $system->addError(HEURIST_INVALID_REQUEST, "Username / email not defined");//INVALID_REQUEST
        }
        return false;
    }

    /**
     * Peforms one of three actions:
     *  Validates the captcha and sends a reset pin to the user's email
     *  Sends a new reset pin, or
     *  Validates the provided pin, allowing a password reset
     *
     * @param object $system - initialised System class object
     * @param string $username User's username or email address.
     * @param string $pin (Optional) If provided and not empty, this function attempts to validate this PIN.
     *                    If 1, it forces a resend. If empty, it attempts to generate and send a new PIN.
     * @param string $captcha (Optional) The user's answer to a CAPTCHA challenge, required when generating a new PIN.
     * @return bool|string Returns `true` if a PIN is successfully validated or successfully sent.
     *                     Returns a specific string message if a PIN is re-sent or an old one expired and a new one sent.
     *                     Returns `false` on any error (e.g., user not found, CAPTCHA failed, email failed, too many attempts).
     *                     Errors are added to `$system`.
     */
    /**
     * Manages the PIN-based password reset process.
     *
     * This function has multiple modes based on the `$pin` parameter:
     * 1. Initial request (empty `$pin`, `$captcha` provided): Validates CAPTCHA, generates a new PIN,
     *    stores its hash and expiry in the PHP session, and emails the PIN to the user.
     * 2. PIN validation (`$pin` provided by user): Checks the provided PIN against the stored hash
     *    and its expiry. If valid, marks the PIN as "redeemed" in the session.
     * 3. PIN resend (`$pin` is 1): Generates and sends a new PIN, updating session details.
     *
     * It includes rate limiting for PIN resends and overall attempts to prevent abuse.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param string $username The username or email of the user requesting a password reset.
     * @param string $pin The PIN provided by the user for validation, or '1' to request a resend, or empty for initial request.
     * @param string $captcha The CAPTCHA answer, required for initial PIN request.
     * @return bool|string True if PIN is validated or a new PIN is successfully sent.
     *                     A string message can be returned for specific scenarios like PIN resend/expiry.
     *                     False on error.
     */
    function user_HandleResetPin($system, $username, $pin = '', $captcha = ''){

        $mysqli = $system->getMysqli();
        $now = strtotime('now');
        $an_hour = 60 * 60;

        if($pin == 1) {$pin = '';}// requesting new pin or a re-send

        if(session_status() == PHP_SESSION_ACTIVE){  // all information is stored within the current session

            $db = $system->dbnameFull();//dbname()

            if(!@$_SESSION[$db]){
                $_SESSION[$db] = array();
            }

            // Check for user
            $user = user_getByField($mysqli, 'ugr_Name', $username);
            if($user == null) {
                $user = user_getByField($mysqli, 'ugr_eMail', $username);
            }
            if($user == null) {
                $system->addError(HEURIST_NOT_FOUND, 'Unable to find provided username / email');
                return false;
            }

            $user_id = $user['ugr_ID'];

            if(!array_key_exists('reset_pins', $_SESSION[$db])){
                $_SESSION[$db]['reset_pins'] = array(
                    $user_id => array(
                        'pin' => '',
                        'resends' => 1,
                        'attempts' => 0,
                        'expire' => null,
                        'redeemed' => false
                    ),
                    'blocked' => 0,
                    'last_block' => null
                );
            }elseif(!array_key_exists($user_id, $_SESSION[$db]['reset_pins'])){
                $_SESSION[$db]['reset_pins'][$user_id] = array(
                    'pin' => '',
                    'resends' => 1,
                    'attempts' => 0,
                    'expire' => null,
                    'redeemed' => false
                );
            }

            // Check if password reset system has been blocked
            if($_SESSION[$db]['reset_pins']['last_block'] !== null && $_SESSION[$db]['reset_pins']['last_block'] + (60 * 60) < $now){
                $_SESSION[$db]['reset_pins']['blocked'] = 0;
                $_SESSION[$db]['reset_pins']['last_block'] = null;
            }elseif($_SESSION[$db]['reset_pins']['blocked'] == 3){

                $system->addError(HEURIST_ACTION_BLOCKED, 'We are unable to send a reset pin at this time.<br>Please try again later');
                return false;
            }

            $check_pin = !empty($pin) && !empty($_SESSION[$db]['reset_pins'][$user_id]['pin']);

            if($check_pin && $_SESSION[$db]['reset_pins'][$user_id]['expire'] > $now){ // pin check requested, and valid pin in session

                if(!passwordCheck($pin, $_SESSION[$db]['reset_pins'][$user_id]['pin'])){
                    $system->addError(HEURIST_ACTION_BLOCKED, 'Invalid pin provided');
                    return false;
                }

                $_SESSION[$db]['reset_pins'][$user_id]['redeemed'] = true; // set redeemed flag for pin

                return true;
            }

            // create/re-send pin, save in session
            $new_pin = passwordGenerate();// generate pin
            $has_pin = !empty(@$_SESSION[$db]['reset_pins'][$user_id]['pin']);
            $response = true;
            $test_captcha = true;
            $resends = $_SESSION[$db]['reset_pins'][$user_id]['resends'] < 1 ? 1 : $_SESSION[$db]['reset_pins'][$user_id]['resends'];

            if($has_pin){
                if($now > ($_SESSION[$db]['reset_pins'][$user_id]['expire'] + $an_hour)){ // very old pin, reset resends
                    $resends = 1;
                }else{ // requesting re-send

                    $test_captcha = false;
                    $resends ++;
                    $expired = $_SESSION[$db]['reset_pins'][$user_id]['expire'] < $now;

                    if($resends > 5){ // check re-send attempt count, likely emails aren't being sent

                        $_SESSION[$db]['reset_pins']['blocked'] ++;
                        $_SESSION[$db]['reset_pins']['last_blocked'] = strtotime('now');

                        $msg = ($expired ? 'Your pin has expired.<br> However, we' : 'We')
                            . ' are unable to send another reset pin at this moment, plase contact the Heurist team or try again at a later time';

                        $system->addError(HEURIST_ACTION_BLOCKED, $msg);
                        return false;
                    }elseif($expired && $check_pin){ // was checking pin, but existing one has expired
                        $response = 'Your current reset pin has expired.<br>A new one has been sent to your email';
                    }else{ // re-sending
                        $response = 'A new pin has been sent';
                    }
                }
            }

            // Test captcha
            if($test_captcha){
                if(!array_key_exists('captcha_code', $_SESSION)){
                    $system->addError(HEURIST_ERROR, 'An error has occurred with testing the captcha code');
                    return false;
                }
                if(empty($captcha) || $captcha != $_SESSION["captcha_code"]){
                    $system->addError(HEURIST_ACTION_BLOCKED, 'Are you a bot? Please enter the correct answer to the challenge question');
                    return false;
                }
            }

            // Send new pin to user's email
            $dbowner_Email = user_getDbOwner($mysqli, 'ugr_eMail');

            $email_title = "Forgot password";
            $email_body = "Dear ".$user['ugr_FirstName'].",\n\n".
            "A reset pin was requested for your account on database ". $db .".\n\n".
            "Your username is: ".$user['ugr_Name']."\n".
            "Your reset pin is: ".$new_pin."\n\n".
            "This pin will expire in 5 minutes. Please enter it in the popup to reset your password.\n\n"
                ."Database Owner: $dbowner_Email";

            $res = sendEmail($user['ugr_eMail'], $email_title, $email_body);
            if($res){

                // Store in session
                $_SESSION[$db]['reset_pins'][$user_id] = array(
                    'pin' => hash_it($new_pin),
                    'expire' => strtotime('+5 minutes'),
                    'resends' => $resends,
                    'user' => $user_id,
                    'redeemed' => false
                );

                return $response;
            }else{
                $msg = $system->getError();
                $system->addError(HEURIST_SYSTEM_CONFIG, 'We were unable to email you a reset pin', $msg?@$msg['message']:null);
                return false;
            }

        }else{

            $system->addError(HEURIST_ERROR, 'We were unable to send your reset pin, as an error occurred with retrieving your current session');
            return false;
        }
    }

    /**
     * Resets user's password to the provided value, via the use of a reset pin
     *
     * @param object $system - initialised System class object
     * @param string $username User's username or email address.
     * @param string $password The new password to set.
     * @param string $pin The reset PIN previously validated by `user_HandleResetPin`.
     * @return bool True if the password was successfully reset, false otherwise.
     *              Errors are added to `$system`.
     */
    /**
     * Resets a user's password after successful PIN validation.
     *
     * This function should be called after `user_HandleResetPin` has successfully validated a PIN
     * (by returning true and setting the 'redeemed' flag in the session).
     * It verifies the PIN again against the session data and, if valid and redeemed,
     * updates the user's password in the database with the new (hashed) password.
     * The PIN information is then cleared from the session.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param string $username The username or email of the user.
     * @param string $password The new plaintext password.
     * @param string $pin The reset PIN that was previously validated.
     * @return bool True on successful password update, false on any error or validation failure.
     */
    function user_ResetPassword($system, $username, $password, $pin){

        $mysqli = $system->getMysqli();

        if(empty($username) || empty($password) || empty($pin)){ // check required values
            $system->addError(HEURIST_ACTION_BLOCKED, 'A username, the new password, and the reset pin are required for this function');
            return false;
        }

        if(session_status() != PHP_SESSION_ACTIVE){ // all information is stored within the current session
            $system->addError(HEURIST_ERROR, 'We were unable to reset your password via the pin system, as an error occurred with retrieving your current session');
            return false;
        }

        $db = $system->dbnameFull();

        // Check for user
        $user = user_getByField($mysqli, 'ugr_Name', $username);
        if($user == null) {
            $user = user_getByField($mysqli, 'ugr_eMail', $username);
        }
        if($user == null) {
            $system->addError(HEURIST_NOT_FOUND,  'Cannot set new password. Unable to find specified username / email.');
            return false;
        }

        $user_id = $user['ugr_ID'];

        // Check reset pin
        if(!array_key_exists('reset_pins', $_SESSION[$db]) || !array_key_exists($user_id, $_SESSION[$db]['reset_pins'])){ // check that a pin has been requested for this user
            $system->addError(HEURIST_ERROR, 'An error has occurred with changing your password using a reset pin.<br>Please contact the Heurist team');
            return false;
        }
        if(!passwordCheck($pin, $_SESSION[$db]['reset_pins'][$user_id]['pin'])){ // check the pins match
            $system->addError(HEURIST_ACTION_BLOCKED, 'Invalid reset pin');
            return false;
        }
        if($_SESSION[$db]['reset_pins'][$user_id]['redeemed'] !== true){ // has been handled by user_HandleResetPin
            $system->addError(HEURIST_ERROR, 'We were unable to verify the reset pin');
            return false;
        }

        // Update password
        $res = userUpdatePassword($mysqli, $user['ugr_ID'], hash_it($password));

        if(is_numeric($res) > 0){

            unset($_SESSION[$db]['reset_pins'][$user_id]);// remove from session

            return true;
        }

        $system->addError(HEURIST_ERROR, 'We were unable to reset your password, an error occurred while updating your user account details');
        return false;
    }
    
    /**
     * Updates the user's password hash in the database.
     *
     * @param \mysqli $mysqli The mysqli database connection object.
     * @param int $ugr_ID The User ID.
     * @param string $ugr_Password The new hashed password.
     * @return int|string The result of `mysql__insertupdate` (typically number of affected rows or error string).
     */
    function userUpdatePassword($mysqli, $ugr_ID, $ugr_Password){
        $record = array("ugr_ID"=>intval($ugr_ID), "ugr_Password"=>$ugr_Password);// prepare record
        return mysql__insertupdate($mysqli, "sysUGrps", "ugr_", $record);
    }

    /**
     * Updates the user's last login time to NOW() and increments their login count.
     *
     * @param \mysqli $mysqli The mysqli database connection object.
     * @param int $ugr_ID The User ID.
     * @return int The number of affected rows (should be 1 on success).
     */
    function user_updateLoginTime($mysqli, $ugr_ID){
        $query = 'update sysUGrps set ugr_LastLoginTime=now(), ugr_LoginCount=ugr_LoginCount+1 where ugr_ID='.intval($ugr_ID);
        $mysqli->query($query);

        $cnt = $mysqli->affected_rows;
        return $cnt;
    }

    /**
     * Retrieves the list of workgroups a specific user belongs to, along with their role in each group.
     *
     * Can optionally fetch full group details (name, description) or just IDs and roles.
     * Can also query a specified external database.
     *
     * @param \mysqli $mysqli The mysqli database connection object.
     * @param int $ugr_ID The User ID for whom to fetch group memberships.
     * @param bool $isfull (Optional) If true, fetches `ugr_Name` and `ugr_Description` for each group.
     *                     If false (default), fetches only `ugl_GroupID` and `ugl_Role`.
     * @param string|null $database (Optional) The name of an alternative database to query.
     *                              If null, queries the current database.
     * @return array An associative array where keys are group IDs (`ugl_GroupID`).
     *               If `$isfull` is false, values are the user's role (`ugl_Role`) in that group.
     *               If `$isfull` is true, values are arrays `[ugl_Role, ugr_Name, ugr_Description]`.
     *               Returns an empty array if the user belongs to no groups or on error.
     */
    function user_getWorkgroups($mysqli, $ugr_ID, $isfull=false, $database=null){

        $result = array();

        if(!$mysqli || intval($ugr_ID)==0)
        {
            return $result;
        }

            $dbprefix = '';
            if($database!=null){
                $dbprefix = preg_replace(REGEX_ALPHANUM, "", $database);//for snyk
                $dbprefix = '`'.$dbprefix.'`.';
            }

            $query = 'select ugl_GroupID, ugl_Role '
            .($isfull?', ugr_Name, ugr_Description ':'')
            .' from '.$dbprefix.'sysUsrGrpLinks '
            .' left join '.$dbprefix.'sysUGrps grp '
            .' on grp.ugr_ID=ugl_GroupID where '
            .' ugl_UserID='.intval($ugr_ID)
            .' and grp.ugr_Type != "user" order by ugl_GroupID';

            $res = $mysqli->query($query);
            if(!$res){
                return $result;
            }
                while ($row = $res->fetch_row()) {
                    if($isfull){
                        $id = array_shift($row);
                        $result[$id] = $row;
                    }else{
                        $result[$row[0]] = $row[1];
                    }
                }
                $res->close();

            return $result;
    }

    //@todo verify why it returns db onwer - The original query did not explicitly exclude ugr_ID=2, so it might have been intended.
    /**
     * Retrieves a list of all workgroups in the system.
     *
     * Excludes entities of type 'user'.
     *
     * @param \mysqli $mysqli The mysqli database connection object.
     * @return array An associative array `[ugr_ID => ugr_Name]` for all workgroups, ordered by name.
     *               Returns an empty array if no workgroups exist.
     */
    function user_getAllWorkgroups($mysqli){
//OR (ugr_ID=2) // Original comment, implies ugr_ID=2 (DB Owner) might have been considered for exclusion or special handling.
        $query = 'SELECT ugr_ID, ugr_Name FROM sysUGrps WHERE (ugr_Type != "user") ORDER BY ugr_Name';
        $result = mysql__select_assoc2($mysqli, $query);

        if($result==null) {$result = array();}

        return $result;
    }

    /**
     * Retrieves a list of members for a specific workgroup.
     *
     * Fetches users who are enabled and are of type 'user'.
     * Provides role, first name, last name, and organisation for each member.
     * This is described as "non admin short info".
     *
     * @param \mysqli $mysqli The mysqli database connection object.
     * @param int $ugr_ID The Group ID for which to fetch members.
     * @return array An associative array where keys are user IDs (`ugl_UserID`) and values are arrays
     *               `[ugl_Role, ugr_FirstName, ugr_LastName, ugr_Organisation]`.
     *               Returns an empty array if the group has no members or on error.
     */
    function user_getWorkgroupMembers($mysqli, $ugr_ID){

        $result = array();

        if($mysqli && intval($ugr_ID)){

            $query = 'select ugl_UserID, ugl_Role, ugr_FirstName, ugr_LastName, ugr_Organisation '
            .' from sysUsrGrpLinks left join sysUGrps usr on usr.ugr_ID=ugl_UserID where '
            .' ugl_GroupID='.intval($ugr_ID)
            .' and usr.ugr_Type = "user" and usr.ugr_Enabled!="n" order by ugl_UserID';

            $res = $mysqli->query($query);
            if($res){
                while ($row = $res->fetch_row()) {
                    $id = array_shift($row);
                    $result[$id] = $row;
                }
                $res->close();
            }
        }
        return $result;
    }

    //==========================================================================

    /**
     * Returns a default set of user preferences.
     *
     * These preferences are used for new users or when a user's preferences
     * cannot be loaded from the database or session.
     *
     * @return array An associative array of default preference key-value pairs.
     */
    function user_getDefaultPreferences(){
        return array(
        "layout_language" => "en",
        'search_result_pagesize' => 100,
        'search_detail_limit' => 2000,
        'userCompetencyLevel' => 2, //'beginner'
        'userFontSize' => 12, //px

        'deriveMapLocation ' => true,

        "edit_open_in_new_window" => false,
        "edit_force_tags" => true,
        "edit_pointer_recentsearch" => true,

        'help_on' => true,
        'optfields' => true,
        'mapcluster_on' => true,
        'searchQueryInBrowser' => true,
        'defaultSearch' => 'sortby:-m'
        );
    }

    //@$_SESSION[$system->dbnameFull()]['ugr_Groups'] = user_getWorkgroups( $this->mysqli, $userID );

    /**
     * Saves a user's preferences to both the PHP session and the database.
     *
     * Iterates through the provided `$params`, saving each key-value pair into the
     * current user's session preferences (`$_SESSION[$dbname]["ugr_Preferences"]`).
     * Excludes certain parameters like 'a', 'db', 'DBGSESSID'.
     * Then, it persists the entire preference set (including any existing ones not
     * being modified in this call, and potentially external repository credentials)
     * as a JSON string into the `ugr_Preferences` field of the `sysUGrps` table
     * for the current logged-in user.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param array $params An associative array of preference key-value pairs to save.
     */
    function user_setPreferences($system, $params){

        $mysqli = $system->getMysqli();
        $ugrID = $system->getUserId();
        $dbname = $system->dbnameFull();

        $exclude = array('a','db','DBGSESSID');//do not save these params

        //save into SESSION
        foreach ($params as $property => $value) {
            if(!in_array($property, $exclude)){
                @$_SESSION[$dbname]["ugr_Preferences"][$property] = $value;
            }
        }

        //save into Database
        if($ugrID>0){

            $prefs = $_SESSION[$dbname]["ugr_Preferences"];

            if(@$prefs['externalRepositories']==null){
                //get current from database
                $repositories = user_getRepositoryCredentials($system, false, $ugr_ID);
                if($repositories!=null && !empty($repositories)){
                    $prefs['externalRepositories'] = $repositories;
                }
            }

            $res = mysql__insertupdate( $mysqli, 'sysUGrps', 'ugr', array(
                        'ugr_ID'=>$ugrID,
                        'ugr_Preferences'=>json_encode($prefs) ));
        }
    }

    /**
    * Restores user preferences, loading from the database for logged-in users,
    * or falling back to session or default preferences.
    *
    * This function is typically called during user login (see `login_verify`) to populate
    * the session with the user's stored preferences. To subsequently get an individual
    * preference value, `$system->userGetPreference()` should be used.
    *
    * @param \hserv\System $system The Heurist system object.
    * @return array An associative array of user preferences.
    */
    function user_getPreferences( $system ){

        $mysqli = $system->getMysqli();
        $ugrID = $system->getUserId();

        //1. from database
        if($ugrID>0){ //logged in
            $res = mysql__select_value( $mysqli, 'select ugr_Preferences from sysUGrps where ugr_ID='.$ugrID);
            if($res!=null && $res!=''){
                $res = json_decode($res, true);
                if($res && !empty($res)){
                    return $res;
                }
            }
        }

        //2. from session or default
        $dbname = $system->dbnameFull();
        return(@$_SESSION[$dbname]['ugr_Preferences'])
                    ?$_SESSION[$dbname]['ugr_Preferences']
                    :user_getDefaultPreferences();
    }


    /**
     * Checks if a user account is pending administrative approval.
     *
     * A user is considered pending approval if their account type is 'user',
     * they are not enabled (`ugr_Enabled` = 'n'), and their login count (`ugr_LoginCount`) is 0.
     * This check is typically performed by an administrator.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param int $recID The User ID to check.
     * @return bool True if the user is pending approval, false otherwise or if current user is not admin.
     */
    function user_isApprovement( $system, $recID ) {

        $ret = false;

        if($system->isAdmin() && $recID>0){
            $row = mysql__select_row($system->getMysqli(),
                "select ugr_Type, ugr_Enabled, ugr_LoginCount from sysUGrps  where ugr_ID=".$recID);
            $ret = ($row[0]=="user" && $row[1]=="n" && $row[2]==0);
        }

        return $ret;
    }

    //
    //
    //
    /**
     * Manages a user's "working set" of records.
     *
     * A working set is a temporary collection of record IDs associated with a user.
     * This function can:
     * - Clear the current user's existing working set.
     * - If `$params['clear']` is not 1 and `$params['ids']` is provided, it populates
     *   the `usrWorkingSubsets` table with the given record IDs for the current user.
     *   This involves writing IDs to a temporary file and then using `LOAD DATA LOCAL INFILE`.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param array $params Parameters for the operation:
     *                      - 'ids': (Optional) An array or comma-separated string of record IDs to add to the set.
     *                      - 'clear': (Optional) If 1, only clears the set. Otherwise, populates with 'ids'.
     * @return bool|int Returns `true` if the set was only cleared successfully.
     *                  Returns the count of added records if records were added successfully.
     *                  Returns `false` on error (e.g., user not logged in, DB error, file error).
     */
    function user_WorkSet( $system, $params ){

        $res = false;
        $curr_user_id = $system->getUserId();
        if($curr_user_id>0){

            $mysqli = $system->getMysqli();

            $mysqli->query('DELETE FROM usrWorkingSubsets where wss_OwnerUGrpID='.$curr_user_id);
            if ($mysqli->error) {
                    $system->addError(HEURIST_DB_ERROR, 'Cannot reset user workset. SQL error: '.$mysqli->error);
            }else{
                    $res = true;
            }

            if(@$params['clear']!=1){

                $recids = @$params['ids'];
                $recids = prepareIds($recids);
                if(!isEmptyArray($recids)){

                    $filename = tempnam(HEURIST_SCRATCHSPACE_DIR, "data");

                    if (!$handle_wr = fopen($filename, 'w')) {
                        $system->addError(HEURIST_ERROR, 'Cannot open file to save workset data: '.$filename);
                        return false;
                    }

                    foreach($recids as $recid){
                        if (fwrite($handle_wr, $recid.','.$curr_user_id."\n") === false) {
                            $system->addError(HEURIST_ERROR, 'Cannot write workset data to file '.$filename);
                            fclose($handle_wr);
                            if(file_exists($filename)) {unlink($filename);}
                            return false;
                        }
                    }
                    fclose($handle_wr);

                    if(strpos($filename,"\\")>0){
                        $filename = str_replace("\\","\\\\",$filename);
                    }

                    $mysqli->query('SET GLOBAL local_infile = true');
                    //load file into table  LOCAL
                    $query = "LOAD DATA LOCAL INFILE '".$filename."' INTO TABLE usrWorkingSubsets "
                    //." CHARACTER SET utf8mb4"
                    ." FIELDS TERMINATED BY ',' "
                    ." OPTIONALLY ENCLOSED BY  '\"' "
                    ." LINES TERMINATED BY '\n'"
                    //." IGNORE 1 LINES
                    ." (wss_RecID ,wss_OwnerUGrpID)";

                    if (!$mysqli->query($query)) {
                        $system->addError(HEURIST_DB_ERROR, 'Unable to import workset data. SQL error: '.$mysqli->error);
                    }else{
                        $res = count($recids);
                    }

                    if(file_exists($filename)) {unlink($filename);}

                }else{
                    $system->addError(HEURIST_INVALID_REQUEST, 'Set of records to be added to user workset is not defined');
                }
            }

        }else{
            $system->addError(HEURIST_REQUEST_DENIED);
        }

        return $res;
    }

    /**
     * Handles user/group creation (registration) or profile updates.
     *
     * This function performs several checks and operations:
     * - Validates the input record data using `user_Validate()`.
     * - For new user registrations (`$recID` < 1, `ugr_Type` = 'user'):
     *   - Checks guest registration daily limits.
     *   - Checks if registration is allowed by system settings (`sys_AllowRegistration`), unless `$allow_registration` is true.
     *   - Validates CAPTCHA if provided.
     *   - Ensures username and email are unique.
     *   - Hashes the password.
     *   - Sets the `ugr_Enabled` status (e.g., 'n' for new non-guest users, 'y' if `$allow_registration` forces it).
     *   - Sends notification emails about the new user or approval using `user_EmailAboutNewUser()` or `user_EmailApproval()`.
     *   - Synchronizes credentials to linked databases if applicable (`user_SyncCommonCredentials`).
     * - For updates or group creation:
     *   - Checks if the current user has permission to modify the target user/group.
     * - Saves the data to `sysUGrps` table using `mysql__insertupdate`.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param array $record An associative array of user/group data (ugr_ prefixed fields).
     *                      Must include 'ugr_Type'. For new users, password and required profile fields are necessary.
     * @param bool $allow_registration (Optional) If true, allows user registration even if the global
     *                                 `sys_AllowRegistration` setting is off. Also, makes the new user enabled ('y') directly.
     * @return int|string|false The ID of the created/updated user/group on success.
     *                          Returns `false` or an error string from `mysql__insertupdate` on failure.
     *                          Errors are added to `$system`.
     */
    function user_Update($system, $record, $allow_registration=false){

        if (user_Validate($system, $record))
        {
            $recID = intval(@$record['ugr_ID']);
            $rectype = $record['ugr_Type'];
            $is_registration = ($rectype=='user' && $recID<1);
            $is_guest_registration = ($is_registration && @$record['is_guest']==1);

            $mysqli = $system->getMysqli();

            if($is_guest_registration && $recID<1 && $rectype=='user'){
                //verify max allowed count of guest registrations per day
                $res = mysql__select_value($mysqli,
                    "select count(ugr_ID) from sysUGrps where ugr_Enabled='n' AND DATE(ugr_Modified)=CURDATE()");
                if($res>19){
                    $system->addError(HEURIST_ACTION_BLOCKED, 'Sorry, registration of guest users for the current database exceeds allowed daily limit');
                    return false;
                }
            }


            if($is_registration && !$allow_registration && $system->settings->get('sys_AllowRegistration')==0){

                $system->addError(HEURIST_REQUEST_DENIED, 'Registration is not allowed for current database');

            }elseif($is_registration || $system->hasAccess($recID)) {

                //do not allow registration if approvement mail cannot be sent
                if($is_registration){
                    if(false && !checkSmtp()){
                        $system->addError(HEURIST_SYSTEM_CONFIG, 'Error_Mail_Registration');
                        return false;
                    }
                    //check capture
                    if (@$_SESSION["captcha_code"] && $_SESSION["captcha_code"] != @$record['ugr_Captcha']) {
                        $system->addError(HEURIST_UNKNOWN_ERROR, 'Are you a bot? Please enter the correct answer to the challenge question');
                        return false;
                    }
                    if (@$_SESSION["captcha_code"]){
                        unset($_SESSION["captcha_code"]);
                    }
                }
                if(@$record['ugr_Captcha']){
                    unset($record['ugr_Captcha']);
                }

                $res = mysql__select_value($mysqli,
                    "select ugr_ID from sysUGrps  where ugr_Name='"
                    .$mysqli->real_escape_string( $record['ugr_Name'])."' or ugr_eMail='"
                    .$mysqli->real_escape_string($record['ugr_eMail'])."'");
                if($res>0 && $res!=$recID){
                    $system->addError(HEURIST_ACTION_BLOCKED, 'The provided name or email already exists');
                    return false;
                }

                $is_approvement = false;
                //encrypt password
                $tmp_password = null;
                if($rectype=='user'){

                    $allowed_status = array('n', 'y', 'y_no_add', 'y_no_delete', 'y_no_add_delete');
                    $record['ugr_Enabled'] = (in_array($record['ugr_Enabled'], $allowed_status) ? $record['ugr_Enabled'] : 'n');// y_no_add_delete

                    if(@$record['ugr_Password'] && $record['ugr_Password']!=''){
                        $tmp_password = $record['ugr_Password'];
                        $record['ugr_Password'] = hash_it($tmp_password);
                    }else{
                        unset($record['ugr_Password']);
                    }

                    if($is_guest_registration){
                        $record['ugr_Enabled'] = "n";
                    }elseif($allow_registration){
                        $record['ugr_Enabled'] = "y";
                    }else{

                        if($system->getUserId()<1){ //not logged in - always disabled
                            $record['ugr_Enabled'] = "n";
                        }
                        if("n"!=@$record['ugr_Enabled']){
                            $is_approvement = user_isApprovement($system, $recID) ? $record['ugr_Enabled'] : false;
                        }
                    }

                }

                $res = mysql__insertupdate($mysqli, "sysUGrps", "ugr", $record);
                if(is_numeric($res)>0){

                    $new_recID = $res;

                    //actions on complete
                    if($rectype=='user'){
                        $rv = true;
                        if($recID<1 && $system->getUserId()<1){
                            $rv = user_EmailAboutNewUser($system, $new_recID, false, $is_guest_registration);
                        }elseif($recID<1 || $is_approvement){
                            $rv = user_EmailApproval($system, $new_recID, $tmp_password, $is_approvement);

                            user_SyncCommonCredentials($system,  $new_recID, $is_approvement);
                        }
                        if(!$rv){
                            return false;
                        }

                    }elseif($recID<1){
                        //this is addition of new group
                        //add current user as admin for new group
                        //changeRole($recID, get_user_id(), "admin", null, false, true);
                    }

                    return $res; //returns affected record id
                }else{
                    $system->addError(HEURIST_DB_ERROR, 'Cannot update record in database', $res);
                }
            }else{
                $system->addError(HEURIST_REQUEST_DENIED, 'Operation denied. Not enough rights (logout/in to refresh)');
            }

        }  else {
            //$system->addError(HEURIST_INVALID_REQUEST, "All required fields are not defined");
        }

        return false;
    }

    /**
     * Validates user or workgroup data before saving.
     *
     * Checks for the presence of required fields based on whether it's a 'user' or 'workgroup'.
     * For users: 'ugr_Name', 'ugr_eMail', 'ugr_FirstName', 'ugr_LastName', 'ugr_Organisation', 'ugr_Interests'.
     *            Password ('ugr_Password') is also required for new users.
     * For workgroups: 'ugr_Name', 'ugr_eMail'.
     *
     * @param \hserv\System $system The Heurist system object (used for adding errors).
     * @param array $record An associative array of user/group data. Must contain 'ugr_Type'.
     * @return bool True if validation passes, false otherwise (errors are added to `$system`).
     */
    function user_Validate($system, $record){
        $res = false;

        if(@$record['ugr_Type']=='user'){
            //required fields for user
            $reqs = array('ugr_Name','ugr_eMail','ugr_FirstName','ugr_LastName','ugr_Organisation','ugr_Interests');
            if(intval(@$record['ugr_ID'])<1){
                array_push($reqs, 'ugr_Password');
            }

        }elseif (@$record['ugr_Type']=='workgroup'){
            $reqs = array('ugr_Name','ugr_eMail');

        }else{
            $system->addError(HEURIST_INVALID_REQUEST, "Wrong type for usergroup: ".@$record['ugr_Type']);
            return false;
        }

        $missed = array();
        foreach ($reqs as $fld){
            if(!@$record[$fld]){
                array_push($missed, $fld);
            }
        }

        if(!empty($missed)){
            $system->addError(HEURIST_INVALID_REQUEST, "Some required fields are not defined: ".implode(",",$missed));
        }else{
            $res = true;
        }


        return $res;
    }


    /**
     * Synchronizes user credentials (adds or enables user) to other Heurist databases
     * that are configured for mutual credential sharing.
     *
     * This function is typically called after a new user is created or an existing user is approved.
     * It checks the `sys_UGrpsDatabase` setting in the current database to find a list of
     * linked databases. For each linked database, it verifies that the link is mutual
     * (i.e., the target database also lists the current database for credential sharing).
     * If mutual linking is confirmed:
     * - It checks if the user (by email) already exists in the linked database.
     * - If not, it attempts to insert a copy of the user's record (excluding some fields like login count)
     *   into the linked database's `sysUGrps` table. The username in the linked DB becomes the user's email.
     * - If the user exists and `$is_approvement` is true (indicating an approval action),
     *   it attempts to update the user's `ugr_Enabled` status in the linked database.
     *
     * @param \hserv\System $system The Heurist system object for the current database.
     * @param int $userID The ID of the user whose credentials are to be synchronized.
     * @param bool|string $is_approvement If true or a 'y' status string, indicates an approval action,
     *                                    which may trigger enabling the user in linked databases.
     */
    function user_SyncCommonCredentials($system, $userID, $is_approvement){

        $dbname_full = $system->dbnameFull();
        $mysqli = $system->getMysqli();
        //1. find sys_UGrpsDatabase in this database
        $linked_dbs = mysql__select_value($mysqli, 'select sys_UGrpsDatabase from sysIdentification');
        if($linked_dbs)
        {

            $userEmail = mysql__select_value($mysqli, 'select ugr_eMail from sysUGrps where ugr_ID='.$userID);

            $linked_dbs = explode(',', $linked_dbs);
            foreach ($linked_dbs as $ldb){
                if(strpos($ldb, HEURIST_DB_PREFIX)!==0){
                    $ldb = HEURIST_DB_PREFIX.$ldb;
                }
                //database exists
                $dbname = mysql__select_value($mysqli,
                    'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \''
                        .$mysqli->real_escape_string($ldb).'\'');
                if(!$dbname) {continue;}

                $ldb = preg_replace(REGEX_ALPHANUM, '', $ldb);//for snyk

                //2. find sys_UGrpsDatabase in linked database - this database must be in list
                $linked_dbs2 = mysql__select_value($mysqli, 'select sys_UGrpsDatabase from '.$ldb.'.sysIdentification');
                if(!$linked_dbs2) {continue;} //this database is not mutually linked
                $linked_dbs2 = explode(',', $linked_dbs2);
                foreach ($linked_dbs2 as $ldb2){
                    if(strpos($ldb2, HEURIST_DB_PREFIX)!==0){
                        $ldb2 = HEURIST_DB_PREFIX.$ldb2;
                    }
                    if( strcasecmp($dbname_full, $ldb2)==0 ){
                        //yes database is mutually linked
                        //3. find user email in linked database
                        $userEmail_in_linkedDB = mysql__select_value($mysqli, 'select ugr_eMail from `'
                                .$ldb.'`.sysUGrps where ugr_eMail="'.$userEmail.'"');
                        if(!$userEmail_in_linkedDB){
                            //add new user to linked database

                            $fields = 'ugr_LongName,ugr_Description,ugr_Password,ugr_eMail,'.
                            'ugr_FirstName,ugr_LastName,ugr_Department,ugr_Organisation,ugr_City,'.
                            'ugr_State,ugr_Postcode,ugr_Interests,ugr_Enabled,ugr_LastLoginTime,'.
                            'ugr_MinHyperlinkWords,ugr_IsModelUser,'.  //ugr_LoginCount,
                            'ugr_IncomingEmailAddresses,ugr_TargetEmailAddresses,ugr_URLs,ugr_FlagJT';

                            $query1 = "insert into `$ldb`.sysUGrps (ugr_Type,ugr_Name,$fields) ".
                            "SELECT ugr_Type,ugr_eMail,$fields ".
                            "FROM sysUGrps where ugr_ID=".intval($userID);


                        }elseif($is_approvement){
                            //enable user
                            $query1 = "update `$ldb`.sysUGrps set ugr_Enabled='". $is_approvement ."' where ugr_ID=".intval($userID);
                        }

                        $res = $mysqli->query($query1);
                        break;
                    }
                }
            }
        }



    }

    /**
     * Sends an email notification to the database owner (and potentially system admin)
     * about a new user registration or import.
     *
     * The email content varies slightly based on whether the user was imported or registered directly,
     * and if it's a guest registration. It includes user details and a link to the database.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param int $recID The ID of the newly registered/imported user.
     * @param bool $fromImport (Optional) True if the user was imported from another database. Default false.
     * @param bool $is_guest_registration (Optional) True if it's a guest registration. Default false.
     * @return bool True if the email was sent successfully, false otherwise.
     *              Errors are added to `$system`.
     */
    function user_EmailAboutNewUser($system, $recID, $fromImport = false, $is_guest_registration=false){

        $mysqli = $system->getMysqli();

        $dbowner_Email = user_getDbOwner($mysqli, 'ugr_eMail');
        //$systemAdmin_Email = HEURIST_MAIL_TO_ADMIN;

        $user = user_getById($mysqli, $recID);//find user
        if($user)
        {
            $ugr_Name = $user['ugr_Name'];
            $ugr_FullName = $user['ugr_FirstName'].' '.$user['ugr_LastName'];
            $ugr_Organisation = $user['ugr_Organisation'];
            $ugr_eMail = $user['ugr_eMail'];

            //create email text for admin
            $email_text =
            ($fromImport ? "A new Heurist user has been imported from another local database.\nPlease note that this new user's account will be enabled by default but they can only create new records.\n" :
                "There is a Heurist user registration awaiting approval.\n") .
            "The user details ". ($fromImport ? "imported" : "submitted") ." are:\n".
            "Database name: ".HEURIST_DBNAME."\n".
            "Full name:    ".$ugr_FullName."\n".
            "Email address: ".$ugr_eMail."\n".
            "Organisation:  ".$ugr_Organisation."\n".
            "Go to the address below and navigate in menu Admin > Manage Users to review further details".
            ($fromImport ? "" : " and approve the registration") .":\n".
            HEURIST_BASE_URL."?db=".HEURIST_DBNAME; //."&recID=$recID&mode=users";

            if($is_guest_registration){
                $email_text .= ("\n\n".'WARNING: Guest users can add up to 200 records per day');
            }

            $email_title = 'User Registration: '.$ugr_FullName.' ['.$ugr_eMail.']';

            $rv = sendEmail($dbowner_Email, $email_title, $email_text);
            if(!$rv){
                $msg = $system->getError();
                $system->addError(HEURIST_SYSTEM_CONFIG, 'Error_Mail_Registration', $msg?@$msg['message']:null);
                return false;
            }
        }else{
                $system->addError(HEURIST_NOT_FOUND, 'User not found');
                return false;
        }
        return true;
    }

    /**
     * Sends an email to a user upon account creation or approval.
     *
     * The email includes login details (username and temporary password if provided)
     * and a link to the Heurist database.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param int $recID The ID of the user.
     * @param string|null $tmp_password (Optional) The temporary password to include in the email.
     * @param bool|string $is_approvement If true or a 'y' status string, the email text indicates account approval.
     *                                    Otherwise, it indicates account creation.
     * @return bool True if the email was sent successfully, false otherwise.
     *              Errors are added to `$system`.
     */
    function user_EmailApproval($system, $recID, $tmp_password, $is_approvement){

        $mysqli = $system->getMysqli();

        $dbowner_Email = user_getDbOwner($mysqli, 'ugr_eMail');
        $user = user_getById($mysqli, $recID);//find user
        if($user)
        {

            $ugr_Name = $user['ugr_Name'];
            $ugr_FullName = $user['ugr_FirstName'].' '.$user['ugr_LastName'];
            $ugr_Organisation = $user['ugr_Organisation'];
            $ugr_eMail = $user['ugr_eMail'];

            if($is_approvement){
                $email_text = "Your Heurist account registration has been approved.";
            }else{
                $email_text = "A new Heurist account has been created for you.";
            }

            //give them a pointer to the search page for the database
            $email_text .= "\n\nLogin to the database: ".HEURIST_DBNAME." at ".
            HEURIST_BASE_URL."?db=".HEURIST_DBNAME. "\n"."\n\nwith the username: " . $ugr_Name;


            if($tmp_password!=null){
                $email_text = $email_text." and password: ".$tmp_password.
                "\n\nTo change your password go to Profile -> My User Info in the top right menu";
            }

            $email_text = $email_text."\n\nWe recommend visiting https://HeuristNetwork.org and the online documentation ".
            "pages, which provide comprehensive overviews and step-by-step instructions for using Heurist.\n\n".
                            "Database Owner: $dbowner_Email";


            $email_title = 'User Registration: '.$ugr_FullName.' ['.$ugr_eMail.']';

            $rv = sendEmail($ugr_eMail, $email_title, $email_text);

            if(!$rv){
                $msg = $system->getError();
                $system->addError(HEURIST_SYSTEM_CONFIG, 'Error_Mail_Approvement', $msg?@$msg['message']:null);
                return false;
            }

        }else{
                $system->addError(HEURIST_NOT_FOUND, 'User not found');
                return false;
        }

        return true;
    }  // sendApprovalEmail

    /**
     * Get notifications to display to the user, currently handled:
     *  Monthly Bug / Suggestion report
     *
     * Monthly bug / suggestion report is handled separatly
     *
     * @param \hserv\System $system The Heurist system object.
     * @return array An array of notification messages to display to the user. Each message
     *               can be a string or an array with 'message' and 'links' components.
     *               Currently, only a 'bug_report' notification type is implemented.
     */
    /**
     * Retrieves system notifications to be displayed to the current user.
     *
     * This function checks for conditions under which specific notifications should be shown,
     * such as periodic reminders (e.g., for bug reports) or one-time informational messages.
     * It uses user preferences stored in the database (`sys_dbsettings.Notifications`) to
     * track when notifications were last shown or if they've been blocked by the user.
     *
     * Currently implemented notifications:
     * - 'bug_report': A periodic reminder to report bugs/suggestions.
     *
     * It avoids showing notifications if the user is seeing a "new version" popup or if they've
     * logged in very recently (within the last 3 days, to avoid immediate repeat notifications).
     *
     * @param \hserv\System $system The Heurist system object.
     * @return array An array of notification messages. Each key is a notification type (e.g., 'bug_report'),
     *               and the value is an array containing 'message' (the message string, possibly with placeholders
     *               like '#bug-reporter') and 'links' (an array defining interactive elements for the notification).
     */
    function user_getNotifications($system){

        $mysqli = $system->getMysqli();
        $today = strtotime('now');
        $usr_id = intval($system->getUserId());

        // LOAD USER SETTINGS
        $user_settings = $system->settings->getDatabaseSetting('Notifications');
        $notes_user_settings = HEURIST_FILESTORE_DIR . 'userNotifications.json'; // original user settings file

        if(empty($user_settings) && file_exists($notes_user_settings)){

            $user_settings = file_get_contents($notes_user_settings);
            $user_settings = json_decode($user_settings, true);

            if(!empty($user_settings)){
                $system->settings->setDatabaseSetting('Notifications', $user_settings, 0);
            }
        }

        fileDelete($notes_user_settings);

        // Skip notifications if the user is now getting the 'new version' popup
        $userPreferences = user_getPreferences($system);
        if($userPreferences && array_key_exists('version_in_cache', $userPreferences)){

            $curVersion = explode('.', HEURIST_VERSION);
            $lastVersion = explode('.', $userPreferences['version_in_cache']);

            $mismatch = false;

            foreach($curVersion as $idx => $curNum){

                if(count($lastVersion) < $idx + 1 || $curNum != $lastVersion[$idx]){
                    $mismatch = true;
                    break;
                }
            }

            if($mismatch){
                return [];
            }
        }

        // Handled system notifications
        $notifications = [
            'bug_report' => [
                'message' => '#bug-reporter',
                'links' => [
                    'span#open-bug-reporter' => [
                        'widget' => 'actionHandler',
                        'id' => 'menu-help-bugreport'
                    ]
                ]
            ]
        ];
        $conditions = [
            'bug_report' => [
                'period' => '+1 month'
            ],
            'cms_websites' => [
                'query' => [
                    'query' => <<<QUERY
                    SELECT IF(COUNT(rec_ID) = 0, true, false)
                    FROM Records
                    WHERE rec_RecTypeID IN (
                        SELECT rty_ID
                        FROM defRecTypes
                        WHERE rty_OriginatingDBID = 99 AND rty_IDInOriginatingDB IN (51,52)
                    )
                    QUERY
                ]
            ],
            'db_description' => [
                'query' => [
                    'query' => <<<QUERY
                    SELECT IF(LENGTH(sys_dbDescription) = 0, true, false)
                    FROM sysIdentification
                    QUERY
                ]
            ]
        ];

        if(empty($user_settings)){
            $user_settings = [
                $usr_id => []
            ];
        }elseif(!array_key_exists($usr_id, $user_settings)){
            $user_settings[$usr_id] = [];
        }

        $messages = [];

        if(empty($user_settings[$usr_id])){

            $user_settings[$usr_id] = array_fill_keys(array_keys($notifications), $today);
            $user_settings[$usr_id]['last_login'] = $today;

            $system->settings->setDatabaseSetting('Notifications', $user_settings, 0);

            return $messages;
        }

        // check user has logged in within the last three days
        if(!array_key_exists('last_login', $user_settings[$usr_id])
        || strtotime('+3 days', $user_settings[$usr_id]['last_login']) <= $today){

            $user_settings[$usr_id]['last_login'] = $today;
            $system->settings->setDatabaseSetting('Notifications', $user_settings, 0);

            return $messages;
        }

        $blocked = array_key_exists('block', $user_settings[$usr_id]) ? $user_settings[$usr_id]['block'] : '';
        $blocked = explode(',', $blocked);

        $checkLastNotify = function(&$usrSettings, $type) use ($today, $usr_id, $blocked){

            if(!array_key_exists($type, $usrSettings[$usr_id])){
                $usrSettings[$usr_id][$type] = $today;
                return true;
            }elseif(in_array($type, $blocked)){
                return true;
            }

            return false;
        };

        $checkConditions = function($conditions, $usrLastCheck) use ($mysqli, $today){

            $notify = false;

            foreach($conditions as $conditional_type => $condition){

                switch($conditional_type){

                    case 'period':

                        $notify = strtotime($condition, intval($usrLastCheck)) <= $today;

                        break;

                    case 'query':

                        $value = mysql__select_value($mysqli, $condition['query']);

                        $notify = $value === 1;

                        break;

                    default:
                        break;
                }
            }

            return $notify;
        };

        foreach($notifications as $type => $details){

            if($checkLastNotify($user_settings, $type)){
                continue;
            }

            $notify_conds = $conditions[$type];

            if($checkConditions($notify_conds, $user_settings[$usr_id][$type])){

                $user_settings[$usr_id][$type] = $today;

                $messages[$type] = [
                    'message' => $details['message'],
                    'links' => $details['links']
                ];
            }
        }

        $user_settings[$usr_id]['last_login'] = $today;

        $system->settings->setDatabaseSetting('Notifications', $user_settings, 0);

        return $messages;
    }

    /**
     * Set blocked notifications to avoid displaying to the current user
     *
     * @param \hserv\System $system The Heurist system object.
     * @param string|array $blocking A comma-separated string or an array of notification type keys
     *                               (e.g., 'bug_report') to be blocked for the current user.
     * @return bool True on success, false on failure (errors are added to `$system`).
     */
    /**
     * Allows the current user to block (snooze/dismiss) specific types of system notifications.
     *
     * Updates the 'Notifications' setting in `sys_dbsettings` for the current user,
     * adding the specified notification types to a 'block' list.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param string|array $blocking Notification type(s) to block.
     * @return bool True if preferences were successfully updated, false otherwise.
     */
    function user_blockNotifications($system, $blocking){

        $usr_id = intval($system->getUserId());

        if(empty($blocking)){
            $system->addError(HEURIST_INVALID_REQUEST, 'Missing required parameters');
            return false;
        }

        $user_settings = $system->settings->getDatabaseSetting('Notifications');

        if(empty($user_settings) || !array_key_exists($usr_id, $user_settings) || empty($user_settings[$usr_id])){
            $system->addError(HEURIST_ACTION_BLOCKED, 'No notifications found for this user');
            return false;
        }

        $notifications = ['bug_report'];

        $blocking = !is_array($blocking) ? explode(',', $blocking) : $blocking;
        $blocking = array_filter($blocking, function($type) use ($notifications){ return in_array($type, $notifications); });

        $blocking = array_merge($blocking, $user_settings[$usr_id]['block']);

        $user_settings[$usr_id]['block'] = implode(',', $blocking);

        return $system->settings->setDatabaseSetting('Notifications', $user_settings);
    }

    function passwordGenerate ($length = 8) { //private
        $passwd = '';
        $possible = '023456789bcdfghjkmnpqrstvwxyz';
        while (strlen($passwd) < $length) {
            $char = substr($possible, random_int(0, strlen($possible)-1), 1);
            if (!strstr($passwd, $char)) {$passwd .= $char;}
        }
        return $passwd;
    }

    function hash_it ($passwd) {
        //$pwd_peppered = hash_hmac("sha256", $passwd, $pepper);
        $options = ['cost' => 12];
        //new way return password_hash($passwd, PASSWORD_BCRYPT, $options); //PASSWORD_DEFAULT
        
        /* old way */
        $s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
        $salt = $s[random_int(0, strlen($s)-1)] . $s[random_int(0, strlen($s)-1)];
        return crypt($passwd, $salt);
        
    }
    
    function passwordCheck ($passwd, $passwd_hashed, $mysqli=null, $ugr_ID=0) {
        //$passwd = hash_hmac("sha256", $passwd, $pepper);
        $res = password_verify($passwd, $passwd_hashed);
        
        if(false && $res && $mysqli!=null && $ugr_ID>0){ //DISABLED TILL production version will be upgraded
            $algorithm = PASSWORD_BCRYPT;
            // bcrypt's cost parameter can change over time as hardware improves
            $options = ['cost' => 12];
            if (password_needs_rehash($passwd_hashed, $algorithm, $options)) {
                // If so, create a new hash, and replace the old one
                $passwd_hashed_new = password_hash($passwd, $algorithm, $options);        
                
                userUpdatePassword($mysqli, $ugr_ID, $passwd_hashed_new);
            }
        }
        
        //old way hash_equals(crypt($passwd, $passwd_hashed), $passwd_hashed)
        
        return $res;
    }
    

    //==========================================================================
    //
    //  nakala_2:{label:"Nakala",params:{writeApiKey: '111', writeUser: '', writePwd: '', readApiKey: '2222', readUser: '', …},
    //              service:"nakala",service_id:"nakala_2",usr_ID:"2"}
    //
    //
    //  Saves repository credentials in ugr_Preferences
    //
    function user_saveRepositoryCredentials($system, $new_prefs, $to_remove) {

        $res = false;

        if(is_string($new_prefs)){
            $new_prefs = json_decode($new_prefs, true);
        }

        if(isEmptyArray($new_prefs) && isEmptyArray($to_remove)) {

            $system->addError(HEURIST_INVALID_REQUEST, 'Data to update repository configuration are not defined');
            return false;
        }

        //get groups for current user
        $wg_ids = array();
        $currentUser = $system->getCurrentUser();
        if($currentUser && @$currentUser['ugr_ID']>0)
        {
            if(@$currentUser['ugr_Groups']){
                $wg_ids = array_keys($currentUser['ugr_Groups']);
            }
            array_push($wg_ids, $currentUser['ugr_ID']);
        }else{
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }


        if($system->isAdmin()){
            // be sure to include the generic everybody workgroup
            array_push($wg_ids, 0);
        }

        $mysqli = $system->getMysqli();

        //prepare services - group by group/user ids
        $prepared = array();

        if(is_array($new_prefs)){
            foreach($new_prefs as $service_id=>$service){
            if(in_array($service['usr_ID'], $wg_ids)){
                $usr_ID = intval($service['usr_ID']);
                if(!@$prepared[$usr_ID]) {$prepared[$usr_ID] = array();}

                $prepared[$usr_ID][$service_id] = $service;
            }
        }
        }

        if(is_array($to_remove)){
            foreach($to_remove as $service_id){

                $parts = explode('_',$service_id);
                $usr_ID = end($parts);

                if(!@$prepared[$usr_ID][$service_id] && in_array($usr_ID, $wg_ids)){
                    //new value does not exists
                    $prepared[$usr_ID][$service_id] = 'delete';
                }
            }
        }

        //save into database
        if(empty($prepared)){
            $system->addError(HEURIST_INVALID_REQUEST, 'Data to update repository configuration are not defined');
            return false;
        }

        foreach($prepared as $usr_ID=>$services){

            $prefs = mysql__select_value( $mysqli, 'select ugr_Preferences from sysUGrps where ugr_ID='.$usr_ID);
            if($prefs!=null && $prefs!=''){
                $prefs = json_decode($prefs, true);
            }
            if($prefs==null || empty($prefs)){
                $prefs = array();
            }

            $curr_services = @$prefs['externalRepositories'];
            //if password is not set take passwords from current settings
            foreach($services as $service_id=>$service){
                if(is_array($to_remove) && in_array($service_id,$to_remove)){

                    if($service=='delete'){
                        unset($services[$service_id]);
                    }
                    //do not take password from the existing one - it was removed
                }
                /*
                if(!isEmptyArray($curr_services)){
                    if(@$curr_services[$service_id]['params']['writePwd']){ //old passsword exists
                        if(!@$service['params']['writePwd']){ //new password not defined
                            $services[$service_id]['params']['writePwd'] = $curr_services[$service_id]['params']['writePwd'];
                        }
                    }
                    if(@$curr_services[$service_id]['params']['readPwd']){ //old passsword exists
                        if(!@$service['params']['readPwd']){ //new password not defined
                            $services[$service_id]['params']['readPwd'] = $curr_services[$service_id]['params']['readPwd'];
                        }
                    }
                }*/
            }

            if(empty($services)){
                if(@$prefs['externalRepositories']){
                    unset($prefs['externalRepositories']);
                }
            }else{
                $prefs['externalRepositories'] = $services;
            }

            //$res = mysql__insertupdate( $mysqli, 'sysUGrps', 'ugr', array(
            //            'ugr_ID'=>$usr_ID,
            //            'ugr_Preferences'=>json_encode($prefs) ));

            $res = mysql__exec_param_query($mysqli,
                    'UPDATE `sysUGrps` set ugr_Preferences=? WHERE ugr_ID='.$usr_ID,
                    array('s', (empty($prefs)?'':json_encode($prefs))));

            if(!$res){
                break;
            }

        }//for

        return $res;
    }


    //
    // returns credentials for given service_id  (service_name+user_id)
    //
    function user_getRepositoryCredentials2($system, $serviceId) {

        // Chcek if serviceId is a testing one
        //  these keys are publicly available from their respective services
        $TEST_KEYS = [
            // Nakala => https://test.nakala.fr/
            'tnakala' => '01234567-89ab-cdef-0123-456789abcdef',
            'unakala1' => '33170cfe-f53c-550b-5fb6-4814ce981293',
            'unakala2' => 'f41f5957-d396-3bb9-ce35-a4692773f636',
            'unakala3' => 'aae99aba-476e-4ff2-2886-0aaf1bfa6fd2'
        ];
        if(array_key_exists($serviceId, $TEST_KEYS)){
            return [ $serviceId => [ 'params' => [ 'writeApiKey' => $TEST_KEYS[$serviceId] ] ] ];//implode('-', $TEST_KEYS[$serviceId])
        }

        $parts = explode('_', $serviceId);
        $ugr_ID = end($parts);
        if(count($parts)>2){
            $serviceName = implode('_',array_slice($parts,0,count($parts)-1));
        }else{
            $serviceName = $parts[0];
        }

        return user_getRepositoryCredentials($system, false, $ugr_ID, $serviceName);
    }

    //
    // returns read/write credentials for given service and user_id  (for edit on client side)
    //
    function user_getRepositoryCredentials($system, $search_all_groups, $ugr_ID, $serviceName=null) {

        //1. search all workgroups
        $ugr_ID = intval($ugr_ID);

        if($search_all_groups){
            $query = 'SELECT ugr_ID, ugr_Preferences FROM sysUGrps '
                    .' WHERE ugr_ID=0 OR ugr_ID='.$ugr_ID
                    .' OR ugr_ID in (SELECT ugl_GroupID FROM sysUsrGrpLinks WHERE ugl_UserID='.$ugr_ID.')'
                    .' ORDER BY ugr_Type DESC';
        }else{
        //2 search only specific group or user
            $query = 'SELECT ugr_ID, ugr_Preferences FROM sysUGrps '
                    .' WHERE ugr_ID='.$ugr_ID;
        }

        /*
        if($all_groups){
                $query .= ' WHERE ugr_ID=0 OR ugr_ID='.intval($ugr_ID)
                .' OR ugr_ID in (SELECT ugl_GroupID FROM sysUsrGrpLinks WHERE ugl_UserID='.intval($ugr_ID).')';
        }else{
                $query .= ' WHERE ugr_ID='.intval($ugr_ID);
        }*/

        $result = null;

        $mysqli = $system->getMysqli();
        $res = $mysqli->query($query);//ugr_Type

        if(!$res){
            return array();
        }

        //2. loop and parse preferences
        $result = array();
            while ($row = $res->fetch_row()) { //loop for user/groups
                //get preferences
                $usr_ID = intval($row[0]);
                $prefs = $row[1];

                if($prefs!=null && $prefs!=''){
                    $prefs = json_decode($prefs, true);
                    if(!isEmptyArray($prefs) && array_key_exists('externalRepositories',$prefs)){
                        $prefs = $prefs['externalRepositories'];
                        if(!isEmptyArray($prefs)){
                            if($serviceName==null || $serviceName=='all'){
                                //all services
                                $result = array_merge($result, $prefs);
                            }else{
                                foreach($prefs as $service_id=>$service){
                                    if(@$service['service']==$serviceName){
                                         $result[$service_id] = $service;
                                         if(!$search_all_groups) {break;}
                                    }
                                }
                            }
                        }
                    }
                }
                //3. extract required service

            }
            $res->close();

        return $result;
    }

    //
    // returns list of available/writeable external repositories for given user
    //
    //   ugr_ID, ugr_Name,  serviceName
    //
    function user_getRepositoryList($system, $ugr_ID, $writeOnly){

        $result = array();

        $ugr_ID = intval($ugr_ID);

        if($ugr_ID>=0){
            //1. search all workgroups
            $query = 'SELECT ugr_ID, ugr_Name, ugr_Preferences FROM sysUGrps '
                    .' WHERE ugr_ID=0 OR ugr_ID='.$ugr_ID
                    .' OR ugr_ID in (SELECT ugl_GroupID FROM sysUsrGrpLinks WHERE ugl_UserID='.$ugr_ID.')'
                    .' ORDER BY ugr_Type DESC';


            $mysqli = $system->getMysqli();
            $res = $mysqli->query($query);//ugr_Type

            //2. loop and parse preferences
            if($res){
                while ($row = $res->fetch_row()) {
                    //get preferences
                    $prefs = $row[2];
                    if($prefs!=null && $prefs!=''){
                        $prefs = json_decode($prefs, true);
                        if(!isEmptyArray($prefs) && array_key_exists('externalRepositories',$prefs)){
                                $prefs = $prefs['externalRepositories'];
                                if(is_array($prefs)){
                                    foreach($prefs as $service_id=>$service){
                                        if(!$writeOnly || @$service['params']['writeApiKey'] || @$service['params']['writeUser'])
                                        {
                                             //$service['service'].'_'.$usr_ID,
                                             $usr_ID = intval($row[0]);
                                             $result[] = array($service_id, $service['label'], $usr_ID, $row[1]);
                                             //$usr_ID, $row[1], $service['service'], $service['label']);
                                        }
                                    }
                                }
                        }
                    }
                }
                $res->close();
            }
        }

        return $result;
    }



/**
* It checks whether a user has access to a certain system level.
*
* @param mixed $system
* @param mixed $level
*/
function userCheckAccess($system, $level=0){

    // Base login warning message
    $login_warning = 'To perform this action you must be logged in';
    $message = ''; // Initialize empty message

    // Check access based on the user level
    if ($level == 2 && !$system->isDbOwner()) {
        // Level 2: Only Database Owners allowed
        $message = $login_warning . ' as Database Owner';
    } elseif ($level == 1 && !$system->isAdmin()) {
        // Level 1: Only Administrators allowed
        $message = $login_warning . ' as Administrator of group \'Database Managers\'';
    } elseif ($level > 2 && !$system->hasAccess($level)) {
        // Levels greater than 2: Check specific access level
        $message = $login_warning . ' as Administrator of group #' . $level;
    } elseif ($level == 0 && !$system->hasAccess()) {
        // Default check for access without specific level (just logged in)
        $message = $login_warning;
    } else {
        // If all checks pass, return true
        return true;
    }

    // If access is denied, log the error and return false
    $system->addError(HEURIST_REQUEST_DENIED, $message);
    return false;
}

/**
 * Checks if a user has the necessary permissions to perform a specified Record action on the system.
 *
 * This function first verifies the user's access level through `userCheckAccess`. Then, it retrieves
 * the user's permissions from the database and determines whether the user can perform the specified
 * action (e.g., 'add', 'edit', or 'delete'). Guest users are subject to additional checks such as
 * daily limits for adding records.
 *
 * @param object $system - The system object that provides access to the current session, user, and
 *                         database interaction methods.
 * @param string $action - The action the user is attempting to perform. Accepted values include
 *                         'add', 'edit', 'delete', or other valid actions.
 * @param int $level - (Optional) The access level required for the action. Defaults to 0, meaning no
 *                     special access level is required.
 *
 * @return bool - Returns true if the user has the appropriate permissions and is allowed to
 *                perform the Record action, otherwise false adds HEURIST_ACTION_BLOCKED error to $system
 */
function userCheckPermissions($system, $action, $level=0){

    if(!userCheckAccess($system, $level)){
        return false;
    }

    $mysqli = $system->getMysqli();

    $user_query = 'SELECT ugr_Enabled FROM sysUGrps WHERE ugr_ID=' . intval($system->getUserId());

    //'y','n','y_no_add','y_no_delete','y_no_add_delete'
    $permissions = mysql__select_value($mysqli, $user_query);

    if($permissions==null){
        $system->addError(HEURIST_DB_ERROR,
                'Cannot obtain User Permissions.<br>Please contact the Heurist team, if this persists.',
                $mysqli->error);
        return false;
    }

    // Define action message for error
    // PHP8
    /*
    $action_msg = match ($action) {
        'add' => 'create',
        'edit' => 'modify',
        'add delete' => 'create or delete',
        default => $action,
    };
    */

    $action_msg = $action;

    if($action=='add'){
        $action_msg = 'create';
    }elseif($action=='edit'){
        $action_msg = 'modify';
    }elseif($action=='add delete'){
        $action_msg = 'create or delete';
    }

    $block_msg = 'Your account does not have permission to ' . $action_msg
                .' records,<br>please contact the database owner for more details.';

    $result = true; // Default result

    // If user permissions are disabled (n)
    if($permissions == 'n'){

        // Guest users are allowed to add records, but have a daily limit
        if(!($action == 'add' && $system->isGuestUser())){
            $system->addError(HEURIST_ACTION_BLOCKED, 'Only enabled accounts can ' . $action_msg . ' records.');
            return false;
        }

        // Guest user: Check daily limit
        $cnt_added_by_guests = mysql__select_value($mysqli,
        'SELECT count(rec_ID) FROM Records, sysUGrps WHERE ugr_ID=rec_AddedByUGrpID and ugr_Enabled="n" AND DATE(rec_Added)=CURDATE()');

        if($cnt_added_by_guests>199){
            $system->addError(HEURIST_ACTION_BLOCKED, 'The number of records added by guest users for the current database exceeds the allowed daily limit.');
            $result = false;
        }

    }elseif(  ($permissions == 'y_no_add')  // Read-only
            || ($action == 'add' && strpos($permissions, 'add') !== false)
            || ($action == 'delete' && strpos($permissions, 'delete') !== false)){

        // User doesn't have permission to add or delete
        $system->addError(HEURIST_ACTION_BLOCKED, $block_msg);
        $result = false;
    }

    return $result;
}


?>
