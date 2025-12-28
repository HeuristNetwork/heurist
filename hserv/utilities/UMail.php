<?php
/**
* UMail.php - Email sending utilities
*
* This file provides a collection of global functions for sending emails.
* It primarily utilizes the PHPMailer library for robust email functionality,
* including HTML emails and attachments. It also includes a fallback to PHP's native
* mail() function and helper functions for SMTP checks and rate-limited admin notifications.
*
* Key functions include:
* - sendEmail: General purpose email sending function (wraps sendPHPMailer).
* - sendPHPMailer: Core email sending logic using PHPMailer.
* - sendEmail_native: Sends email using PHP's native mail() function.
* - checkSmtp: Checks basic SMTP connectivity.
* - sendEmailToAdmin: Sends a rate-limited notification email to the admin.
* - endsWith: A string utility function.
*
* @project     Heurist academic knowledge management system
* @package Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sends an email using PHPMailer. This is a general-purpose wrapper.
 *
 * @param string|array $email_to Recipient email address or an array of addresses.
 *                               Can also be an associative array with 'to', 'cc', 'bcc' keys, each being an array of emails.
 * @param string $email_title The subject of the email.
 * @param string $email_text The body of the email. Can be plain text or HTML if $is_html is true.
 * @param bool $is_html Optional. Whether the email body is HTML. Defaults to false.
 * @param string|array|null $email_attachment Optional. Path to a file to attach, or an array of paths. Defaults to null.
 * @return bool True on success, false on failure.
 */
function sendEmail($email_to, $email_title, $email_text, $is_html=false, $email_attachment=null)
{
    return sendPHPMailer(null, null, $email_to, $email_title, $email_text, $email_attachment, $is_html);
}

/**
 * Checks if a string ends with a specific substring.
 * Polyfill for `str_ends_with()` available in PHP 8+.
 *
 * @param string $haystack The string to search in.
 * @param string $needle The substring to search for at the end of $haystack.
 * @return bool True if $haystack ends with $needle, false otherwise.
 */
function endsWith($haystack, $needle) {
    // search forward starting from end minus needle length characters
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
}

/**
 * Sends an email using the PHPMailer library with specified sender details.
 * Handles various configurations like SMTP relay for Gmail, HTML content, and attachments.
 *
 * @global \hserv\System|null $system The global Heurist system object, used for error reporting.
 * @global string|null $mailRelayPwd Password for a mail relay service (used for Gmail relay).
 *
 * @param string|null $email_from Sender's email address. Defaults to 'no-reply@[HEURIST_MAIL_DOMAIN|HEURIST_DOMAIN]'.
 * @param string|null $email_from_name Sender's name. Defaults to 'Heurist system. ([HEURIST_SERVER_NAME])'.
 * @param string|array $email_to Recipient email address or an array of addresses.
 *                               Can also be an associative array with 'to', 'cc', 'bcc' keys, each being an array of emails.
 * @param string $email_title The subject of the email.
 * @param string $email_text The body of the email. If $is_html is true, HTML will be purified.
 * @param string|array|null $email_attachment Optional. Path to a file to attach, or an array of paths. Defaults to null.
 * @param bool $is_html Whether the email body is HTML.
 * @return bool True on success, false on failure.
 */
