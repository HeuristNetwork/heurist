<?php
require_once (dirname(__FILE__).'/../../vendor/autoload.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

    /**
    * utils_mail.php - main email sending
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

    //
    // Uses PHPMailer
    //    
    
    
    function sendEmail($email_to, $email_title, $email_text, $is_html=false, $email_attachment=null)
    {
        return sendPHPMailer(null, null, $email_to, $email_title, $email_text, $email_attachment, $is_html);
    }
    
    function sendPHPMailer($email_from, $email_from_name, $email_to, $email_title, $email_text, $email_attachment, $is_html){
        
        global $system;

        if(!$email_from) $email_from = 'no-reply@'.(defined('HEURIST_MAIL_DOMAIN')?HEURIST_MAIL_DOMAIN:HEURIST_DOMAIN);
        if(!$email_from_name) $email_from_name = 'Heurist system. ('.HEURIST_SERVER_NAME.')';
        
        if(is_array($email_text)){
            $email_text =  json_encode($email_text);    
        }
        
        if(!$email_to){
            $system->addError(HEURIST_ACTION_BLOCKED, 
                        'Cannot send email. Recipient email address is not defined');
            return false;
        }else if(!is_array($email_to)){
            $email_to = array($email_to);
        }
        
        //$is_html = (strpos("\n",$email_text)===false);
       
        // strip all whitespaces
        $email_from = filter_var($email_from, FILTER_SANITIZE_EMAIL);
        
        //send an email with attachment
        $email = new PHPMailer();
        
        /*
        $mail->IsSMTP(); 
        $mail->SMTPAuth   = true; 
        $mail->Port       = 25; 
        $mail->Host       = "xx.xxx.x.x"; // SMTP server
        $mail->Username   = "myemail@mydomain.local";  
        $mail->Password   = <myemailpassword>;        
        
        $mail->From = 'contacto@45norte.com';
        $mail->addReplyTo($_POST['inputEmail'], $_POST['inputName']); //recipient
        */
        
        $email->CharSet = 'UTF-8';
        $email->Encoding = 'base64';
        $email->isHTML( $is_html ); 
        $email->SetFrom($email_from, $email_from_name); 
        $email->Subject   = $email_title; //'=?UTF-8?B?'.base64_encode($email_title).'?=';
        $email->Body      = $email_text;
        
        foreach($email_to as $email_address){
            
            $email_address = filter_var($email_address, FILTER_SANITIZE_EMAIL);
            if(!filter_var($email_address, FILTER_VALIDATE_EMAIL)){
                $system->addError(HEURIST_ACTION_BLOCKED, 
                        'Cannot send email. Recipient email address '.$email_address.' is not defined or invalid');
                return false;
            }
            
            $email->AddAddress( $email_address );
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
            $system->addError(HEURIST_SYSTEM_CONFIG, 
                    'Cannot send email. Please ask system administrator to verify that mailing is enabled on your server'
                    , $email->ErrorInfo);
            return false;
        }
        return true;
    }
    
    // 
    // Uses php native mail function (in send_email.php only)
    //	
    function sendEmail_native($email_to, $email_title, $email_text, $email_header, $is_utf8=false, $use_html=false){

        $res = "ok";
        
        $email_to = filter_var($email_to, FILTER_SANITIZE_EMAIL);

        if(!filter_var($email_to, FILTER_VALIDATE_EMAIL)){
            $res = "Mail send failed. Recipient email address is not defined or invalid.";
        }else if(!$email_text){
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
                    if(defined('HEURIST_DBNAME')){
                        $email_header = $email_header." (".HEURIST_DBNAME.")";
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
            }else if($is_utf8){
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


    function checkSmtp(){

        $smtpHost = '127.0.0.1'; //'localhost';
        $smtpPort = '25';
        $smtpTimeout = 5;

        $res = @fsockopen($smtpHost,
                      $smtpPort,
                      $errno,
                      $errstr,
                      $smtpTimeout);

      if (!is_resource($res))
      {
        error_log("email_smtp_error {$errno} {$errstr}");
        return false;
      }
      return true;
    }
    
?>
