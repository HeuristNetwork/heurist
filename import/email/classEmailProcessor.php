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
* classEmailProcessor.php
* EmailProcessor class, used to process emails by external callback procedure.
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


require_once(dirname(__FILE__).'/classEmail.php');

class EmailProcessor{

    private $server, $username, $password, $connection;
    private $out_charset='UTF-8';

    /**
    * Constructor.
    * @param $server string address of IMAP server
    * @param $username string username
    * @param $password string password
    * @param $use_ssl boolean connect via SSL. This is optional parameter. false by default.
    */
    public function __construct($server, $port, $username, $password, $use_ssl=false){

        $this->server=$server;
        $this->username=$username;
        $this->password=$password;

        if($port){
            $port=':'.$port;
        }

        if($use_ssl){
            $server='{'.$server.$port.'/imap/norsh/ssl/novalidate-cert}INBOX';
        }else{
            $server='{'.$server.$port.'/imap/norsh/notls/novalidate-cert}INBOX';
        }

        $this->connection=@imap_open($server, $username, $password);
        if(!$this->connection){
            throw new Exception(imap_last_error());
        }
    }

    public function __destruct(){

        @imap_close($this->connection);
    }



    /**
    * Process emails by invoking of callback processing function.
    * @param $callback function Function which should be executed for every email. This function should take an Email object as a parameter and return boolean as a result of processing.
    * @param $senders list of comma-separated emails/names. This parameter is optional and is used to filter incoming mail by senders. false by default.
    * @param $should_delete boolean Optional parameter. If this parameter is true and callback function returned true, email will be deleted.
    */
    public function process($callback, $senders=false, $should_delete=false){

        if($senders)$senders=split(",", $senders);
        if(is_array($senders) && (!empty($senders))){
            foreach($senders as $sender){
                if($sender==''){
                    continue;
                }
                $this->_process('FROM "'.$sender.'"', $callback, $should_delete);
            }
        }else{
            $this->_process('ALL', $callback, $should_delete);
        }

        imap_expunge($this->connection);
    }



    /**
    * Set output character encodung.
    *
    * @param $charset string An output charset used for the message body. Default is UTF-8.
    */
    public function setOutCharset($charset){

        $this->out_charset=$charset;
    }



    /**
    * Returns current output character encoding.
    *
    * @return <type> string
    */
    public function getOutCharset(){

        return $this->out_charset;
    }

    private function _process($criteria, $callback, $should_delete){

        $emails=imap_search($this->connection, $criteria);
        if($emails){
            rsort($emails);
            foreach($emails as $email_number){
                $structure=imap_fetchstructure($this->connection, $email_number);
                if($structure->ifparameters and (!strcasecmp($structure->parameters[0]->attribute, "CHARSET"))){
                    $charset=$structure->parameters[0]->value;
                }else{
                    $charset=false;
                }

                $body=$this->_get_part($this->connection, $email_number, "TEXT/PLAIN", $structure);
                if(!$body){
                    $body=$this->_get_part($this->connection, $email_number, "TEXT/HTML", $structure);
                }
                if(!$body){
                    $body='';
                }

                $overview=imap_fetch_overview($this->connection, $email_number);
                $subject=$this->_decode_mime_string($overview[0]->subject, $charset);
                $from=$this->_decode_mime_string($overview[0]->from, $charset);

                $attachments=$this->_get_attachements($this->connection, $email_number, $structure);

                $email=new Email($from, $subject, $body, $attachments);

                $callbackresult=$callback($email);
                if($callbackresult && $should_delete){
                    imap_delete($this->connection, $email_number);
                }
            }
        }
    }



    private function _get_mime_type($structure){

        $primary_mime_type=array("TEXT", "MULTIPART","MESSAGE", "APPLICATION", "AUDIO","IMAGE", "VIDEO", "OTHER");
        if($structure->subtype){
            return $primary_mime_type[(int)$structure->type].'/'.$structure->subtype;
        }
        return "TEXT/PLAIN";
    }



    private function _get_part($stream, $msg_number, $mime_type, $structure=false, $part_number=false){

        if(!$structure){
            $structure=imap_fetchstructure($stream, $msg_number);
        }
        if($structure){
            if($mime_type==$this->_get_mime_type($structure)){
                if(!$part_number){
                    $part_number="1";
                }
                $text=imap_fetchbody($stream, $msg_number, $part_number);
                if($structure->encoding==3){
                    $text=imap_base64($text);
                }else if($structure->encoding==4){
                    $text=imap_qprint($text);
                }

                if($structure->ifparameters){
                    if(!strcasecmp($structure->parameters[0]->attribute, "CHARSET")){
                        $charset=$structure->parameters[0]->value;
                        $text=$this->_iconv($text, $charset);
                    }
                }

                return $text;
            }

            if($structure->type==1){ // multipart
                foreach($structure->parts as $index=>$sub_structure){
                    if($part_number){
                        $prefix=$part_number.'.';
                    }else{
                        $prefix='';
                    }
                    $data=$this->_get_part($stream, $msg_number, $mime_type, $sub_structure, $prefix.($index+1));
                    if($data){
                        return $data;
                    }
                }
            }
        }
        return false;
    }



    private function _get_attachements($connection, $msg_number, $structure=false){

        if(!$structure){
            $structure=imap_fetchstructure($connection, $msg_number);
        }
        $attachments=array();
        if(isset($structure->parts) && count($structure->parts)){
            for($i=0; $i<count($structure->parts); $i++){
                $is_attachment=false;
                $filename='';
                $name='';
                $body='';

                if($structure->parts[$i]->ifdparameters){
                    foreach($structure->parts[$i]->dparameters as $object){
                        if(strtolower($object->attribute)=='filename'){
                            $is_attachment=true;
                            $filename=$object->value;
                        }
                    }
                }
                if($structure->parts[$i]->ifparameters){
                    foreach($structure->parts[$i]->parameters as $object){
                        if(strtolower($object->attribute)=='name'){
                            $is_attachment=true;
                            $name=$object->value;
                        }
                    }
                }
                if($is_attachment){
                    $body=imap_fetchbody($connection, $msg_number, $i+1);
                    if($structure->parts[$i]->encoding==3){ // BASE64
                        $body=base64_decode($body);
                    }else if($structure->parts[$i]->encoding==4){ // QUOTED-PRINTABLE
                        $body=imap_qprint($body);
                    }

                    $attachments[]=new EmailAttachement($filename, $name, $body);
                }
            }
        }
        return $attachments;
    }



    private function _iconv($text, $charset){

        if(strcasecmp($charset, $this->out_charset)){
            $text=iconv($charset, $this->out_charset, $text);
        }
        return $text;
    }



    private function _decode_mime_string($text, $structure_charset=false){

        $result='';
        $text_parts=imap_mime_header_decode($text);
        foreach($text_parts as $text_part){
            if($text_part->charset!='default'){
                $result.=$this->_iconv($text_part->text, $text_part->charset);
            }else if($structure_charset){
                $result.=$this->_iconv($text_part->text, $structure_charset);
            }else{
                $result.=$text_part->text;
            }
        }
        return $result;
    }
}