function sendPHPMailer($email_from, $email_from_name, $email_to, $email_title, $email_text, $email_attachment, $is_html){

    global $system, $mailRelayPwd;

    $replyTo = null;//$email_from;
    $replyToName = null;//$email_from_name;

    if(!$email_from) {$email_from = 'no-reply@'.(defined('HEURIST_MAIL_DOMAIN')?HEURIST_MAIL_DOMAIN:HEURIST_DOMAIN);}
    if(!$email_from_name) {$email_from_name = 'Heurist system. ('.HEURIST_SERVER_NAME.')';}

    if($is_html){
        USanitize::purifyHTML($email_text);
    }

    if(is_array($email_text)){
        $email_text =  json_encode($email_text);
    }
    
    if(!$email_to){
        if(isset($system)){
            $system->addError(HEURIST_ACTION_BLOCKED,
                'Cannot send email. Recipient email address is not defined');
        }
        return false;
    }elseif(!is_array($email_to)){
        $email_to = array($email_to);
    }

    //$is_html = (strpos("\n",$email_text)===false);

    // strip all whitespaces
    $email_from = filter_var($email_from, FILTER_SANITIZE_EMAIL);

    //using heuristref.net as mail relay for gmail recipients
    if(isset($mailRelayPwd) && $mailRelayPwd!=''
    && count($email_to)==1 && endsWith($email_to[0], '@gmail.com')){

        $data = array('pwd' => $mailRelayPwd ,
            'from_name' => $replyToName,
            'from' => $replyTo,
            'to' => implode(',',$email_to),  //cs list of recipients
            'title' => $email_title,
            'text' => $email_text,
            'html' => 1);

        $data_str = http_build_query($data);

        $ch =  curl_init("https://heuristref.net/HEURIST/mailRelay.php");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_str);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responce = curl_exec($ch);
        curl_close($ch);

        return $responce==1;
    }


    //send an email with attachment
    $email = new PHPMailer();

    /*
    $mail->IsSMTP();
    $mail->SMTPAuth   = true;
    $mail->Port       = 25;
    $mail->Host       = "xx.xxx.x.x";// SMTP server
    $mail->Username   = "myemail@mydomain.local";
    $mail->Password   = <myemailpassword>;

    $mail->From = 'contacto@45norte.com';
    $mail->addReplyTo($_POST['inputEmail'], $_POST['inputName']);//recipient
    */

    $email->CharSet = 'UTF-8';
    $email->Encoding = 'base64';
    $email->isHTML( $is_html );

    if($replyTo!=null && $replyTo!=$email_from){
        $email->ClearReplyTos();
        $email->addReplyTo($replyTo, $replyToName);
    }
    $email->SetFrom($email_from, $email_from_name);


    $email->Subject   = $email_title; //'=?UTF-8?B?'.base64_encode($email_title).'?=';
    $email->Body      = $email_text;

    $email_cc = array_key_exists('cc', $email_to) ? $email_to['cc'] : [];
    $email_bcc = array_key_exists('bcc', $email_to) ? $email_to['bcc'] : [];
    $email_to = array_key_exists('to', $email_to) ? $email_to['to'] : $email_to;

    foreach($email_to as $email_address){

        $email_address = filter_var($email_address, FILTER_SANITIZE_EMAIL);
        if(!filter_var($email_address, FILTER_VALIDATE_EMAIL)){

            $problem = (($email_address==null) || (trim($email_address)==='')) ? "is not defined" : "$email_address is invalid";

            if(isset($system)){
                $system->addError(HEURIST_ACTION_BLOCKED,
                    "Cannot send email. Recipient email address $problem.");
            }
            return false;
        }

        $email->AddAddress( $email_address );
    }
    foreach($email_cc as $email_address){

        $email_address = filter_var($email_address, FILTER_SANITIZE_EMAIL);
        if(!filter_var($email_address, FILTER_VALIDATE_EMAIL)){
            continue;
        }

        $email->AddCC($email_address);
    }
    foreach($email_bcc as $email_address){

        $email_address = filter_var($email_address, FILTER_SANITIZE_EMAIL);
        if(!filter_var($email_address, FILTER_VALIDATE_EMAIL)){
            continue;
        }

        $email->AddBCC($email_address);
    }

    if($email_attachment!=null){
        if(is_array($email_attachment)){
            foreach($email_attachment as $attach_file){
                $email->addAttachment( $attach_file );
            }
        }else{
            $email->addAttachment($email_attachment);// , 'new.jpg');
        }
    }

    try{
        $email->send();
        return true;
    } catch (Exception $e) {
        if(isset($system)){
            $system->addError(HEURIST_SYSTEM_CONFIG,
                'Cannot send email. Please ask system administrator to verify that mailing is enabled on your server'
                , $email->ErrorInfo);
        }
        return false;
    }
    return true;
}

/**
 * Sends an email using PHP's native mail() function.
 * Used primarily by sendBulkEmail.php as a fallback or simpler alternative.
 *
 * @param string $email_to Recipient's email address.
 * @param string $email_title Subject of the email. Prefixed with "HEURIST ".
 * @param string $email_text Body of the email. A standard footer is appended.
 * @param string|null $email_header Optional. Custom email headers. If null, default "From" header is constructed.
 * @param bool $is_utf8 Optional. If true, sets Content-Type to UTF-8 and base64 encodes the title. Defaults to false.
 * @param bool $use_html Optional. If true, sets Content-Type to HTML. Defaults to false.
 * @return string "ok" on success, or an error message string on failure.
 */
