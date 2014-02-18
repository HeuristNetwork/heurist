<?php
require_once (dirname(__FILE__) . '/../../../configIni.php'); // read in the configuration file

require_once (dirname(__FILE__).'/consts.php');
require_once (dirname(__FILE__).'/common/utils_db.php');
require_once (dirname(__FILE__).'/common/db_users.php');

/**
*  Class that contains mysqli (dbconnection), current user and system settings
*  
*  it performs system initialization: 
*   a) establish connection to server
*   b) define sytems constans - paths
*   c) perform login and load current user info
*   d) load system infor (from sysIdentification)
*   e) keeps array of errors
* 
* system constants:
* 
* HEURIST_THUMB_DIR
* HEURIST_UPLOAD_DIR
*/
class System {
/*
    const INVALID_REQUEST = "invalid";    // The Request provided was invalid.
    const NOT_FOUND = "notfound";         // The requested object not found.
    const OK = "ok";                      // The response contains a valid Result.
    const REQUEST_DENIED = "denied";      // The webpage is not allowed to use the service.
    const UNKNOWN_ERROR = "unknown";       // A request could not be processed due to a server error. The request may succeed if you try again.
    const SYSTEM_FATAL  = "system";        // System fatal configuration
*/

    private $mysqli = null;
    private $dbname_full = null;
    private $dbname = null;
    private $session_refix = null;

    private $errors = array();

    //???
    //private $guest_User = array('ugr_ID'=>0,'ugr_FullName'=>'Guest');
    private $current_User = null;
    private $system_settings = null;

    /**
    * Read configuration parameters from config file
    *
    * Establish connection to server
    * Open database
    *
    * @param $db - database name
    * @param $dbrequired - if false only connect to server (for database list)
    * @return true on success
    */
    public function init($db, $dbrequired=true){
        
        if($db){
            $this->dbname = $db;
            if(!(strpos($db, HEURIST_DB_PREFIX)===0)){
                $db = HEURIST_DB_PREFIX.$db;
            }
            $this->dbname_full = $db;

        }else if($dbrequired){
            $this->addError(HEURIST_INVALID_REQUEST, "Database not defined");
            $this->mysqli = null;
            return false;
        }

        //dbutils
        $res = mysql_connection(HEURIST_DBSERVER_NAME, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, $this->dbname_full);
        if ( is_array($res) ){
            $this->addError($res[0], $res[1]);
            $this->mysqli = null;
            return false;
        }else{
            $this->mysqli = $res;

            if($this->dbname_full)  //database is defined
            {
                if(!$this->get_system()){
                    return false;
                }

                $this->start_my_session();
                $this->login_verify(); //load user info from session

                define('HEURIST_DBNAME',$this->dbname);
                define('HEURIST_DBNAME_FULL',$this->dbname_full);
                //@todo  - test upload and thumb folder exist and writeable
                define('HEURIST_UPLOAD_DIR', @$_SERVER["DOCUMENT_ROOT"] .'/HEURIST_FILESTORE/' . $this->dbname . '/');
                define('HEURIST_THUMB_DIR', @$_SERVER["DOCUMENT_ROOT"] .'/HEURIST_FILESTORE/' . $this->dbname . '/filethumbs/');
                define('HEURIST_THUMB_BASE_URL', HEURIST_SERVER_URL .'/HEURIST_FILESTORE/' . $this->dbname . '/filethumbs/' );

            }
            return true;
        }

    }

    /**
    * return list of errors
    */
    public function getError(){
        return $this->errors;
    }

    /**
    * keep error message (for further use with getError) 
    */
    public function addError($status, $message='', $sysmsg=null){
        $this->errors = array("status"=>$status, "message"=>$message, "sysmsg"=>$sysmsg);
        return $this->errors;
    }

    /**
    * Returns all info for current user and some sys config parameters
    */
    public function getCurrentUserAndSysInfo(){

            $user = $this->current_User;
            if($user) {
                $user['ugr_Password'] = ''; // do not send password to client side
                $user['ugr_Admin'] = $this->is_admin();
                if(!@$user['ugr_Preferences']) $user['ugr_Preferences'] = user_getPreferences();
            }
            
            $dbowner = user_getDbOwner($this->mysqli);
            
            $res = array(
                            "currentUser"=>$user,
                            "sysinfo"=>array(
                                    "registration_allowed"=>$this->get_system('sys_AllowRegistration'), 
                                    "help"=>HEURIST_HELP,
                                    "dbowner_name"=>@$dbowner['ugr_FirstName'] . ' ' . @$dbowner['ugr_LastName'],
                                    "dbowner_email"=>@$dbowner['ugr_eMail'])
                        );
            return $res;
     }


