<?php

/*
* Copyright (C) 2005-2018 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* email.php
* Email class, used to store email data.* brief description of file
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2018 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


require_once(dirname(__FILE__).'/classEmailAttachment.php');

class Email{

    private $from, $subject, $body, $attachments, $rec_id, $err_message;

    public function __construct($from, $subject, $body, $attachments){

        $this->from=$from;
        $this->subject=$subject;
        $this->body=$body;
        $this->attachments=$attachments;
        $this->rec_id=false;
        $this->err_message = null;
    }

    public function getFrom(){

        return $this->from;
    }

    public function setFrom($from){

        $this->from=$from;
    }

    public function getSubject(){

        return $this->subject;
    }

    public function setSubject($subject){

        $this->subject=$subject;
    }

    public function getBody(){

        return $this->body;
    }

    public function setBody($body){

        $this->body=$body;
    }

    public function getAttachments(){

        return $this->attachments;
    }

    public function setAttachments($attachments){

        $this->attachments=$attachments;
    }

    public function getRecId(){

        return $this->rec_id;
    }

    public function setRecId($rec_id){

        $this->rec_id=$rec_id;
    }

    public function getErrorMessage(){

        return $this->err_message;
    }

    public function setErrorMessage($message){

        $this->err_message=$message;
    }

}