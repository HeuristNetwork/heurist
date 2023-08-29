<?php
/**
* dbUtils.php : Functions to create, delelet, clean the entire HEURIST database
*               and other functions to do with database file structure
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
* @subpackage  DataStore
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* Static class to perform database operations
*
* Methods:

* - databaseCreate() - creates empty database
* - databaseCreateFull() - creates new heurist database with file folders and ready to use
* - databaseDrop()
* - databaseDump()
* - databaseRestoreDump()
* - databaseCreateFolders
*   databaseEmpty    
*   databaseClone
* 
* - db_script()  - see utils_db_load_script.php
* 
* - databaseRegister - set register ID to sysIdentification and rectype, detail and term defintions
* - databaseNextRegisterID - get next registration ID from HEURIST_INDEX_DATABASE database
*/

require_once(dirname(__FILE__).'/../../hsapi/utilities/utils_db_load_script.php');
require_once(dirname(__FILE__).'/../../external/php/Mysqldump8.php');
require_once(dirname(__FILE__).'/../../admin/structure/import/importDefintions.php');

class DbUtils {

     /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     */
    private function __construct() {}    
    private static $mysqli = null;
    private static $system = null;
    private static $initialized = false;
    private static $db_del_in_progress = null;

    public static function initialize($mysqli=null)
    {
        if (self::$initialized)
            return;

        global $system;
        self::$system = $system;
        
        if($mysqli){
            self::$mysqli = $mysqli;
        }else{
            self::$mysqli = $system->get_mysqli();    
        }

        self::$initialized = true;
    }

    //
    // set Origin ID for rectype, detail and term defintions
    //
    public static function databaseRegister($dbID){

        self::initialize();
        
        $res = true;

        if($dbID>0){
                    $mysqli = self::$mysqli;
                    $result = 0;
                    $res = $mysqli->query("update defRecTypes set "
                        ."rty_OriginatingDBID='$dbID',rty_NameInOriginatingDB=rty_Name,rty_IDInOriginatingDB=rty_ID "
                        ."where (rty_OriginatingDBID = '0') OR (rty_OriginatingDBID IS NULL) ");
                    if ($res===false) {$result = 1; }
                    // Fields
                    $res = $mysqli->query("update defDetailTypes set "
                        ."dty_OriginatingDBID='$dbID',dty_NameInOriginatingDB=dty_Name,dty_IDInOriginatingDB=dty_ID "
                        ."where (dty_OriginatingDBID = '0') OR (dty_OriginatingDBID IS NULL) ");
                    if ($res===false) {$result = 1; }
                    // Terms
                    $res = $mysqli->query("update defTerms set "
                        ."trm_OriginatingDBID='$dbID',trm_NameInOriginatingDB=trm_Label, trm_IDInOriginatingDB=trm_ID "
                        ."where (trm_OriginatingDBID = '0') OR (trm_OriginatingDBID IS NULL) ");
                    if ($res===false) {$result = 1; }

                    
                    if ($result == 1){
                        self::$system->addError(HEURIST_DB_ERROR,
                                    'Error on update IDs "IDInOriginatingDB" fields for database registration '.$dbID, $mysqli->error);
                        $res = false;
                    }
        }
        return $res;
    }    

    //
    // set Origin ID for rectype, detail and term defintions for 9999 
    // (after import from unregistered database)
    //
    public static function updateImportedOriginatingIds(){

        self::initialize();
        
        $res = true;
        
        $dbID = 0;    
        if(defined('HEURIST_DBID')){
            $dbID = HEURIST_DBID;    
        }

        $mysqli = self::$mysqli;
        $result = 0;
        $res = $mysqli->query("update defRecTypes set "
            ."rty_OriginatingDBID='$dbID',rty_NameInOriginatingDB=rty_Name,rty_IDInOriginatingDB=rty_ID "
            ."where (rty_OriginatingDBID = '9999')");
        if ($res===false) {$result = 1; }
        // Fields
        $res = $mysqli->query("update defDetailTypes set "
            ."dty_OriginatingDBID='$dbID',dty_NameInOriginatingDB=dty_Name,dty_IDInOriginatingDB=dty_ID "
            ."where (dty_OriginatingDBID = '9999')");
        if ($res===false) {$result = 1; }
        // Terms
        $res = $mysqli->query("update defTerms set "
            ."trm_OriginatingDBID='$dbID',trm_NameInOriginatingDB=trm_Label, trm_IDInOriginatingDB=trm_ID "
            ."where (trm_OriginatingDBID = '9999')");
        if ($res===false) {$result = 1; }

        
        if ($result == 1){
            self::$system->addError(HEURIST_DB_ERROR,
                        'Error on update IDs "IDInOriginatingDB" fields for unregistered (imported) definitions '.$dbID, $mysqli->error);
            $res = false;
        }
        
        return $res;
    }    

    
    //
    // $params - registration parameters
    //  return database ID or string "0,error message"
    //
    // this function switches database connection to HEURIST_INDEX_DATABASE
    // it is mandatory to swtich it back mysql__usedatabase($mysqli, HEURIST_DBNAME); 
    //
    public static function databaseNextRegisterID($params){
        
        self::initialize();
        
        //switch to master index 
        $mysqli = self::$mysqli;
        
        $connect_failure = (mysql__usedatabase($mysqli, HEURIST_INDEX_DATABASE)!=true);
        if($connect_failure){
            return '0, Failed to connect to  Master Index database';
        }
        
        $indexdb_user_id = 0; // Flags problem if not reset

        // Get parameters passed from registration request
        // @ preceding $params avoids errors, sets Null if parameter missing
        $serverURL = $params["serverURL"];
        $serverURL_lc = strtolower($params["serverURL"]);
        $dbReg = $params["dbReg"];
        $dbTitle = $params["dbTitle"];
        $dbVersion = @$params["dbVer"];
        $usrEmail = $params["usrEmail"];
        $usrPassword = $params["usrPassword"];
        $usrName = $params["usrName"];
        $usrFirstName = $params["usrFirstName"];
        $usrLastName = $params["usrLastName"];
        $newid = intval(@$params["newid"]);

        // $var is null, blank, 0 or false --> false
        if (!$serverURL || !$dbReg || !$dbTitle || !$usrEmail || !$usrName || !$usrFirstName || !$usrLastName || !$usrPassword) { // error in one or more parameters
            $returnData = '0,Bad parameters passed';
            return $returnData;
        }

        if(strpos($serverURL_lc,'http://')===false && strpos($serverURL_lc,'https://')===false){
            $serverURL = 'https://'.$serverURL;  //https by default
            $serverURL_lc = strtolower($serverURL);
        }

        if(strpos($serverURL_lc, '//localhost')>0 ||  strpos($serverURL_lc, '//127.0.0.1')>0 || strpos($serverURL_lc, '//web.local')>0){
            return '0,Impossible to register database from local server '.htmlspecialchars($serverURL);
        }

        // the record type for database (collection) descriptor records - fixed for Master database
        $rty_ID_registered_database = ConceptCode::getRecTypeLocalID(HEURIST_INDEX_DBREC);        

        
        // if database is on main server it is possible to register database with user-defined ID        
        if($newid>0){ 

            if(!(strpos(strtolower($serverURL_lc), strtolower(HEURIST_MAIN_SERVER))===0)){ 
            
                return '0,It is possible to assign arbitrary ID for databases on heurist servers only';
            }
            
            $rec_id = mysql__select_value($mysqli, 'select rec_ID from Records where rec_ID='.$newid);
            
            if($rec_id>0){
                return '0,Database ID '.$newid.' is already allocated. Please choose different number';
            }
        }        
        
        // allocate a new user for this database unless the user's email address is recognised
        // If a new user, log the user in and assign the record ownership to that user
        // By allocating users on the database based on email address we can allow them to edit their own registrations
        // but they can't touch anyone else's

        // Find the registering user in the index database, make them the owner of the new record
        $usrEmail = strtolower(trim($usrEmail));

        $indexdb_user_id = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where lower(ugr_eMail)="'
                .$mysqli->real_escape_string($usrEmail).'"');