    /**
    * Get current user info
    */
    public function getCurrentUser(){
        // $this->current_User['ismaster'] = $system->is_admin();
        return $this->current_User; // ?$this->current_User :$this->$guest_User;
    }
    
    /**
    * Set current user info
    * 
    * @param mixed $user
    */
    public function setCurrentUser($user){
        $this->current_User = $user;
    }

    /**
    * Get if of current user, if not looged in returns zero
    * 
    */
    public function get_user_id(){
        return $this->current_User? $this->current_User['ugr_ID'] :0;
    }
    
    /**
    * Returns array of ID of all groups for current user plus current user ID
    */
    public function get_user_group_ids(){

        if($this->current_User){
            $groups = @$this->current_User['ugr_Groups'];
            if($groups){
                $groups = array_keys($groups);
            }else{
                $groups = array();
            }
            array_push($groups, $this->current_User['ugr_ID']);
            return $groups;
        }else{
            null;
        }
    }
    
    /**
    * Returns true if given id is id of current user or it is id of member of one of current user group
    * 
    * @param mixed $ug - user ID to check
    */
    public function is_member($ug){
        return ($ug==0 || $this->get_user_id()==$ug ||  @$this->current_User['ugr_Groups'][$ug]);
    }
    
    /**
    * Returns user group ID if given id is id of database owner or admin of given group otehrwise it returns FALSE
    * 
    * @param mixed $ugrID - user group id, if it is omitted it takes current user ID
    */
    function is_admin2($ugrID){

        if(!$ugrID){
            $ugrID = $this->get_user_id();
        }
        if($this->is_admin() || $this->is_admin('group', $ugrID)){
            return $ugrID;
        }else{
            return false;
        }
    }

    /**
    * Returns true if current user id databse owner admin or admin of given group (depends on context)
    * 
    * @param mixed $contx - databasde or group
    * @param mixed $ug - group ID 
    */
    public function is_admin($contx = 'database', $ug = 0){

        if($this->get_user_id()<1) return false;     //not logged in

        switch ($contx) {
            case 'group':
                if ($ug == 0 || $ug == $this->get_user_id()) return true;
                if ($ug > 0){
                    return ( "admin" == @$this->current_User['ugr_Groups'][$ug] );
                }
                return false;
                break;
            case 'database':
            default:
                $sysvals = $this->get_system();
                return  ( "admin" ==  @$this->current_User['ugr_Groups'][$sysvals['sys_OwnerGroupID']] ); //admin in db owners group
        }
    }

    /**
    * Get database connection object
    */
    public function get_mysqli(){
        return $this->mysqli;
    }
    
    /**
    * Get full name of database
    */
    public function dbname_full(){
        return $this->dbname_full;
    }

    /**
    * Restore session by cookie id, or start new session
    */
    private function start_my_session(){

//DEBUG error_log($_SERVER['PHP_SELF']." Start session Cooook:".@$_COOKIE['heurist-sessionid']);
        
        if (@$_COOKIE['heurist-sessionid']) {
            session_id($_COOKIE['heurist-sessionid']);
            session_cache_limiter('none');
            session_start();
        } else {
            //session_id(sha1(rand()));
            session_start();
            $session_id = session_id();
            /*
            $res = setcookie('heurist-sessionid', $session_id, time()+3600*24*30, '/', HEURIST_SERVER_NAME);
            if(!$res){
                error_log("Cooookie no SAVED");
            }else{
                error_log("Cooookie OK");
            }
            */
        }

        //session_cache_limiter('none');
        //session_start();
    }

    /**
    * Load user info from session
    */
    public function login_verify(){
        $userID = @$_SESSION[$this->dbname_full]['ugr_ID'];
        $islogged = ($userID != null);
        if($islogged){
            
//DEBUG error_log($_SERVER['PHP_SELF']." login_verify>>>".print_r(@$_SESSION[$this->dbname_full]['ugr_Preferences'],true)  );            
            
            if(!@$_SESSION[$this->dbname_full]['ugr_Groups']){
                $_SESSION[$this->dbname_full]['ugr_Groups'] = user_getWorkgroups( $this->mysqli, $userID );
            }
            if(!@$_SESSION[$this->dbname_full]['ugr_Preferences']){
                $_SESSION[$this->dbname_full]['ugr_Preferences'] = user_getPreferences();
            }

            $this->current_User = array(
                'ugr_ID'=>$userID,
                'ugr_FullName'=>$_SESSION[$this->dbname_full]['ugr_FullName'],
                'ugr_Groups' => $_SESSION[$this->dbname_full]['ugr_Groups'],
                'ugr_Preferences' => $_SESSION[$this->dbname_full]['ugr_Preferences']);

            if (@$_SESSION[$this->dbname_full]['keepalive']) {
                //update cookie - to keep it alive
//ARTEM                setcookie('heurist-sessionid', session_id(), time() + 7*24*60*60, '/', HEURIST_SERVER_NAME);
            }
        }
        return $islogged;
    }


