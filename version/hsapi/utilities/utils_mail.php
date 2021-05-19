<?php

    /**
    * utils_mail.php - main email sending
    *
    * TODO: Rationalise: THIS FILE IS DUPLICATED IN /common/php

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
	
	/* Allow external javascript to utilise php's sendMail, alternative to running mailto */
    if (isset($_POST['to']) && isset($_POST['title']) && isset($_POST['msg'])) {

        $email = $_POST['to'];
        $subject = ($_POST['title']);
        $msg = ($_POST['msg']);

        $res = sendEmail($email, $subject, $msg, null);
        
        echo $res;
    }	
	
    function sendEmail($email_to, $email_title, $email_text, $email_header, $is_utf8=false){

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
                if(defined('HEURIST_SERVER_NAME')){
                    if(defined('HEURIST_DBNAME')){
                        $email_header = $email_header." (".HEURIST_DBNAME.")";
                    }
                    $email_header = $email_header." <no-reply@".HEURIST_SERVER_NAME.">";
                }
            }


            if($is_utf8){
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

        $smtpHost = 'localhost';
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