        // Check if the email address is recognised as a user name
        // Added 19 Jan 2012: we also use email for ugr_Name and it must be unique, so check it has not been used
        if(!($indexdb_user_id>0)) { // no user found on email, try querying on user name
            $indexdb_user_id = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where lower(ugr_Name)="'
                .$mysqli->real_escape_string($usrEmail).'"');
        }

        if(!($indexdb_user_id>0)) { // did not find the user, create a new one and pass back login info
            
            // Note: we use $usrEmail as user name because the person's name may be repeated across many different users of
            // different databases eg. there are lots of johnsons, which will cause insert statement to fail as ugr_Name is unique.
            
            $indexdb_user_id = mysql__insertupdate($mysqli, 'sysUGrps', 'ugr_', 
                array(
                    'ugr_Name'=>$usrEmail,
                    'ugr_Password'=>$usrPassword,
                    'ugr_eMail'=>$usrEmail,
                    'ugr_Enabled'=>'y',
                    'ugr_FirstName'=>$usrFirstName,
                    'ugr_LastName'=>$usrLastName,
                )
            );
            
            if(!($indexdb_user_id>0)) { // Unable to create the new user
                return '0,Unable to write new user in Heurist master index database<br>'.
                    'Please '.CONTACT_HEURIST_TEAM.' for advice';   
            }
        }        
        
        

        // write the core database record describing the database to be registered and allocate registration ID
        // This is not a fully valid Heurist record, we let the edit form take care of that
        // First look to see if there is an existing registration - note, this uses the URL to find the record, not the registration ID
        // TODO: Would be good to have a recaptcha style challenge otherwise can be called repeatedly
        // with slight URL variations to spawn multiple registrations of dummy databases

        $dbID = mysql__select_value($mysqli, "select rec_ID from Records where lower(rec_URL)='".
                        $mysqli->real_escape_string($serverURL_lc)."'");

        if($dbID>0) { 
            
            return $dbID;
            
        }else{// new registration

            $mysqli->query('set @logged_in_user_id = 2');

            $dbID = mysql__insertupdate($mysqli, 'Records', 'rec_', 
                array(
                    'rec_ID'=>($newid>0)?-$newid:0,
                    'rec_URL'=>$mysqli->real_escape_string($serverURL),
                    'rec_Added'=>date('Y-m-d H:i:s'),
                    'rec_Title'=>$mysqli->real_escape_string($dbTitle),
                    'rec_RecTypeID'=> $rty_ID_registered_database,
                    'rec_AddedByImport'=>0,
                    'rec_OwnerUGrpID'=>$indexdb_user_id,
                    'rec_NonOwnerVisibility'=>'public',
                    'rec_Popularity'=>99,
                ), true
            );
            $mysqli->query('set @logged_in_user_id = '.self::$system->get_user_id());
            
            if($dbID>0){
                
                self::$system->defineConstant('DT_NAME');

                //Write the database title into the details, further data will be entered by the Heurist form
                mysql__insertupdate($mysqli, 'recDetails', 'dtl_', 
                    array(
                        'dtl_RecID'=>$dbID,
                        'dtl_DetailTypeID'=>DT_NAME,
                        'dtl_Value'=>$mysqli->real_escape_string($dbTitle)
                    )
                );

                //Write db version as detail
                if($dbVersion){
                    mysql__insertupdate($mysqli, 'recDetails', 'dtl_', 
                        array(
                            'dtl_RecID'=>$dbID,
                            'dtl_DetailTypeID'=>ConceptCode::getDetailTypeLocalID(1176-335), //version
                            'dtl_Value'=>$dbVersion
                        )
                    );
                }

                //Write db name as detail
                if($dbReg){
                    mysql__insertupdate($mysqli, 'recDetails', 'dtl_', 
                        array(
                            'dtl_RecID'=>$dbID,
                            'dtl_DetailTypeID'=>ConceptCode::getDetailTypeLocalID("1176-469"),
                            'dtl_Value'=>$dbReg
                        )
                    );   
                }

                // Write the record bookmark into the bookmarks table. This allos the user registering the database
                // to see thir lsit of databases as My Bookmarks
                mysql__insertupdate($mysqli, 'usrBookmarks', 'bkm_', 
                    array(
                        'bkm_UGrpID'=>$indexdb_user_id,
                        'bkm_RecID'=>$dbID
                    )
                );
                

                //send email to administrator about new database registration
                $email_text =
                "There is a new Heurist database registration on the Heurist Reference Index\n\n".
                "Database Title:     ".htmlspecialchars($dbTitle, ENT_QUOTES, 'UTF-8')."\n".
                "Registration ID:    ".$dbID."\n". // was $indexdb_user_id, which is always 0 b/cnot yet logged in to master index
                "DB Format Version:  ".$dbVersion."\n\n".
                // "User name:    ".$usrFirstName." ".$usrLastName."\n".  // comes out 'every user' b/c user not set
                // "Email address: ".$usrEmail."\n".                      // comes out 'not set for user 0'
                "Go to the address below to review the database:\n".
                $serverURL;

                $dbowner = user_getDbOwner($mysqli);
                $dbowner_Email = $dbowner['ugr_eMail'];
                $email_title = 'Database registration ID: '.$dbID.'. User ['.$indexdb_user_id.']';

                //sendEmail($dbowner_Email, $email_title, $email_text);
//TEMP it is very slow on intersect server                sendEmail_native($dbowner_Email, $email_title, $email_text, null);
                //END email -----------------------------------

                
                return $dbID;
            }else{
                
                self::$system->addError(HEURIST_DB_ERROR, 'Cannot write record in Heurist master index ', $dbID);
                
                $error = 'Cannot write record in Heurist master index database<br>'
                .'The URL may have been registered with a previous database.<br>'
                .'Please '.CONTACT_HEURIST_TEAM.' for advice';
                return '0,'. $error;
            }
        
        }
        
    }
    
    //
    // Remotely update registered database details
    // @todo Needs some sort of validation
    public static function updateRegisteredDatabase($params){

        self::initialize(); 
        $mysqli = self::$mysqli;
        
        //switch to master index
        $connect_failure = (mysql__usedatabase($mysqli, HEURIST_INDEX_DATABASE)!=true);
        if($connect_failure){
            return 'Failed to connect to Master Index database';
        }

        // Get parameters passed from update request
        $serverURL = $params["serverURL"];
        $serverURL_lc = strtolower($params["serverURL"]);
        $dbReg = $params["dbReg"]; // Database name
        $dbTitle = $params["dbTitle"]; // Database description
        $usrEmail = $params["usrEmail"];
        $usrPassword = $params["usrPassword"];
        $dbID = intval(@$params["dbID"]);

        // $var is null, blank, 0 or false --> false
        if ( (!($dbID>0)) || (!$serverURL && !$dbReg && !$dbTitle)) { // error in one or more parameters
            $returnData = 'Bad parameters passed';
            return $returnData;
        }

        // Check the record exists
        $res = mysql__select_value($mysqli, 'SELECT rec_ID FROM Records WHERE rec_ID = ' . $dbID);
        if(!$res){
            return 'Unable to locate database record id '.$dbID;
        }

        if(strpos($serverURL_lc,'http://')===false && strpos($serverURL_lc,'https://')===false){
            $serverURL = 'https://'.$serverURL;  //https by default
            $serverURL_lc = strtolower($serverURL);
        }

        if(strpos($serverURL_lc, '//localhost')>0 ||  strpos($serverURL_lc, '//127.0.0.1')>0 || strpos($serverURL_lc, '//web.local')>0){
            return 'Registered databases cannot be on local server '.htmlspecialchars($serverURL);
        }

        $user_id = 0; // existing record owner
        // Retrieve user - OWNER CAN BE CHANGED + DETAILS CAN BE CHANGED
        $usrEmail = strtolower(trim($usrEmail));
        $user_id = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where lower(ugr_eMail)="'
                    .$mysqli->real_escape_string($usrEmail).'"');

        // Check if the email address is recognised as a user name
        if($user_id <= 0){
            $user_id = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where lower(ugr_Name)="'
                    .$mysqli->real_escape_string($usrEmail).'"');
        }

        // Validate password
        $valid_password = !empty($usrPassword);
        if($valid_password && $user_id > 0){
            $user_pwd = mysql__select_value($mysqli, 'select ugr_Password from sysUGrps where ugr_ID=' . intval($user_id));
            $valid_password = hash_equals(crypt($usrPassword, $user_pwd), $user_pwd);
        }

        // Unable to retrieve existing user or provided password is wrong
        if($user_id <= 0 || $valid_password){
            return ($user_id <= 0 ? 'We were unable to retrieve your user account within the Heurist Index database.' 
                    : 'We were unable to authenicate your account on the Heurist Index database')
                . '<br>Please ensure that your email address and password on the Heurist Index database match your current email address and password.'
                . '<br>Contact the Heurist team if you require help with updating your email address and password on the Heurist Index database.';
        }

        // Check user is owner of record
        $res = mysql__select_value($mysqli, 'SELECT rec_ID FROM Records WHERE rec_ID = ' . $dbID . ' AND rec_OwnerUGrpID = ' . $user_id);
        if(!$res){
            return 'You do not own the record for this registered database, this could be due to a previous transfer in database ownership.'
                . '<br>Please contact the Heurist team and request that the record for your database be updated.';
        }

        if(!empty($serverURL) || !empty($dbTitle)){
            $record = array(
                'rec_ID'=>$dbID,
                'rec_Modified'=>date('Y-m-d H:i:s')
            );

            $err_msg = '';
            if(!empty($serverURL)){
                $record['rec_URL'] = $mysqli->real_escape_string($serverURL);
                $err_msg = 'URL (server URL)';
            }
            if(!empty($dbTitle)){
                $record['rec_Title'] = $mysqli->real_escape_string($dbTitle);
                $err_msg = $err_msg . (!empty($err_msg) ? ' and ' : '') . 'Title (database name)';
            }
            $res = mysql__insertupdate($mysqli, 'Records', 'rec_', $record, true);

            if(!$res && $res != $dbID){
                return 'Failed to update record\'s ' . $err_msg . ', Error: ' . $mysqli->error;
            }
        }

        // Database name
        if($dbReg){

            $dty_id = ConceptCode::getDetailTypeLocalID("1176-469");
            $fld_id = mysql__select_value($mysqli, 'SELECT dtl_ID FROM recDetails WHERE dtl_DetailTypeID='.$dty_id.' AND dtl_RecID='.$dbID);

            $detail = array(
                'dtl_DetailTypeID'=>$dty_id,
                'dtl_Value'=>$dbReg
            );

            if($fld_id != null){ // update
                $detial['dtl_ID'] = $fld_id;
            }else{ // insert - shouldn't be needed
                $detail['dtl_RecID'] = $dbID;
            }

            mysql__insertupdate($mysqli, 'recDetails', 'dtl_', $detail);   
        }

        return $dbID;
    }

    //
    // remove database entirely
    // $database_name - name of database to be deleted
    // $createArchive - create db dump and archive all uploaded files
    //
    // 1. Create an SQL dump in the filestore directory
    // 2. Zip the filestore directories (using bzip2) directly into the DELETED_DATABASES directory
    // 3. Delete filestore directory for the database
    // 4. Drop database
    // 5. Append row to DELETED_DATABASES_LOG.csv in the Heurist filestore. 
    //
    public static function databaseDrop( $verbose=false, $database_name=null, $createArchive=false ){
        
//error_log('databaseDrop '.$database_name.'   '.$createArchive);
//error_log(debug_print_backtrace());

        self::initialize();

        if(self::$db_del_in_progress!==null){
            error_log('DELETION ALREADY IN PROGRESS '.self::$db_del_in_progress);
            return false;
        }
        
        self::$db_del_in_progress = null;
        
        if($database_name==null){
            $msg = 'Database parameter not defined';
            self::$system->addError(HEURIST_INVALID_REQUEST, $msg);
            if($verbose) echo '<br>'.$msg;
            return false;
        }

        $mysqli = self::$mysqli;
        $system = self::$system;
        
        if($database_name==null && defined('HEURIST_DBNAME')) $database_name = HEURIST_DBNAME;
        
        self::$db_del_in_progress = $database_name; 
        
        list($database_name_full, $database_name) = mysql__get_names( $database_name );
        $msg_prefix = "Unable to delete <b> $database_name </b>. ";
        
        if(defined('HEURIST_DBNAME') && $database_name!=HEURIST_DBNAME){ //switch to database
           $connected = mysql__usedatabase($mysqli, $database_name_full);
        }else{
           $connected = true;
        }
        
        $archiveFolder = HEURIST_FILESTORE_ROOT."DELETED_DATABASES/";   
        $db_dump_file = null;     
        
        $source = HEURIST_FILESTORE_ROOT.$database_name.'/'; //  HEURIST_FILESTORE_DIR;  database upload folder
        $archOK = true;

        
        if(!$connected){
            $msg = $msg_prefix.'Failed to connect to database '
                    .($database_name).'  '.($createArchive);
            $system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
            if($verbose) echo '<br>'.htmlspecialchars($msg);
            self::$db_del_in_progress = null;
            return false;
        }else 
        if($createArchive) {
            // Create DELETED_DATABASES directory if needed
            if(!folderCreate($archiveFolder, true)){
                    $system->addError(HEURIST_SYSTEM_CONFIG, 
                        $msg_prefix.'Cannot create archive folder for database to be deleteted.');                
                    self::$db_del_in_progress = null;
                    return false;
            }
            
            $db_dump_file = DbUtils::databaseDump( $database_name, null, null, $verbose );
            
            if ($db_dump_file===false) {
                    $msg = $msg_prefix.'Failed to dump database to a .sql file';
                    self::$system->addError(HEURIST_SYSTEM_CONFIG, $msg);                
                    if($verbose) echo '<br/>'.htmlspecialchars($msg);
                    self::$db_del_in_progress = null;
                    return false;
            }
        
            // Zip $source to $destination
            $datetime1 = date_create('now');
            $destination = $archiveFolder.$database_name.'_'.$datetime1->format('Y-m-d_H_i_s'); 
            
            $filestore_dir = HEURIST_FILESTORE_ROOT.$database_name.'/';
            $folders_to_copy = folderSubs($filestore_dir, array('backup', 'scratch', 'documentation_and_templates'));
            foreach($folders_to_copy as $idx=>$folder_name){
                $folder_name = realpath($folder_name);
                if($folder_name!==false){
                    $folders_to_copy[$idx] = str_replace('\\', '/', $folder_name);    
                }
                
            }
            
            //$folders_to_copy = self::$system->getSystemFolders( 2, $database_name );
            $folders_to_copy[] = realpath($db_dump_file);
            
            
            if( extension_loaded('bz2') ){
                
                $destination = $destination.'.tar'; 
                
                $archOK = createBz2Archive($source, $folders_to_copy, $destination, $verbose);
                
            }else{
                $destination = $destination.'.zip'; 
                
                $archOK = createZipArchive($source, $folders_to_copy, $destination, $verbose);
            }
            
            if(!$archOK){
                $msg = $msg_prefix.'Cannot create archive with database folder. Failed to archive '
                        .($source).' to '.($destination);
                self::$system->addError(HEURIST_SYSTEM_CONFIG, $msg);                
                if($verbose) echo '<br/>'.htmlspecialchars($msg);
                self::$db_del_in_progress = null;
                return false;
            }
            
        }
            
        if($archOK){

            //get owner info
            $owner_user = user_getDbOwner($mysqli);

            if(true){ //TEMP
                // Delete database from MySQL server
                if(!mysql__drop_database($mysqli, $database_name_full)){

                    $msg = $msg_prefix.' Database error on sql drop operation. '.$mysqli->error;
                    self::$system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
                    if($verbose) echo '<br/>'.htmlspecialchars($msg);
                    return false;
                }

                if($verbose) {
                    echo "<br/>Database ".htmlspecialchars($database_name)." has been dropped";
                }
                
                // Delete $source folder
                folderDelete($source);
                //change current folder
                chdir(HEURIST_FILESTORE_ROOT);
                if($verbose) {
                    echo "<br/>Folder ".htmlspecialchars($source)." has been deleted";   
                }

                //add to log file
                $filename = HEURIST_FILESTORE_ROOT.'DELETED_DATABASES_LOG.csv';
                $fp = fopen($filename, 'a'); //open for add
                if($fp===false){
                    error_log( 'Cannot open file '.$filename );    
                }else{
                    $row = array($database_name,  
                        $owner_user['ugr_LastName'],
                        $owner_user['ugr_FirstName'],
                        $owner_user['ugr_eMail'],
                    date_create('now')->format('Y-m-d H:i:s'));
                    fputcsv($fp, $row); 
                    fclose($fp);
                }
            }

            self::$db_del_in_progress = null;
            return true;
        }
        
            
        self::$db_del_in_progress = null;
        return false;

    }    
    
    
    /**
    * Dump all tables (except csv import cache) into text files
    * It is assumed that all tables exist and empty in target db
    *
    * @param mixed $database_name - database name if not defined - current database
    * @param mixed $verbose
    */
    public static function databaseDump($database_name=null, $database_dumpfile=null, $dump_options=null, $verbose=false ) {
        
        self::initialize();
        
        list($database_name_full, $database_name) = mysql__get_names( $database_name );
        
        $mysqli = self::$mysqli;

        if(defined('HEURIST_DBNAME') && $database_name!=HEURIST_DBNAME){ //switch to database
           $connected = mysql__usedatabase($mysqli, $database_name_full);
        }else{
           $connected = true;
        }
        
        if($connected){
            
            // dump will be created in database upload folder
            if($database_dumpfile==null){
                $directory = HEURIST_FILESTORE_ROOT.$database_name;
                /*if(!folderCreate($directory, true)){
                    self::$system->addError(HEURIST_SYSTEM_CONFIG, 'Cannot create folder for deleteted databases');                
                    if($verbose) echo 'Unable to create folder '.$directory;
                    return false;
                }*/
                
                // Define dump file name
                $database_dumpfile = $directory.'/'.$database_name_full.'_'.time().'.sql';
            }

            if($dump_options==null){
                $dump_options = array(
                        'add-drop-table' => true,
                        'skip-triggers' => false,
                        'single-transaction' => true,
                        'add-drop-trigger' => true,
                        //'databases' => true,
                        'add-drop-database' => true);
            }else{
                //$dump_options = array('skip-triggers' => true,  'add-drop-trigger' => false);
            }
            
            if(defined('HEURIST_DB_MYSQLDUMP')){ // use native mysqldump utility
            
                $tables = array();
                $options = '';
            
                foreach($dump_options as $opt => $val){
                    
                    if($opt=='include-tables'){
                        if(is_array($val) && count($val)>0){
                            $tables = $val;    
                        }
                    }else if($val==true){
                        $options = $options .' --'.$opt;
                    }
                }
                
                if(count($tables)>0){
                    $tables = '--tables '.implode(' ', $tables);
                }else{
                    $tables = '';
                }
            
                //--log-error=mysqldump_error.log -h {$server_name}
                //--hex-blob --routines --skip-lock-tables 
                //-u ".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD."
                $cmd = escapeshellarg(HEURIST_DB_MYSQLDUMP)
                ." --login-path=local {$database_name_full} {$options} {$tables} > " 
                .$database_dumpfile;

//echo $cmd."\n";                
                $arr_out = array();
                $return = null;
                
                exec($cmd, $arr_out, $return);
                
//echo 'return '.$return;                
//echo print_r($arr_out,true)."\n\n";

                if($return !== 0) {
                    self::$system->addError(HEURIST_SYSTEM_CONFIG, $msg);
                    
                    $msg = "mysqldump for ".htmlspecialchars($database_name_full)
                                ." failed with a return code of {$return}";
                    if($verbose) echo '<br>'.$msg;
                    /*
                    echo "Error message was:\n";
                    $file = escapeshellarg("mysqldump_error.log");
                    $message = `tail -n 1 $file`;
                    echo "- $message\n\n";
                    */
                    return false;
                }
                            
            
            }else if(true){
                
                try{
                    $pdo_dsn = 'mysql:host='.HEURIST_DBSERVER_NAME.';dbname='.$database_name_full.';charset=utf8mb4';
                    $dump = new Mysqldump( $pdo_dsn, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, $dump_options);
                            
                    $dump->start($database_dumpfile);
                } catch (Exception $e) {
                    self::$system->addError(HEURIST_SYSTEM_CONFIG, $e->getMessage());
                    return false;
                }            

            }            
            else{//NOT USED
                //create dump manually - all tables without triggers
                $file = fopen($database_dumpfile, "a+");
                if(!$file){
                    $msg = 'Unable to open dump file '.htmlspecialchars($file);
                    self::$system->addError(HEURIST_SYSTEM_CONFIG, $msg);
                    if($verbose) echo '<br>'.$msg;
                    return false;
                }
                
                
                // SQL settings
                $settings = "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n
                /*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n
                /*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n
                /*!40101 SET NAMES utf8mb4 */;\n
                /*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n
                /*!40103 SET TIME_ZONE='+00:00' */;\n
                /*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n
                /*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n
                /*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n
                /*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n";
                fwrite($file, $settings);

                // Dump all tables of the database
                $tables = $mysqli->query("SHOW TABLES");
                if($tables){
                    // Start to dump all tables
                    while ($table = $tables->fetch_row()) {
                        $table = $table[0];

                        // Select everything in the table
                        $result = $mysqli->query('SELECT * FROM '.$table);
                        $num_fields = mysqli_field_count($mysqli);

                        // Drop table sql
                        $output = "\n\nDROP TABLE IF EXISTS `".$table.'`;';

                        // Create table sql
                        $row2 = mysqli_fetch_row($mysqli->query('SHOW CREATE TABLE '.$table));
                        $output.= $row2[1].";\n\n";

                        // Insert values sql
                        $output .= '/*!40000 ALTER TABLE '.$table.' DISABLE KEYS */;';
                        for ($i = 0; $i < $num_fields; $i++) {
                            while($row = $result->fetch_row()) {
                                $output.= 'INSERT INTO '.$table.' VALUES(';
                                for($j=0; $j<$num_fields; $j++) {
                                    $row[$j] = addslashes($row[$j]);
                                    $row[$j] = str_replace("\n","\\n",$row[$j]);

                                    if (isset($row[$j])) {
                                        $output.= '"'.$row[$j].'"' ;
                                    } else {
                                        $output.= '""';
                                    }

                                    if ($j<($num_fields-1)) {
                                        $output.= ',';
                                    }
                                }
                                $output.= ");\n";
                            }
                        }
                        $output .= '/*!40000 ALTER TABLE '.$table.' ENABLE KEYS */;';

                        // Write table sql to file
                        $output.="\n\n\n";
                        fwrite($file, $output);
                    }
                }

                fwrite($file, "SET FOREIGN_KEY_CHECKS=1;\n");
                fwrite($file, "SET sql_mode = 'TRADITIONAL';\n");
                
                // Close file
                fclose($file);
            }
            //$mysqli->close();
            
            chmod($database_dumpfile, 0777);    

            // Echo output
            if($verbose) {
                $size = filesize($database_dumpfile) / pow(1024,2);
                echo "<br/>Successfully dumped "
                    .htmlspecialchars($database_name)." to ".htmlspecialchars($database_dumpfile);
                echo "<br/>Size of SQL dump: ".sprintf("%.2f", $size)." MB";
            }

            return $database_dumpfile;
            
        }else{
            $msg = 'Failed to connect to database '.htmlspecialchars($database_name_full);
            self::$system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
            if($verbose) echo '<br>'.$msg;
            return false;
        }
    }
    
    //
    // Creates new heurist database with file folders and ready to use
    //
    public static function databaseCreateFull($database_name_full, &$user_record, $templateFileName=null){
        
            self::initialize();
            $mysqli = self::$mysqli;
            $system = self::$system;
        
            if($templateFileName==null){
                $templateFileName = HEURIST_DIR."admin/setup/dbcreate/coreDefinitions.txt";
            }
            $templateFoldersContent = 'NOT DEFINED'; //it is used for tempalate database only

            //check template
            if(!file_exists($templateFileName)){
                $system->addError(HEURIST_SYSTEM_CONFIG, 
                        'Template database structure file '.$templateFileName.' not found');
                return false;
            }            

            //create empty database
            if(!DbUtils::databaseCreate($database_name_full)){
                return false;
            }
            
            //switch to new database            
            mysql__usedatabase( $mysqli, $database_name_full);  

            $idef = new ImportDefinitions();
            $idef->initialize( $mysqli );

            if(!$idef->doImport( $templateFileName )) {
                
                $system->addError(HEURIST_SYSTEM_CONFIG, 
                    'Error importing core definitions from coreDefinitions.txt '
                    .' for database '.$database_name_full.'<br>'
                    .'Please check whether this file or database is valid; '.CONTACT_HEURIST_TEAM.' if needed');
                    
                mysql__drop_database( $mysqli, $database_name_full );
                return false;
            }
            
            $warnings = DbUtils::databaseCreateFolders($database_name_full);
            
            if(is_array($warnings) && count($warnings)>0){
                return $warnings;
            }
            
            if(file_exists($templateFoldersContent) && filesize($templateFoldersContent)>0){ //override content of setting folders with template database files - rectype icons, dashboard icons, smarty templates etc
                $upload_root = $system->getFileStoreRootFolder();
                unzipArchive($templateFoldersContent, $upload_root.$database_name.'/');    
            }            
            
            //update owner in new database
            $user_record['ugr_ID'] = 2;
            $user_record['ugr_NavigationTree'] = '"bookmark":{"expanded":true,"key":"root_1","title":"root","children":[{"folder":false,"key":"_1","title":"Recent changes","data":{"url":"?w=bookmark&q=sortby:-m after:\"1 week ago\"&label=Recent changes"}},{"folder":false,"key":"_2","title":"All (date order)","data":{"url":"?w=bookmark&q=sortby:-m&label=All records"}}]},"all":{"expanded":true,"key":"root_2","title":"root","children":[{"folder":false,"key":"_3","title":"Recent changes","data":{"url":"?w=all&q=sortby:-m after:\"1 week ago\"&label=Recent changes"}},{"folder":false,"key":"_4","title":"All (date order)","data":{"url":"?w=all&q=sortby:-m&label=All records"}}]}';
//,{"folder":true,"key":"_5","title":"Rules","children":[{"folder":false,"key":"12","title":"Person > anything they created","data":{"isfaceted":false}},{"folder":false,"key":"13","title":"Organisation > Assoc. places","data":{"isfaceted":false}}]}
            $user_record['ugr_Preferences'] = '';
            
            $ret = mysql__insertupdate($mysqli, 'sysUGrps', 'ugr', $user_record);
            if($ret!=2){
                array_push($warnings, 'Cannot set owner user. '.$ret);                
            }
           
            //add default saved searches and tree
            $navTree = '{"expanded":true,"key":"root_3","title":"root","children":[{"expanded":true,"folder":true,"key":"_1","title":"Save some filters here ...","children":[]}]}';

//{"key":"28","title":"Organisations","data":{"isfaceted":false}},{"key":"29","title":"Persons","data":{"isfaceted":false}},{"key":"30","title":"Media items","data":{"isfaceted":false}}
                        
            $ret = mysql__insertupdate($mysqli, 'sysUGrps', 'ugr', array('ugr_ID'=>1, 'ugr_NavigationTree'=>$navTree ));
            if($ret!=1){
                array_push($warnings, 'Cannot set navigation tree for group 1. '.$ret);                
            }
            
            return true;
    }
    
    //    
    //create new empty heurist database
    // $level - 0 empty db, 1 +strucute, 2 +constraints and triggers
    //
    public static function databaseCreate($database_name, $level=2, $dumpfile=null){

        self::initialize();
        
        list($database_name_full, $database_name) = mysql__get_names( $database_name );
        
        if(strlen($database_name_full)>64){
                self::$system->addError(HEURIST_ACTION_BLOCKED, 
                        'Database name '.$database_name_full.' is too long. Max 64 characters allowed');
                return false;
        }
        $hasInvalid = preg_match('[\W]', $database_name_full);
        if ($hasInvalid) {
                self::$system->addError(HEURIST_ACTION_BLOCKED, 
                        'Database name '.$database_name_full
                        .' is invalid. Only letters, numbers and underscores (_) are allowed in the database name');
                return false;
        }
        
        if($dumpfile==null){
            $dumpfile = HEURIST_DIR."admin/setup/dbcreate/blankDBStructure.sql";
        }
        
        $mysqli = self::$mysqli;
        
        $res = mysql__create_database($mysqli, $database_name_full);
        
        if (is_array($res)){
            self::$system->addError($res[0], $res[1]); //can't create
        }else
        if($level<1 || execute_db_script(self::$system, $database_name_full, $dumpfile, 'Cannot create database tables')){

            // echo_flush ('OK');
            // echo_flush ("<p>Add Referential Constraints ");
            if($level<2){
                return true;
            }else if(self::databaseCreateConstraintsAndTriggers($database_name)){
                return true;    
            }
        }
        
        //fail
        mysql__drop_database($mysqli, $database_name_full);
        return false;
    }

    //
    //
    //    
    public static function databaseCreateConstraintsAndTriggers($database_name){

        self::initialize();
        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        if(execute_db_script(self::$system, $database_name_full, 
                HEURIST_DIR."admin/setup/dbcreate/addReferentialConstraints.sql",
                'Cannot add referential constraints')){

                if(execute_db_script(self::$system, $database_name_full, 
                    HEURIST_DIR."admin/setup/dbcreate/addProceduresTriggers.sql",
                    'Cannot create procedures and triggers')){

                    // echo_flush ('OK');
                    return true;
                }
        }
        return false;
        
    }
        
    //
    // create if not exists set of folders for given database
    //
    public static function databaseCreateFolders($database_name){

        list($database_name_full, $database_name) = mysql__get_names( $database_name );
        
        $upload_root = self::$system->getFileStoreRootFolder();
        
        // Create a default upload directory for uploaded files eg multimedia, images etc.
        $database_folder = $upload_root.$database_name.'/';

        if (folderCreate($database_folder, true)){
            folderAddIndexHTML( $database_folder ); //add index file to block directory browsing
        }else{
            return array('message'=>"Heurist was unable to create the required database root folder,\nDatabase name: " . $database_name . "\nServer url: " . HEURIST_BASE_URL, 'revert'=>true);
        }

        $warnings = array();


        if(folderRecurseCopy( HEURIST_DIR."admin/setup/dbcreate/icons", $database_folder."entity" )){
            
            folderAddIndexHTML($database_folder."entity"); // index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy entity folder (icons) to $database_folder";
        }
        
        

        if(folderRecurseCopy( HEURIST_DIR."admin/setup/dbcreate/smarty-templates", $database_folder."smarty-templates" )){
            
            folderAddIndexHTML($database_folder."smarty-templates"); // index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy smarty-templates folder to $database_folder";
        }
/* removed 2021-07-15
        if(folderRecurseCopy( HEURIST_DIR."admin/setup/xsl-templates", $database_folder."xsl-templates" )){
            
            folderAddIndexHTML($database_folder."xsl-templates"); // index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy xsl-templates folder to $database_folder";
        }
*/
    if(false){ //since 2023-06-02 this folder is not created
        if(folderRecurseCopy( HEURIST_DIR."documentation_and_templates", $database_folder."documentation_and_templates" )){
            
            folderAddIndexHTML($database_folder."documentation_and_templates"); // index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy documentation folder to $database_folder";
        }
    }

        // Create all the other standard folders required for the database
        // index.html files are added by createFolder to block index browsing
        $warnings[] = folderCreate2($database_folder. '/filethumbs', 'used to store thumbnails for uploaded files', true);
        $warnings[] = folderCreate2($database_folder. '/file_uploads','used to store uploaded files by default');
        $warnings[] = folderCreate2($database_folder. '/scratch', 'used to store temporary files');
        $warnings[] = folderCreate2($database_folder. '/hml-output', 'used to write published records as hml files', true);
        $warnings[] = folderCreate2($database_folder. '/html-output', 'used to write published records as generic html files', true);
        $warnings[] = folderCreate2($database_folder. '/generated-reports', 'used to write generated reports');
        $warnings[] = folderCreate2($database_folder. '/backup', 'used to write files for user data dump');
        
        //remove empty warns
        $warnings = array_filter($warnings, function($value) { return $value !== ''; });

        return $warnings;
    }
    
    
    //
    //
    //
    private static function empty_table($name, $remark, $verbose){

        $mysqli = self::$mysqli;
        
        if($verbose) echo ("Deleting ".htmlspecialchars($remark)."</br>");

        if(!$mysqli->query("delete from $name where 1")){
            if($verbose) {
                echo ("<br/><p>Warning: Unable to clean ".htmlspecialchars($remark)
                    ." - SQL error: ".$mysqli->error."</p>");
            }
            return false;
        }else{
            //if($verbose) { echo ("<p>OK</p>"); }
            return true;
        }
    }

    

    //
    //
    //
    public static function databaseEmpty($database_name, $verbose=true){

        list($database_name_full, $database_name) = mysql__get_names( $database_name );
        
        $res = true;
        
        self::initialize();
        $mysqli = self::$mysqli;
        $system  = self::$system;
        
        if($database_name!=HEURIST_DBNAME){ //switch to database
           $connected = mysql__usedatabase($mysqli, $database_name_full);
        }else{
           $connected = true;
        }
        if(!$connected){
            $msg = 'Failed to connect to database '.htmlspecialchars($database_name);
            $system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
            if($verbose) echo '<br><p>'.$msg.'</p>';
            return false;
        }
        
        $mysqli->autocommit(FALSE);

        if(!$mysqli->query("update recThreadedComments set cmt_ParentCmtID = NULL where cmt_ID>0")){
            $res = false;
            if($verbose) {
                echo ("<br/><p>Warning: Unable to set parent IDs to null for Comments".
                    " - SQL error: ".$mysqli->error."</p>");
            }
        }
        
        if($res){

            $tables = array(
                "recThreadedComments" => "Comments",
                "recForwarding" => "Forwarding",
                "recLinks" => "Relationships Cache",
                "recSimilarButNotDupes" => "List of Similar Records",
                "usrRecTagLinks" => "Tag Links",
                "usrReminders" => "Reminders",
                "usrRemindersBlockList" => "Reminders Block List",
                "usrRecPermissions" => "Permissions",
                "recDetails" => "Details",
                "usrBookmarks" => "Bookmarks",
                "Records" => "Records"
            );

            foreach ($tables as $name => $remark) {
                if(! self::empty_table($name, $remark, $verbose)){
                    $res = false;
                    break;
                }
            }

            if($res){
                $res = $mysqli->query("ALTER TABLE Records AUTO_INCREMENT = 0");
                if($res) $mysqli->commit();
            }
        }
    
        if(!$res){
            $mysqli->rollback();
        }

        $mysqli->close();

        return $res;
    }

    /**
    * Copy all tables (except csv import cache) from one db to another
    * It is assumed that all tables exist and empty in target db
    *
    * @param mixed $db_source
    * @param mixed $db_target - must exist as new empty heurist database created by  databaseCreate($targetdbname_full, 1)
    * @param mixed $verbose
    */
    public static function databaseClone($db_source, $db_target, $verbose, $nodata=false, $isCloneTemplate){

        self::initialize();
        
        $res = true;
        $mysqli = self::$mysqli;
        $message = null;
        
        if( !mysql__usedatabase($mysqli, $db_source) ){
            $message = 'Could not open source database '.$db_source;
            $res = false;
            if($verbose) {
                $message = '<br/><p>Warning: '.$message.'</p>';
            }
        }else{
            
            if( !mysql__usedatabase($mysqli, $db_target) ){
                $message = 'Could not open target database '.$db_target;
                $res = false;
                if($verbose) {
                    $message = '<br/><p>Warning: '.$message.'</p>';
                }
            }
        }   

        if($res){
            
                // Remove initial values from empty target database
                $mysqli->query('delete from sysIdentification where 1');
                $mysqli->query('delete from defLanguages where 1');
                
                if(!$isCloneTemplate){
                    $mysqli->query('delete from sysUsrGrpLinks where 1');
                    $mysqli->query('delete from sysUGrps where ugr_ID>=0');
                }            
                
                //$isCloneTemplate
                $exception_for_clone_template = array('sysugrps','sysusrgrplinks',
                'woot_chunkpermissions','woot_chunks','woot_recpermissions','woots',
                'usrreminders','usrremindersblocklist','recthreadedcomments','usrreportschedule','usrhyperlinkfilters', 'sysarchive');
                
                $data_tables = array('records','recdetails','reclinks',
                'recsimilarbutnotdupes','recthreadedcomments','recuploadedfiles','usrbookmarks','usrrectaglinks',
                'usrreminders','usrremindersblocklist','woot_chunkpermissions','woot_chunks','woot_recpermissions','woots', 'sysarchive');
          
                
                $tables = $mysqli->query("SHOW TABLES");  //get all tables from target db
                if($tables){

                    $mysqli->query("SET foreign_key_checks = 0"); //disable
                    $mysqli->query("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

                    if($verbose) {
                        echo ("<b>Adding records to tables: </b>");
                    }
                    while ($table = $tables->fetch_row()) { //loop for all tables
                        $table = $table[0];
                        
                        if($nodata && in_array(strtolower($table), $data_tables)){
                            continue;
                        }
                        if($isCloneTemplate &&  in_array(strtolower($table), $exception_for_clone_template)){
                            continue;
                        }
                        
                        if(strtolower($table)=='usrrecpermissions'){
                            $cnt = mysql__select_value($mysqli,'select count(*) from usrRecPermissions');
                            if(!($cnt>0)) continue;
                        }else if($table=='sysUGrps'){
                            $cnt = mysql__select_value($mysqli, "SELECT count(*) FROM ". $db_source .".sysUGrps WHERE ugr_Enabled != 'n' AND ugr_Enabled != 'y'");

                            if(is_numeric($cnt) && $cnt > 0){
                                checkUserStatusColumn(self::$system, $db_target);
                            }
                        }
                        
                        $mysqli->query("ALTER TABLE `".$table."` DISABLE KEYS");
                        $res = $mysqli->query("INSERT INTO `".$table."` SELECT * FROM ".$db_source.".`".$table."`"  );

                        if($res){
                                if($verbose) {
                                    echo (" > " . htmlspecialchars($table) . ": ".intval($mysqli->affected_rows) . "  ");
                                }
                        }else{
                                if($table=='usrReportSchedule'){
                                    if($verbose) {
                                        echo ("<br/><p class=\"error\">Warning: Unable to add records into ".htmlspecialchars($table)." - SQL error: ".$mysqli->error."</p>");
                                    }
                                }else{
                                    $message = "Unable to add records into ".$table." - SQL error: ".$mysqli->error;
                                    if($verbose) {
                                        $message = "<br/><p class=\"error\">Error: $message</p>";
                                    }
                                    $res = false;
                                    break;
                                }
                        }

                        if($table=='recForwarding'){ //remove missed records otherwise we get exception on constraint addition
                            $mysqli->query('DELETE FROM recForwarding where rfw_NewRecID not  in (select rec_ID from Records)');
                        }

                        $mysqli->query("ALTER TABLE `".$table."` ENABLE KEYS");
                    }//while

                    if($isCloneTemplate){
                        //change ownership OR remove entries for all users and groups but 0~3
                        $mysqli->query('delete FROM usrRecTagLinks,usrTags WHERE rtl_TagID=tag_ID AND tag_UGrpID NOT IN (0,1,2,3)');
                        $mysqli->query('delete FROM usrTags WHERE tag_UGrpID NOT IN (0,1,2,3)');

                        $mysqli->query('delete FROM usrBookmarks WHERE bkm_UGrpID NOT IN (0,1,2,3)');
                        $mysqli->query('delete FROM usrSavedSearches WHERE svs_UGrpID NOT IN (0,1,2,3)');
                        
                        $mysqli->query('update Records set rec_AddedByUGrpID=2 WHERE rec_AddedByUGrpID NOT IN (0,1,2,3)');
                        $mysqli->query('update Records set rec_OwnerUGrpID=2 WHERE rec_OwnerUGrpID NOT IN (0,1,2,3)');
                        $mysqli->query('update recUploadedFiles set ulf_UploaderUGrpID=2 WHERE ulf_UploaderUGrpID NOT IN (0,1,2,3)');
                    }
                    
                    
                    $mysqli->query("SET foreign_key_checks = 1"); //restore/enable foreign indexes verification

                    //cleanup target database to avoid issues with addition of constraints

                    //1. cleanup missed trm_InverseTermId
                    $mysqli->query('update defTerms t1 left join defTerms t2 on t1.trm_InverseTermId=t2.trm_ID
                        set t1.trm_InverseTermId=null
                    where t1.trm_ID>0 and t2.trm_ID is NULL');
                    
                    //3. remove missed rl_SourceID and rl_TargetID
                    $mysqli->query('delete FROM recLinks
                        where rl_SourceID is not null
                    and rl_SourceID not in (select rec_ID from Records)');

                    $mysqli->query('delete FROM recLinks
                        where rl_TargetID is not null
                    and rl_TargetID not in (select rec_ID from Records)');
                    
                    //4. cleanup orphaned details
                    $mysqli->query('delete FROM recDetails
                        where dtl_RecID is not null
                    and dtl_RecID not in (select rec_ID from Records)');
                    
                    //5. cleanup missed references to uploaded files
                    $mysqli->query('delete FROM recDetails
                        where dtl_UploadedFileID is not null
                    and dtl_UploadedFileID not in (select ulf_ID from recUploadedFiles)');

                    //6. cleanup missed rec tags links
                    $mysqli->query('delete FROM usrRecTagLinks where rtl_TagID not in (select tag_ID from usrTags)');
                    $mysqli->query('delete FROM usrRecTagLinks where rtl_RecID not in (select rec_ID from Records)');

                    //7. cleanup orphaned bookmarks
                    $mysqli->query('delete FROM usrBookmarks where bkm_RecID not in (select rec_ID from Records)');
                    
                }else{
                    $res = false;
                    $message = 'Cannot get list of table in database '.$db_target;
                    if($verbose) {
                        echo ("<br/><p class=\"error\">Error: $message</p>");
                    }
                }


                //$mysqli->autocommit(FALSE);
                //if($res) $mysqli->commit();
            }

        if(!$res){
            if($verbose) {
                if($message) echo htmlspecialchars($message);
            }else{
                self::$system->addError(HEURIST_ERROR, $message);
            }
        }

        return $res;
    }
    
}


?>