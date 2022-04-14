<?php

    /**
    * db access to usrUGrps table for users
    * 
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
require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/dbEntityBase.php');
require_once (dirname(__FILE__).'/../dbaccess/db_files.php');

require_once (dirname(__FILE__).'/../../vendor/autoload.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class DbSysBugreport extends DbEntityBase
{

    /**
    *  search users
    * 
    *  other parameters :
    *  details - id|name|list|all or list of table fields
    *  offset
    *  limit
    *  request_id
    * 
    *  @todo overwrite
    */
    public function search(){
        return null;
    }
    
    //
    // validate permission
    //    
    protected function _validatePermission(){
        
        if(!$this->system->has_access()){
             $this->system->addError(HEURIST_REQUEST_DENIED, 
                    'You must be logged in for bug reporting. Insufficient rights (logout/in to refresh) for this operation');
             return false;
        }
        
        return true;
    }
    
    //      
    //
    //
    public function save(){

        if(!$this->prepareRecords()){
                return false;    
        }

        if(is_array($this->records) && count($this->records)==1){
            //SPECIAL CASE - this is response to emailForm widget 
            // it sends email to owner of database or to email specified in website_id record
            $fields = $this->records[0];
            if(@$fields['email'] && @$fields['content']){
                 return $this->_prepareEmail($fields);
            }
        }
        
        //validate permission for current user and set of records see $this->recordIDs
        if(!$this->_validatePermission()){
            return false;
        }
        
        //validate values and check mandatory fields
        foreach($this->records as $record){
        
            $this->data['fields'] = $record;

            //validate mandatory fields
            if(!$this->_validateMandatory()){
                return false;
            }
        }
        
        $record = $this->records[0];

        
        $toEmailAddress = HEURIST_MAIL_TO_BUG;

        if(!(isset($toEmailAddress) && $toEmailAddress)){
             $this->system->addError(HEURIST_SYSTEM_CONFIG, 
                    'The owner of this instance of Heurist has not defined either the info nor system emails');
             return false;
        }
        
        $sMessage = '';
        
        $message = array();
        $message["rectype"] = '2-253';
        
        $bug_title = 'Bug report or feature request: '.$record['2-1'];
        $message['type:2-1'] = $record['2-1'];
    
        //keep new line
        $bug_descr = $record['2-3'];
        
        if($bug_descr){
            $bug_descr = str_replace("\n","&#13;",$bug_descr);
            $bug_descr = str_replace("\"","\\\"",$bug_descr);
            $message['type:2-3'] = $bug_descr;
            
            $sMessage = '<p>'.str_replace("\n",'<br>', $record['2-3']).'</p>';
        }

        $repro_steps = $record['2-4'];
        $repro_steps = explode("\n", $repro_steps);  //split on line break
        $message['type:2-4'] = $repro_steps;
        
        if(count($repro_steps)>0){
            $sMessage = $sMessage.'<p>Reproduction steps:<br>'.implode('<br>',$repro_steps).'</p>';
        }

        //add current system information into message
        $ext_info = array();
        array_push($ext_info, "    Browser information: ".$_SERVER['HTTP_USER_AGENT']);

        //add current heurist information into message
        array_push($ext_info, "   Heurist codebase: ".HEURIST_BASE_URL);
        array_push($ext_info, "   Heurist version: ".HEURIST_VERSION);
        array_push($ext_info, "   Heurist dbversion: ".$this->system->get_system('sys_dbVersion').'.'
                                                      .$this->system->get_system('sys_dbSubVersion').'.'
                                                      .$this->system->get_system('sys_dbSubSubVersion'));
        array_push($ext_info, "   Heurist database: ". HEURIST_DBNAME_FULL);
        
        
        $user = $this->system->getCurrentUser();
        if($user){
            $user = user_getByField($this->system->get_mysqli(), 'ugr_ID', $user['ugr_ID']);
            
            array_push($ext_info, "   Heurist user: ".@$user['ugr_Name'].' ('.@$user['ugr_eMail'].')');
        }
        $message['type:2-51'] = $ext_info;  //DT_BUG_REPORT_EXTRA_INFO;
        $sMessage = $sMessage.'<p>'.implode('<br>',$ext_info).'</p>';
        
        $filename = null;
        $attachment_temp_name = @$record['2-38'];
        if($attachment_temp_name){
            
            $info = parent::getTempEntityFile($attachment_temp_name);
            if($info!=null){ //found
            
                $extension = pathinfo($info->getFilename(), PATHINFO_EXTENSION);
                //$extension = $info->getExtension(); since 5.3.6 
                $filename = $info->getPathname();
                
                $message['type:2-38'] = array($info->getFilename(), $extension);
            }
        }
        
        if($this->_sendEmail('support@HeuristNetwork.org', 'Bug reporter', $toEmailAddress, 
                $bug_title, 
                (true?$sMessage:$message),  //since 02 Dec 2021 we sent human readable message
                $filename)){
            return array(1); //fake rec id
        }else{
            return false;
        }
        
        /*
        $message =  json_encode($message);
       
        //send an email with attachment
        $email = new PHPMailer();
        $email->isHTML(true); 
        $email->SetFrom('support@HeuristNetwork.org', 'Bug reporter'); //'bugs@'.HEURIST_SERVER_NAME 
        $email->Subject   = $bug_title;
        $email->Body      = $message;
        $email->AddAddress( $toEmailAddress );        
        if($filename!=null){
            $email->addAttachment($filename);// , 'new.jpg'); 
        }
       
        try{
            $email->send();
            return array(1); //fake rec id
        } catch (Exception $e) {
            $this->system->addError(HEURIST_SYSTEM_CONFIG, 
                    'Cannot send email. Please ask system administrator to verify that mailing is enabled on your server'
                    , $email->ErrorInfo);
            return false;
        }
        */
    }  
    
    //
    //
    //    
    private function _sendEmail($email_from, $email_from_name, $email_to, $email_title, $email_text, $email_attachment){

        if(!$email_from) $email_from = 'info@HeuristNetwork.org';
        if(!$email_from_name) $email_from_name = 'Heurist system';
        
        if(is_array($email_text)){
            $email_text =  json_encode($email_text);    
        }

        $email_from = filter_var($email_from, FILTER_SANITIZE_EMAIL);
        $email_to = filter_var($email_to, FILTER_SANITIZE_EMAIL);

        //send an email with attachment
        $email = new PHPMailer();
        $email->isHTML(true); 
        $email->SetFrom($email_from, $email_from_name); //'bugs@'.HEURIST_SERVER_NAME 
        $email->Subject   = $email_title;
        $email->Body      = $email_text;
        $email->AddAddress( $email_to );        
        if($email_attachment!=null){
            $email->addAttachment($email_attachment);// , 'new.jpg'); 
        }
       
        try{
            $email->send();
            return true;
        } catch (Exception $e) {
            $this->system->addError(HEURIST_SYSTEM_CONFIG, 
                    'Cannot send email. Please ask system administrator to verify that mailing is enabled on your server'
                    , $email->ErrorInfo);
            return false;
        }
        
    }
    
    //
    // this is response to emailForm widget 
    // it sends email to owner of database or to email specified in website_id record
    //
    private function _prepareEmail($fields){
        
        //1. verify captcha
        if (@$fields['captcha'] && @$_SESSION["captcha_code"]){
            
            $is_InValid = (@$_SESSION["captcha_code"] != @$fields['captcha']);
            
            if (@$_SESSION["captcha_code"]){
                unset($_SESSION["captcha_code"]);
            }
            
            if($is_InValid) {
                $this->system->addError(HEURIST_ACTION_BLOCKED, 
                   'Are you a bot? Please enter the correct answer to the challenge question');
                return false;                                
            }
        }else {
            $this->system->addError(HEURIST_ACTION_BLOCKED, 
                    'Captcha is not defined. Please provide correct value');
            return false;
        }
        
        
        //2. get email fields
        $email_text = @$fields['content'];
        if(!$email_text){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Email message is not defined.');
            return false;
        }
        $email_from = @$fields['email']; 
        if(!$email_from){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Email address is not defined.');
            return false;
        }
        
        
        $email_title = null;
        $email_from_name = @$fields['person']; 
        $email_to = null;
        
        //3. get $email_to - either address from website_id record or current database owner
        $rec_ID = $fields['website_id'];
        if($rec_ID>0){
            $this->system->defineConstant('DT_NAME');
            $this->system->defineConstant('DT_EMAIL');
            
            $record = recordSearchByID($this->system, $rec_ID, array(DT_NAME, DT_EMAIL), 'rec_ID');
            if($record){
                $email_title = 'From website '.recordGetField($record, DT_NAME).'.';
                $email_to = recordGetField($record, DT_EMAIL);
            }
        }
        if(!$email_to){
            $email_to = user_getDbOwner($this->system->get_mysqli(), 'ugr_eMail');
        }
        if(!$email_title){
            $email_title = '"Contact us" form. ';
        }
        if($email_from_name){
            $email_title = $email_title.'  From '.$email_from_name;
        }
        $email_text = 'From '.$email_from.' ( '.$email_from_name.' )<br>'.$email_text;
    
        $email_from = null;
        $email_from_name = null;
    
        if($this->_sendEmail($email_from, $email_from_name, $email_to, $email_title, $email_text, null)){
            return array(1); 
        }else{
            return false;
        }
        
    }
            
    //
    //
    //
    public function delete($disable_foreign_checks = false){
        return false;
    }
    
    //
    // batch action for users
    // 1) import users from another db
    //
    public function batch_action(){
         return false;
    }    
}
?>
