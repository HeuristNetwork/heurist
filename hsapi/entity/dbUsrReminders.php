<?php

    /**
    * db access to usrReminders table
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
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
require_once (dirname(__FILE__).'/dbEntitySearch.php');
require_once (dirname(__FILE__).'/../utilities/utils_mail.php');

class DbUsrReminders extends DbEntityBase
{

    /**
    *  search usrReminders
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
                
        if(!@$this->data['rem_OwnerUGrpID']){
            $this->data['rem_OwnerUGrpID'] = $this->system->get_user_id();
        }
        
        if(parent::search()===false){
              return false;   
        }
        
        $needCheck = false;
        $needRecords = false;
        
        //compose WHERE 
        $where = array();
        $from_table = array($this->config['tableName']);
        
        $pred = $this->searchMgr->getPredicate('rem_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rem_OwnerUGrpID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rem_RecID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rem_Message');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->searchMgr->getPredicate('rem_ToWorkgroupID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rem_ToUserID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rem_ToEmail');
        if($pred!=null) array_push($where, $pred);
        

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'rem_ID';
            
        }else if(@$this->data['details']=='name' || @$this->data['details']=='list'){

            $this->data['details'] = 'rem_ID,rem_RecID,rem_OwnerUGrpID,rem_ToWorkgroupID,rem_ToUserID,rem_ToEmail,rem_Message,rem_StartDate,rem_Freq,u1.ugr_Name as rem_ToWorkgroupName,concat(u2.ugr_FirstName,\' \',u2.ugr_LastName) as rem_ToUserName';

            $needRecords = true;//($this->data['details']=='list');

            $from_table[0] = $from_table[0]
                    .' LEFT JOIN sysUGrps u1 on rem_ToWorkgroupID=u1.ugr_ID '
                    .' LEFT JOIN sysUGrps u2 on rem_ToUserID=u2.ugr_ID ';
        
        }else if(@$this->data['details']=='full'){

            $this->data['details'] = 'rem_ID,rem_RecID,rem_OwnerUGrpID,rem_ToWorkgroupID,rem_ToUserID,rem_ToEmail,rem_Message,rem_StartDate,rem_Freq';
            
        }else{
            $needCheck = true;
        }
        
        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }
        
        //validate names of fields
        if($needCheck && !$this->_validateFieldsForSearch()){
            return false;
        }

        //----- order by ------------
        //compose ORDER BY 
        $order = array();
        
        $value = @$this->data['sort:rem_Modified'];
        if($value!=null){
            array_push($order, 'rem_Modified '.($value==1?'ASC':'DESC'));
        }else{
            $value = @$this->data['sort:rem_RecTitle'];
            if($value!=null){
                array_push($order, 'rec_Title '.($value==1?'ASC':'DESC'));
                $needRecords = true;
            }else{
                $value = @$this->data['sort:rem_StartDate'];
                if($value!=null){
                    array_push($order, 'rem_StartDate '.($value==1?'ASC':'DESC'));
                }
            }
        }           
        
        
        
        $is_ids_only = (count($this->data['details'])==1);
        
        if($needRecords){
              array_push($this->data['details'], 'rec_Title as rem_RecTitle');
              array_push($from_table,'Records');
              array_push($where, 'rec_ID=rem_RecID');
        }
        
            
        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details'])
        .' FROM '.implode(',', $from_table);

         if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
         }
         if(count($order)>0){
            $query = $query.' ORDER BY '.implode(',',$order);
         }
         
         $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();

        $calculatedFields = null;
        
        $result = $this->searchMgr->execute($query, $is_ids_only, $this->config['entityName'], $calculatedFields);
        
        return $result;
    }
    
    //
    // validate permission for edit tag
    // for delete and assign see appropriate methods
    //    
    protected function _validatePermission(){
        
        if(!$this->system->is_dbowner() && count($this->recordIDs)>0){ //there are records to update/delete
            
            $ugrID = $this->system->get_user_id();
            
            $mysqli = $this->system->get_mysqli();
             
            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'], $this->primaryField, 
                    'rem_ID in ('.implode(',', $this->recordIDs).') AND rem_OwnerUGrpID!='.$ugrID);
            
            $cnt = count($recIDs_norights);       
                    
            if($cnt>0){
                $this->system->addError(HEURIST_REQUEST_DENIED, 
                (($cnt==1 && (!isset($this->records) || count($this->records)==1))
                    ? 'Reminder belongs'
                    : $cnt.' Reminders belong')
                    .' to other user. Insufficient rights for this operation');
                return false;
            }
        }
        
        return true;
    }
    
    
    //
    //
    //    
    protected function prepareRecords(){
    
        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){
            $rec_ID = intval(@$record[$this->primaryField]);
            $isinsert = ($rec_ID<1);
            if($isinsert){
                if(!($this->records[$idx]['rem_OwnerUGrpID']>0)){
                    $this->records[$idx]['rem_OwnerUGrpID'] = $this->system->get_user_id();
                }
                $this->records[$idx]['rem_Nonce'] = dechex(rand());
                $this->fields['rem_Nonce'] = array(); //to pass data to save 
            }
            $this->records[$idx]['rem_Modified'] = null; //reset
        }

        return $ret;
        
    }    
    
    //
    // batch action for reminders - email sending 
    //
    public function batch_action(){
        
        /*
        $recordIDs = prepareIds($this->data['recIDs']);
        if(count($recordIDs)>0){
            //find record by ids  - todo
            
        }
        */
        
        if(!$this->prepareRecords()){
                return false;    
        }
        
        $rec_IDs = prepareIds(@$this->data['rec_IDs']);
        $is_notification = (count($rec_IDs)>0);
        
        $mysqli = $this->system->get_mysqli();
        
        foreach($this->records as $record){
            
            if($record[$this->primaryField]>0){
                $this->recordIDs[] = $record[$this->primaryField];
            }
                    
            $recipients = array();
            if (@$record['rem_ToEmail']) {
                array_push($recipients, array(
                    "email" => $record['rem_ToEmail'],
                    "e"        => $record['rem_ToEmail'],
                    "u"        => null));
            }
            else if (@$record['rem_ToUserID']) {
                
                $row = mysql__select_row($mysqli,
                    'select usr.ugr_FirstName,usr.ugr_LastName,usr.ugr_eMail FROM sysUGrps usr '
                    .'WHERE usr.ugr_Type="user" and usr.ugr_ID='.$record['rem_ToUserID']);
                if ($row) {
                    array_push($recipients, array(
                        "email" => $row[0].' '.$row[1].' <'.$row[2].'>',
                        "e"        => null,
                        "u"        => $record['rem_ToUserID']));
                }
            }
            else if (@$record['rem_ToWorkgroupID']) {
                
                if($record['rem_ID']>0){
                    
                    $query = 'select usr.ugr_FirstName,usr.ugr_LastName,usr.ugr_eMail,usr.ugr_ID '
                               .' from sysUsrGrpLinks left join sysUGrps usr on ugl_UserID=usr.ugr_ID'
                               .' left join usrRemindersBlockList on rbl_UGrpID=usr.ugr_ID and rbl_RemID = '.$record['rem_ID']
                               .' where ugl_GroupID = '.$record['rem_ToWorkgroupID'].' and isnull(rbl_RemID)';
                }else{
                    $query = 'select usr.ugr_FirstName,usr.ugr_LastName,ugr_eMail,usr.ugr_ID'
                                       .' from sysUsrGrpLinks left join sysUGrps usr on ugl_UserID=usr.ugr_ID'
                                       .' where ugl_GroupID='.$record['rem_ToWorkgroupID'];
                }
                
                                
                $recs = mysql__select_all($mysqli, $query);
                
                foreach ($recs as $row)
                    array_push($recipients, array(
                        "email" => $row[0].' '.$row[1].' <'.$row[2].'>',
                        "e"        => null,
                        "u"        => $row[3]));
            }
            
            if(count($recipients)>0){

            //sender params - reminder owner
            $owner = mysql__select_row($mysqli,
                'select usr.ugr_FirstName,usr.ugr_LastName,usr.ugr_eMail '
                    .'FROM sysUGrps usr where usr.ugr_Type="user" and usr.ugr_ID='
                    .$record['rem_OwnerUGrpID']);
            if ($owner) {
                
                $email_owner = $owner[0].' '.$owner[1].' <'.$owner[2].'>';
                
                if($is_notification){
                    $email_headers = 'From: '.$email_owner.' <no-reply@'.HEURIST_SERVER_NAME.'>';    
                    
                    //find associated records
                    $bibs = mysql__select_assoc2($mysqli,
                        'select rec_ID, rec_Title from Records '.
                                        'where rec_ID in ('. implode(',', $rec_IDs) .')');
                    
                    $email_title = '[HEURIST] Email from '.$owner[0].' '.$owner[1].' ('
                        .(count($bibs)>1?count($bibs).' references':'one reference').')';
                }else{
                    $email_headers = 'From: Heurist reminder service <no-reply@'.HEURIST_SERVER_NAME.'>';        
                    
                    //find associated record
                    $bib = mysql__select_row($mysqli,
                        'select rec_Title, rec_OwnerUGrpID, rec_NonOwnerVisibility, grp.ugr_Name from Records '.
                                        'left join sysUGrps grp on grp.ugr_ID=rec_OwnerUGrpID and grp.ugr_Type!="user" '.
                                        'where rec_ID = '.$record['rem_RecID']);
                    
                    $email_title = '"'.$bib[0].'"'; //rec_Title    
                    if (@$record['rem_ToUserID'] != @$record['rem_OwnerUGrpID']){
                        $email_title .= ' from ' . $owner[0].' '.$owner[1];
                    }
                }
                
                if (@$record['rem_ToEmail']!=$owner[2]  || @$record['rem_ToUserID'] != @$record['rem_OwnerUGrpID']){
                    $email_headers .= "\r\nCc: ".$email_owner;
                }
                $email_headers .= "\r\nReply-To: ".$email_owner;


                foreach($recipients as $recipient) {
                    if($is_notification){
                        
                        $email_text = $owner[0].' '.$owner[1].' would like to draw some records to your attention, with the following note:'. "\n\n"
                                    . '"'.$record['rem_Message'] . '"' . "\n\n"
                                    . 'Access them and add them (if desired) to your Heurist records at:' . "\n\n"
                                    . HEURIST_BASE_URL.'?w=all&db='.HEURIST_DBNAME.'&q=ids:'.implode(',', $rec_IDs) . "\n\n"
                                    . 'To add records, select them and then Selected > Bookmark' . "\n\n"
                                    . "Id      Title\n" . "------  ---------\n";                            
                        foreach($bibs as $rec_id => $rec_title){
                            $email_text .= str_pad("$rec_id", 8) . $rec_title . "\n";
                        }
                                                                                            
                        $email_text .= "\n\n-------------------------------------------\n\n"
                                    .  "This email was generated by Heurist (".HEURIST_MAIL_TO_INFO.").\n\n";
                                    
                    
                    }else{
                        $email_text = 'Reminder From: ' . ($record['rem_ToUserID'] == $record['rem_OwnerUGrpID'] ? 'you'
                                                            : $email_owner) . "\n\n"
                                    . 'For: "'.$bib[0].'"' . "\n\n" //rec_Title
                                    . 'URL: '.HEURIST_BASE_URL.'?w=all&db='.HEURIST_DBNAME.'&q=ids:'.$record['rem_RecID'] . "\n\n";

                        if ($bib[1] && $bib[2] == 'hidden') { //rec_OwnerUGrpID  rec_NonOwnerVisibility
                            $email_text .= "Note: Record belongs to workgroup ".$bib[3] . "\n"   //ugr_Name
                                            ."You must be logged in to Heurist and a member of this workgroup to view it". "\n\n";
                        }

                        $email_text .= 'Message: '.$record['rem_Message'] . "\n\n";

                        if (@$record['rem_ID']  &&  $record['rem_Freq'] != "once") {
                            $email_text .= "-------------------------------------------\n\n"
                                        .  "You will receive this reminder " . $record['rem_Freq'] . "\n"
                                        .  "Click below if you do not wish to receive this reminder again:\n\n"
                                        .  $BASE_URL."records/reminders/deleteReminder.php?r=".$record['rem_ID']
                                        . "db=".HEURIST_DBNAME
                                        .  ($recipient['u'] ? "&u=".$recipient['u'] : "&e=".$recipient['e']) . "&h=".$record['rem_Nonce'] . "\n\n";
                        } else {
                            $email_text .= "-------------------------------------------\n\n"
                                        .  "You will not receive this reminder again.\n\n";
                        }
                    }
                    
                    $res = sendEmail($recipient['email'], $email_title, $email_text, $email_headers, true);
                    if($res!='ok'){
                        $this->system->addError(HEURIST_SYSTEM_CONFIG, $res);
                        return false;
                    }
                    
                }//for recipients
            
            }else{
                //can get owner data
                $this->system->addError(HEURIST_NOT_FOUND, 'Can\'t get reminder\'s owner information');
                return false;
            }
            }else{
                //no recipients found
                $this->system->addError(HEURIST_NOT_FOUND, 'Can\'t get reminder\'s recipients');
                return false;
            }
            
        }//for reminders        
        
        
        return true;
    }
    
}
?>
