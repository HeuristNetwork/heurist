<?php

/**
 * emailattachement.php
 *
 * EmailAttachement class, used to store email attachement data.
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

class EmailAttachement{

    private $filename, $name, $body;

    public function __construct($filename, $name, $body){

        $this->filename=$filename;
        $this->name=$name;
        $this->body=$body;
    }

    public function getFilename(){

        return $this->filename;
    }

    public function setFilename($filename){

        $this->filename=$filename;
    }

    public function getName(){

        return $this->name;
    }

    public function setName($name){

        $this->name=$name;
    }

    public function getBody(){

        return $this->body;
    }

    public function setBody($body){

        $this->body=$body;
    }
}