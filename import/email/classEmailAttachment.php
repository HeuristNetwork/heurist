<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* classEmailAttachement.php
* EmailAttachement class, used to store email attachement data.
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


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