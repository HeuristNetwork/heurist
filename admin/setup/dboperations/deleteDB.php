<?php                                                
/**
* deleteDB.php: delete MULTIPLE databases. Called by dbStatistics.php (for system admin only)
*               note that deletion of current database is handled separately by deleteCurrentDB.php which calls dbUtils.php
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once(dirname(__FILE__).'/../../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../../hsapi/utilities/dbUtils.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

set_time_limit(0);

$res = false;

$system = new System();

$database_to_delete = @$_REQUEST['database'];
$create_arc = ( (@$_REQUEST['create_archive']===true)
        || (@$_REQUEST['create_archive']==='true') 
        || (@$_REQUEST['create_archive']==1));

if(!@$_REQUEST['pwd']){
    $system->addError(HEURIST_INVALID_REQUEST, 'Password parameter is not defined');
}else{

//if user deletes its own database
    $is_delete_current_db = (@$_REQUEST['db']==$database_to_delete && @$_REQUEST['pwd']=='DELETE MY DATABASE');

// Password check for system administrator who can delete any database
    if($is_delete_current_db || !$system->verifyActionPassword(@$_REQUEST['pwd'], $passwordForDatabaseDeletion, 14)) 
    {
        if($database_to_delete){
            
            //if database to be deleted is not current - only system admin can do it
            $isSystemInited = $system->init(@$_REQUEST['db']); //need to verify credentials for current database

            /** Db check */
            if($isSystemInited){

//if(!$database_to_delete){
//    $system->addError(HEURIST_INVALID_REQUEST, 'Database parameter is not defined');
//}

                    $allow_deletion = true;        
                

                    list($dbname_full, $dbname ) = mysql__get_names( $database_to_delete );

                    if($is_delete_current_db){
                
                        $user = user_getById($system->get_mysqli(), $system->get_user_id()); //user in current db
                            
                        $allow_deletion = false;
                        //find the same user in database to be deleted
                        //find user by email
                        $usr = user_getByField($system->get_mysqli(), 'ugr_eMail', $user['ugr_eMail'], $dbname_full);
                        if(@$usr['ugr_ID']==2){ //database owner
                            $allow_deletion = true;
                        }else{
                            //allowed if user is database admnistrator
                            $groups = user_getWorkgroups($system->get_mysqli(), $usr['ugr_ID'], false, $dbname_full);
                            $allow_deletion = (@$groups[1]=='admin');
                        }
                    }
                    
    /* before 2020-12-21 only system administrator or db            
                if (defined('HEURIST_MAIL_TO_ADMIN') && (@$user['ugr_eMail']==HEURIST_MAIL_TO_ADMIN)){ //system admin
                    
                    $allow_deletion = true;
                }else{
                    list($dbname_full, $dbname ) = mysql__get_names( $_REQUEST['database'] );
                    //find user by email
                    $usr = user_getByField($system->get_mysqli(), 'ugr_eMail', $user['ugr_eMail'], $dbname_full);
                    if(@$usr['ugr_ID']==2){ //database owner
                        $allow_deletion = true;   
                    }
                }
    */
                    if($allow_deletion)
                    {
                        //find owner of database
                        $usr_owner = user_getByField($system->get_mysqli(), 'ugr_ID', 2, $dbname_full);
                
                        $res = DbUtils::databaseDrop(false, $database_to_delete, $create_arc);    
                        
                        // in case deletion by sysadmin - send email to onwer of deleted database
                        if(!$is_delete_current_db)
                        { 
                    $server_name = HEURIST_SERVER_NAME;
                    $email_title = 'Your Heurist database '.$dbname.' has been archived';
                    $email_text = <<<EOD
Dear {$usr_owner['ugr_FirstName']} {$usr_owner['ugr_LastName']},                    
                    
Your Heurist database {$dbname} has been archived. We would like to help you get (re)started.

In order to conserve server space we have archived your database on {$server_name} since it has not been used for several months and/or no data has ever been created. 
 
If you got as far as creating a database but did not know how to proceed you are not alone, but those who persevere, even a little, will soon find the system easy to use and surprisingly powerful. We invite you to get in touch ( support@HeuristNetwork.org ) so that we can help you over that (small) initial hump and help you see how it fits with your research (or other use). 

With a brand new interface in 2021, developed in collaboration with an experienced UX designer, Heurist is significantly easier to use than previous versions. The new interface (version 6) remains compatible with databases created more than 10 years ago - we believe in sustainability.
 
Please contact us if you need your database re-enabled or visit one of our free servers to create a new database (heuristplus.Sydney.edu.au/heurist/startup worldwide or heurist.Huma-Num.fr/heurist/startup for European users).
 
Heurist is research-led and responds rapidly to evolving user needs - we often turn around small user suggestions (and most bug-fixes) in a day and larger ones within a couple of weeks. We are still forging ahead with new capabilities such as enhancements to the integrated CMS website builder, new output formats, improved data exchange through XML, JSON, remote resource lookups and linked open data, and improved search, mapping and visualisation widgets (embeddable in websites). 
 
For more information email us at support@HeuristNetwork.org and visit our website at HeuristNetwork.org. We normally respond within hours, depending on time zones. We are actively developing new documentation and training resources for version 6 and can make advance copies available on request.                    
EOD;
                            //sendEmail($usr_owner['ugr_eMail'], $email_title, $email_text, null);
                            
                            $email = new PHPMailer();
                            //$email->isHTML(true); 
                            $email->SetFrom('info@HeuristNetwork.org', 'Heurist');
                            $email->Subject   = $email_title;
                            $email->Body      = $email_text;
                            $email->AddAddress( $usr_owner['ugr_eMail'] );         
                            $email->AddAddress( HEURIST_MAIL_TO_ADMIN );        

                            try{
                                $email->send();
                            } catch (Exception $e) {
                                $this->system->addError(HEURIST_SYSTEM_CONFIG, 
                                        'Cannot send email. Please ask system administrator to verify that mailing is enabled on your server'
                                        , $email->ErrorInfo);
                            }                    
                            
                            
                        }
                        
                    }else{
                        $system->addError(HEURIST_REQUEST_DENIED, 
                            'You must be a database administrator or owner to delete database '.$database_to_delete,1);
                    }

            }
        }else{
            //database not defined - this is just authorization check
            $res = true; //authentification passed
        }
    }
    else{
        $system->addError(HEURIST_REQUEST_DENIED, 'Wrong password');
    }

}

if(is_bool($res) && !$res){
    $response = $system->getError();
}else{
    $response = array("status"=>HEURIST_OK, "data"=> $res);
}
    
header('Content-type: text/javascript');
print json_encode($response);
?>
