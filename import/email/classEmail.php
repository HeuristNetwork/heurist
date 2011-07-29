<?php

/**
 * email.php
 *
 * Email class, used to store email data.
 *
 * 2011/06/07
 * @author: Maxim Nikitin
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 *
 **/

include_once 'emailattachment.php';

class Email{

    private $from, $subject, $body, $attachments, $rec_id;

    public function __construct($from, $subject, $body, $attachments){

        $this->from=$from;
        $this->subject=$subject;
        $this->body=$body;
        $this->attachments=$attachments;
        $this->rec_id=false;
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
}