function sendEmail_native($email_to, $email_title, $email_text, $email_header, $is_utf8=false, $use_html=false){
    
    global $system;

    $res = "ok";

    $email_to = filter_var($email_to, FILTER_SANITIZE_EMAIL);

    if(!filter_var($email_to, FILTER_VALIDATE_EMAIL)){

        $problem = (($email_to==null) || (trim($email_to)==='')) ? "is not defined" : "$email_to is invalid";
        $res = "Mail send failed. Recipient email address $problem.";
    }elseif(!$email_text){
        $res = "Mail send failed. Message text is not defined.";
    }else {

        if(!@$email_title){
            $email_title = "";
        }
        $errorMsg = "Cannot send email "
        .($email_title?"'".$email_title."'":'')
        .". This may indicate that mail transport agent is not correctly configured on server."
        ." Please ask your system administrator to correct the installation";

        if(!checkSmtp()){
            return $errorMsg;
        }

        $email_title = "HEURIST ".$email_title;

        if(!$email_header){
            $email_header = "From: HEURIST";
            if(defined('HEURIST_DOMAIN')){
                if(isset($system)){
                    $email_header = $email_header." (".$system->dbname().")";
                }
                $email_header = $email_header." <no-reply@".(defined('HEURIST_MAIL_DOMAIN')?HEURIST_MAIL_DOMAIN:HEURIST_DOMAIN).">";
            }
        }

        if($use_html){

            $email_header = $email_header."\r\nContent-Type: text/html;";
            if(!$is_utf8){
                $email_header = $email_header."\r\n";
            }else{
                $email_header = $email_header." charset=utf-8\r\n";
                $email_title = '=?utf-8?B?'.base64_encode($email_title).'?=';
            }
        }elseif($is_utf8){
            $email_header = $email_header."\r\nContent-Type: text/plain; charset=utf-8\r\n";
            $email_title = '=?utf-8?B?'.base64_encode($email_title).'?=';
        }

        $email_text = $email_text."\n\n"
        ."-------------------------------------------\n"
        ."This email was generated by Heurist (info@HeuristNetwork.org)\n";
        // This tends to confuse people who click on the link and get a list of databases
        // .(defined('HEURIST_BASE_URL') ?(":\n".HEURIST_BASE_URL) :"")."\n";


        $rv = mail($email_to, $email_title, $email_text, $email_header);
        if(!$rv){
            $res = $errorMsg;
        }
    }

    return $res;
}

/**
 * Checks basic SMTP connectivity by attempting to open a socket connection to localhost on port 25.
 *
 * @return bool True if the SMTP port can be opened, false otherwise.
 */
function checkSmtp(){

    $smtpHost = '127.0.0.1';//'localhost';
    $smtpPort = '25';
    $smtpTimeout = 5;

    $res = @fsockopen($smtpHost,
        $smtpPort,
        $errno,
        $errstr,
        $smtpTimeout);

    if (!is_resource($res))
    {
        USanitize::errorLog("email_smtp_error {$errno} {$errstr}");
        return false;
    }
    return true;
}


    /**
     * Sends a warning email to the administrator, rate-limited to once per 4 hours for a given scope.
     * The rate limiting is managed by checking a timestamp in a "lastWarningSent.ini" file.
     *
     * @global \hserv\System|null $system The global Heurist system object. Used to determine file paths if available.
     * @param string $title The subject/title of the warning email.
     * @param string $message The body of the warning email.
     * @param bool $is_global If true, the rate-limiting timestamp file is checked/stored in the global HEURIST_FILESTORE_ROOT.
     *                        If false, it's checked/stored within the current database's folder (if $system is available).
     * @return void
     */
function sendEmailToAdmin($title, $message, $is_global){
    global $system;

    if(isset($system)){
        $folder = $system->getFileStoreRootFolder();
        if(!$is_global){
            $folder .= $system->dbname().'/';
        }
    }else{
        $folder = dirname(__FILE__).'/../../../'; //$defaultRootFileUploadPath;
    }

    $fname = $folder."lastWarningSent.ini";

    $needSend = true;
    if (file_exists($fname)){//check if warning is already sent
        $datetime1 = date_create(file_get_contents($fname));
        $datetime2 = date_create('now');
        $interval = date_diff($datetime1, $datetime2);
        $needSend = ($interval->format('%h')>4);//in hours
    }
    if($needSend){

        $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, $title, $message);
        if($rv){
            if (file_exists($fname)) {unlink($fname);}
            file_put_contents($fname, date_create('now')->format(DATE_8601));
        }
    }



}