    /**                                                     
    * Find user by name and password and keeps user info in current_User and in session
    *
    * @param mixed $username
    * @param mixed $password
    * @param mixed $session_type   - public, shared, remember
    *
    * @return  TRUE if login is success
    */
    public function login($username, $password, $session_type){

        if($username && $password){

            //db_users
            $user = user_getByField($this->mysqli, 'ugr_Name', $username);

            
//DEBUG error_log($user['ugr_Enabled'].">>>=".crypt($password, $user['ugr_Password'])."   ".$user['ugr_Password']);
            
            if($user){
            
                if($user['ugr_Enabled'] != 'y'){
                    
                    $this->addError(HEURIST_REQUEST_DENIED,  "Your user profile is not active. Please contact database owner");
                    return false;
                    
                }else if ( crypt($password, $user['ugr_Password']) == $user['ugr_Password'] ) {

                $_SESSION[$this->dbname_full]['ugr_ID'] = $user['ugr_ID'];
                $_SESSION[$this->dbname_full]['ugr_Name'] = $user['ugr_Name'];
                $_SESSION[$this->dbname_full]['ugr_FullName'] = $user['ugr_FirstName'] . ' ' . $user['ugr_LastName'];
                //@todo $_SESSION[$this->dbname_full]['user_access'] = $groups;
                //$_SESSION[$this->dbname_full]['cookie_version'] = COOKIE_VERSION;

                $time = 0;
                if($session_type == 'public'){
                    $time = 0;
                }else if($session_type == 'shared'){
                    $time = time() + 24*60*60;
                }else if ($session_type == 'remember') {
                    $time = time() + 7*24*60*60;
                    $_SESSION[$this->dbname_full]['keepalive'] = true;
                }
                $cres = setcookie('heurist-sessionid', session_id(), $time); //, '/', HEURIST_SERVER_NAME);
                if(!$cres){
                    error_log("Cookie no SAVED");
                }

                //update login time in database
                user_updateLoginTime($this->mysqli, $user['ugr_ID']);

                //keep current user info
                $user['ugr_FullName'] = $user['ugr_FirstName'] . ' ' . $user['ugr_LastName'];
                $user['ugr_Password'] = '';
                $user['ugr_Groups'] = user_getWorkgroups( $this->mysqli, $user['ugr_ID'] );
                $user['ugr_Preferences'] = user_getPreferences();
                $this->current_User = $user;
                /*
                $this->current_User = array(
                        'ugr_ID'=>$user['ugr_ID'],
                        'ugr_FullName'=>$user['ugr_FirstName'] . ' ' . $user['ugr_LastName'],
                        'ugr_Groups' => user_getWorkgroups( $this->mysqli, $user['ugr_ID'] ),
                        'ugr_Preferences' => user_getPreferences() );
                */

                //header('Location: http://localhost/h4/index.php?db='.$this->dbname);

                return true;
                }

            }else{
                $this->addError(HEURIST_REQUEST_DENIED,  "Incorrect username / password");
                return false;
            }

        }else{
            $this->addError(HEURIST_INVALID_REQUEST, "Username / password not defined"); //INVALID_REQUEST
            return false;
        }
    }

    /**
    * Clears cookie and destroy session and current_User info
    */
    public function logout(){
        $cres = setcookie('heurist-sessionid', "", time() - 3600);
        $this->current_User = null;
        session_destroy();
        /*
          unset($_SESSION[$this->dbname_full]['user_id']);
          unset($_SESSION[$this->dbname_full]['user_name']);
          unset($_SESSION[$this->dbname_full]['user_realname']);
        */
        return true;
    }

    /**
    * Loads system settings (default values) from sysIdentification
    */
    public function get_system( $fieldname=null ){

        if(!$this->system_settings)
        {
            $mysqli = $this->mysqli;
            $this->system_settings = getSysValues($mysqli);
            if(!$this->system_settings){
                $this->addError(HEURIST_SYSTEM_FATAL, "Unable to read sysIdentification", $mysqli->error);
                return null;
            }
        }
        return ($fieldname) ?@$this->system_settings[$fieldname] :$this->system_settings;
    }

}
?>
