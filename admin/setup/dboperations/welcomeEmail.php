<?php
/**
* welcomeEmail.php: mails for new and cloned database
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
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
require_once (dirname(__FILE__).'/../../../vendor/autoload.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail_NewDatabase($user_record, $database_name, $source_database){
    
    $fullName = $user_record['ugr_FirstName'].' '.$user_record['ugr_LastName'];
    
    // email the system administrator to tell them a new database has been created
    $email_text =
    "There is new Heurist database.\n".
    "Database name: ".$database_name."\n\n".

    'The user who created the new database is:'.$user_record['ugr_Name']."\n".
    "Full name:    ".$fullName."\n".
    "Email address: ".$user_record['ugr_eMail']."\n".
    "Organisation:  ".$user_record['ugr_Organisation']."\n".
    "Research interests:  ".$user_record['ugr_Interests']."\n".
    "Go to the address below to review further details:\n".
    HEURIST_BASE_URL."?db=".$database_name;

    $email_title = (($source_database!=null)?'CloneDB: ':'NewDB: ')
        .$database_name.' by '.$fullName.' ['.$user_record['ugr_eMail'].']'
        .(($source_database!=null) ?$source_database:'');

    //$rv = sendEmail(HEURIST_MAIL_TO_ADMIN, $email_title, $email_text, null); 

    //send an email with attachment
    $message = file_get_contents(dirname(__FILE__).'/welcomeEmail.html');
    
    $message =  substr($message,strpos($message,'<body>')+6);
    $message =  substr($message,0,strpos($message,'</body>')-1);
    $message =  str_replace('##Email##',$user_record['ugr_eMail'], $message);
    $message =  str_replace('##GivenNames##',$user_record['ugr_FirstName'],$message);
    $message =  str_replace('##FamilyName##',$user_record['ugr_LastName'],$message);
    $message =  str_replace('##DBURL##',''.HEURIST_BASE_URL."?db=".$database_name.'',$message); //<pre>
    $message =  str_replace('##Organisation##',$user_record['ugr_Organisation'],$message);
    $message =  str_replace('##Interests##',$user_record['ugr_Interests'],$message);
    
    if(strtolower(substr($user_record['ugr_eMail'], -3)) === '.fr'){
        $message =  str_replace('&lt;FrenchNoteIfFrance&gt;',
        '<p>Je m\'excuse du fait que ce mel soit en anglais. Vous pouvez cependant me r&eacute;pondre en fran&ccedil;ais.</p>'
        ,$message);
    }else{
        $message =  str_replace('&lt;FrenchNoteIfFrance&gt;','',$message);
    }
    
    $email = new PHPMailer();
    $email->CharSet = 'UTF-8';
    $email->Encoding = 'base64';
    $email->isHTML(true); 
    $email->SetFrom('no-reply@HeuristNetwork.org', 'Heurist'); //'no-reply@'.HEURIST_SERVER_NAME
    $email->Subject   = (($source_database!=null)?'CloneDB: ':'NewDB: ')
                    .'Getting up to speed with your Heurist database ('.$database_name.') on '.HEURIST_SERVER_NAME;
    $email->Body      = $message;
    $email->AddAddress( $user_record['ugr_eMail'] );
    $email->AddAddress( HEURIST_MAIL_TO_ADMIN ); // 
    $email->addAttachment(dirname(__FILE__).'/Heurist Welcome attachment.pdf');
   
    try{
        $email->send();
        return array(1); //fake rec id
    } catch (Exception $e) {
        $this->system->addError(HEURIST_SYSTEM_CONFIG, 
                'Cannot send email. Please ask system administrator to verify that mailing is enabled on your server'
                , $email->ErrorInfo);
        return false;
    }
    
}
?>
