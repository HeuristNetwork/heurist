<?php

    /**
    * db access to usrUGrps table for users
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
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
require_once (dirname(__FILE__).'/db_files.php');

require_once(dirname(__FILE__).'/../../external/php/geekMail-1.0.php');



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
             $this->system->addError(HEURIST_ACTION_BLOCKED, 
                    'You must be logged in for bug reporting. Insufficient rights for this operation');
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
        
        //send an email with attachment
        $geekMail = new geekMail();
        $geekMail->setMailType('html');
        $geekMail->to($toEmailAddress);
        
        $message = array();
        $message["rectype"] = '2-253';
        
        $bug_title = $record['2-1'];
        $message['type:2-1'] = $record['2-1'];
    
        $geekMail->from('bugs@HeuristNetwork.org', 'Bug reporter'); //'noreply@HeuristNetwork.org', 'Bug Report');
        $geekMail->subject('Bug report or feature request: '.$bug_title);

        //keep new line
        $bug_descr = $record['2-3'];
        
        if($bug_descr){
            $bug_descr = str_replace("\n","&#13;",$bug_descr);
            $bug_descr = str_replace("\"","\\\"",$bug_descr);
            $message['type:2-3'] = $bug_descr;
        }

        $repro_steps = $record['2-4'];
        $repro_steps = explode("\n", $repro_steps);  //split on line break
        $message['type:2-4'] = $repro_steps;

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
            array_push($ext_info, "   Heurist user: ".@$user['ugr_Name']);
        }
        $message['type:2-51'] = $ext_info;  //DT_BUG_REPORT_EXTRA_INFO;
        
        
        $attachment_temp_name = @$record['2-38'];
        if($attachment_temp_name){
            
            $info = parent::getTempEntityFile($attachment_temp_name);
            if($info!=null){ //found
            
                $extension = pathinfo($info->getFilename(), PATHINFO_EXTENSION);
                //$extension = $info->getExtension(); since 5.3.6 
                $filename = $info->getPathname();
                $geekMail->attach($filename);
                $message['type:2-38'] = array($info->getFilename(), $extension);
            }
        }
        
        $message =  json_encode($message);
        $geekMail->message($message);
        
        if (!$geekMail->send())
        {
             $this->system->addError(HEURIST_SYSTEM_CONFIG, 
                    'Cannot send email. Please ask system administrator to verify that mailing is enabled on your server');
            return false;
        }else{
            //@todo - remove temp files
            
            return array(1); //fake rec id
        }
    }  
            
    //
    //
    //
    public function delete(){
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
