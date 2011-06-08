<?php

  /**
  * geekMail-1.0.php - A standalone version of the CodeIgniter PHP Framework's Email library
  * 
  * - Version 1.0
  * 
  * 
  * This class is a standalone version of the Email library used in the CodeIgniter PHP Framework:
  * 
  * - http://codeigniter.com/
  * 
  * 
  * Most of the existing CodeIgniter documentation for the library should work, with the exception
  * that the library won't be dynamically loaded from a CodeIgniter controller ("$this->load->..." and
  * "$this->email->from..."):
  * 
  * - http://codeigniter.com/user_guide/libraries/email.html
  * 
  * 
  * Usage example:
  * --------------
  * <?php
  * 
  *   require 'geekMail-1.0.php';
  *   
  *   $geekMail = new geekMail();
  *   
  *   $geekMail->setMailType('html');
  *   
  *   $geekMail->from('noreply@geekology.co.za', 'Geekology');
  *   
  *   $geekMail->to('willem@geekology.co.za');
  *   //$geekMail->cc('willem@geekology.co.za');
  *   //$geekMail->bcc('willem@geekology.co.za');
  *   
  *   $geekMail->subject('Example subject');
  *   
  *   $geekMail->message('Example message.');
  *   
  *   $geekMail->attach('/home/willem/file1.txt');
  *   $geekMail->attach('/home/willem/file2.zip');
  *   
  *   if (!$geekMail->send())
  *   {
  *     $errors = $geekMail->getDebugger();
  *     print_r($errors);
  *   }
  * 
  * ?>
  * 
  * 
  * Adapted by: Willem van Zyl
  * willem@geekology.co.za
  * http://www.geekology.co.za/blog/
  * 
  * 
  * ---
  * As with CodeIgniter itself, this code is free and Open Source, but
  * EllisLab, Inc. (the creators of CodeIgniter) require that this license
  * agreement be included in all adaptations:
  * 
  * http://codeigniter.com/user_guide/license.html
  * ---
  */
  
  class geekMail
  {
    private $_altBoundary   = '';
    private $_altMessage    = '';
    private $_atcBoundary   = '';
    private $_attachDisp    = array();
    private $_attachName    = array();
    private $_attachType    = array();
    private $_baseCharsets  = array('us-ascii', 'iso-2022-');
    private $_bccArray      = array();
    private $_bccBatchMode  = false;
    private $_bccBatchSize  = 200;
    private $_bitDepths     = array('7bit', '8bit');
    private $_body          = '';
    private $_ccArray       = array();
    private $_charSet       = 'utf-8';
    private $_crlf          = "\n";
    private $_debugMsg      = array();
    private $_encoding      = '8bit';
    private $_finalBody     = '';
    private $_headerStr     = '';
    private $_headers       = array();
    private $_ip            = false;
    private $_log           = array();
    private $_mailPath      = '/usr/sbin/sendmail';
    private $_mailType      = 'text';
    private $_multipart     = 'mixed';
    private $_newLine       = "\n";
    private $_priorities    = array('1 (Highest)', '2 (High)', '3 (Normal)', '4 (Low)', '5 (Lowest)');
    private $_priority      = '3';
    private $_protocol      = 'mail';
    private $_protocols     = array('mail', 'sendmail', 'smtp');
    private $_recipients    = array();
    private $_replytoFlag   = false;
    private $_safeMode      = false;
    private $_sendMultipart = true;
    private $_smtpAuth      = false;
    private $_smtpConnect   = '';
    private $_smtpHost      = '';
    private $_smtpPass      = '';
    private $_smtpPort      = '25';
    private $_smtpTimeout   = 5;
    private $_smtpUser      = '';
    private $_systemEmail   = 'noreply@getsmarter.co.za';
    private $_subject       = '';
    private $_userAgent     = 'Getsmarter';
    private $_validate      = false;
    private $_wordWrap      = true;
    private $_wrapChars     = '76';
    
    /**
    * Class constructor
    */
    public function __construct()
    {
      //should smtp authenticated be used?
      $this->_smtpAuth = (($this->_smtpUser == '') and ($this->_smtpPass == '')) ? false : true;
      
      //has safe mode been enabled in the PHP configuration file?
      $this->_safeMode = ((boolean)@ini_get("safe_mode") === false) ? false : true;
      
      
      $this->_logMessage('debug', 'geekMail Class Initialized');
    }
    
    
    /**
    * Logging methods to store and retrieve messages.
    */
    private function _logMessage($type, $message)
    {
      //check that a valid log type was specified:
      switch ($type)
      {
        case 'debug': break;
        case 'error': break;
        case 'info':  break;
        default:      $type = 'debug';
      }
      
      //log the message:
      $this->_log[] = array('date'    => date('Y-m-d H:i:s'),
                            'type'    => $type,
                            'message' => $message
                           );
    }
    
    public function getLog()
    {
      return $this->_log;
    }
    
    
    /**
    * Initialize the email data.
    */
    private function _clear($clearAttachments = false)
    {
      $this->_subject     = '';
      $this->_body        = '';
      $this->_finalBody   = '';
      $this->_headerStr   = '';
      $this->_replytoFlag = false;
      $this->_recipients  = array();
      $this->_headers     = array();
      $this->_debugMsg    = array();
      
      $this->_setHeader('User-Agent', $this->_userAgent);
      $this->_setHeader('Date', $this->_setDate());
      
      if ($clearAttachments !== false)
      {
        $this->_attachName = array();
        $this->_attachType = array();
        $this->_attachDisp = array();
      }
    }
    
    
    /**
    * Set the From address.
    */
    public function from($from, $name = '')
    {
      //set the from address if contained in brackets (e.g. "<willem@geekology.co.za>"):
      if (preg_match( '/\<(.*)\>/', $from, $match))
      {
        $from = $match['1'];
      }
      
      //validate the email address if needed:
      if ($this->_validate)
      {
        $this->_validateEmail($this->_strToArray($from));
      }
      
      //prepare the display name:
      if ($name != '')
      {
        //only use Q Encoding if there are characters that require it:
        if (!preg_match('/[\200-\377]/', $name))
        {
          //add slashes for non-printing characters, slashes and double quotes,
          //and surround them in double quotes:
          $name = '"' . addcslashes($name, "\0..\37\177'\"\\") . '"';
        }
        else
        {
          $name = $this->_prepQEncoding($name, true);
        }
      }
      
      $this->_setHeader("From", "{$name} <{$from}>");
      $this->_setHeader("Return-Path", "<{$from}>");
    }
    
    
    /**
    * Set the Reply-to address.
    */
    private function _replyTo($replyTo, $name = '')
    {
      //set the reply-to address if contained in brackets (e.g. "<willem@geekology.co.za>"):
      if (preg_match( '/\<(.*)\>/', $replyTo, $match))
      {
        $replyTo = $match['1'];
      }
      
      //validate the email address if needed:
      if ($this->_validate)
      {
        $this->_validateEmail($this->_strToArray($replyTo));
      }
      
      //prepare the display name:
      if ($name == '')
      {
        $name = $replyTo;
      }
      
      if (strncmp($name, '"', 1) != 0)
      {
        $name = '"' . $name . '"';
      }
      
      $this->_setHeader("Reply-To", "{$name} <{$replyTo}>");
      $this->_replytoFlag = true;
    }
    
    
    /**
    * Set the Recipient(s).
    */
    public function to($to)
    {
      $to = $this->_strToArray($to);
      $to = $this->_cleanEmail($to);
      
      //validate the email address(es) if needed:
      if ($this->_validate)
      {
        $this->_validateEmail($to);
      }
      
      if ($this->_getProtocol() != 'mail')
      {
        $this->_setHeader('To', implode(", ", $to));
      }
      
      switch ($this->_getProtocol())
      {
        case 'smtp':     $this->_recipients = $to;
                         break;
        case 'sendmail': $this->_recipients = implode(", ", $to);
                         break;
        case 'mail':     $this->_recipients = implode(", ", $to);
                         break;
      }
    }
    
    
    /**
    * Set the CC(s).
    */
    public function cc($cc)
    {
      $cc = $this->_strToArray($cc);
      $cc = $this->_cleanEmail($cc);
      
      //validate the email address(es) if needed:
      if ($this->_validate)
      {
        $this->_validateEmail($cc);
      }
      
      $this->_setHeader('Cc', implode(", ", $cc));
      
      if ($this->_getProtocol() == 'smtp')
      {
        $this->_ccArray = $cc;
      }
    }
    
    
    /**
    * Set the BCC(s).
    */
    public function bcc($bcc, $limit = '')
    {
      //should a batch limit be set?
      if (($limit != '') && (is_numeric($limit)))
      {
        $this->_bccBatchMode = true;
        $this->_bccBatchSize = $limit;
      }
      
      $bcc = $this->_strToArray($bcc);
      $bcc = $this->_cleanEmail($bcc);
      
      if ($this->_validate)
      {
        $this->_validateEmail($bcc);
      }
      
      if (($this->_getProtocol() == 'smtp') or 
          (($this->_bccBatchMode) && ((count($bcc)) > $this->_bccBatchSize)))
      {
        $this->_bccArray = $bcc;
      }
      else
      {
        $this->_setHeader('Bcc', implode(", ", $bcc));
      }
    }
    
    
    /**
    * Set the Subject.
    */
    public function subject($subject)
    {
      $subject = $this->_prepQEncoding($subject);
      $this->_setHeader('Subject', $subject);
    }
    
    
    /**
    * Set the Body.
    */
    public function message($body)
    {
      $this->_body = stripslashes(rtrim(str_replace("\r", "", $body)));
    }
    
    
    /**
    * Set the file attachment(s).
    */
    public function attach($filename, $disposition = 'attachment'/*'inline'*/)
    {
      $this->_attachName[] = $filename;
      $this->_attachType[] = $this->_mimeTypes(next(explode('.', basename($filename))));
      $this->_attachDisp[] = $disposition;
    }
    
    
    /**
    * Add a Header item.
    */
    private function _setHeader($header, $value)
    {
      $this->_headers[$header] = $value;
    }
    
    
    /**
    * Convert a String to an Array.
    */
    private function _strToArray($email)
    {
      if (!is_array($email))
      {
        if (strpos($email, ',') !== false)
        {
          $email = preg_split('/[\s,]/', $email, -1, PREG_SPLIT_NO_EMPTY);
        }
        else
        {
          $email = trim($email);
          settype($email, 'array');
        }
      }
      
      return $email;
    }
    
    
    /**
    * Set the Multipart Value.
    */
    private function _setaltMessage($str = '')
    {
      $this->_altMessage = ($str == '') ? '' : $str;
    }
    
    
    /**
    * Set the Mailtype.
    */
    private function _setmailtype($type = 'text')
    {
      $this->_mailType = ($type == 'html') ? 'html' : 'text';
    }
    
    public function setMailType($type = 'text')
    {
      $this->_mailType = ($type == 'html') ? 'html' : 'text';
    }
    
    
    /**
    * Set Wordwrapping.
    */
    private function _setwordwrap($wordwrap = true)
    {
      $this->_wordWrap = ($wordwrap === false) ? false : true;
    }
    
    
    /**
    * Set the Protocol.
    */
    private function _setprotocol($protocol = 'mail')
    {
      $this->_protocol = (!in_array($protocol, $this->_protocols, true)) ? 'mail' : strtolower($protocol);
    }
    
    
    /**
    * Set the Priority.
    */
    private function _setpriority($n = 3)
    {
      if (!is_numeric($n))
      {
        $this->_priority = 3;
        return;
      }
      
      
      if (($n < 1) or ($n > 5))
      {
        $this->_priority = 3;
        return;
      }
      
      $this->_priority = $n;
    }
    
    
    /**
    * Set the Newline Character.
    */
    private function _setnewLine($newLine = "\n")
    {
      if (($newLine != "\n") and ($newLine != "\r\n") and ($newLine != "\r"))
      {
        $this->_newLine = "\n";
        return;
      }
      
      $this->_newLine = $newLine;
    }
    
    
    /**
    * Set CRLF.
    */
    private function _setcrlf($crlf = "\n")
    {
      if (($crlf != "\n") and ($crlf != "\r\n") and ($crlf != "\r"))
      {
        $this->_crlf = "\n";
        return;
      }
      
      $this->_crlf = $crlf;
    }
    
    
    /**
    * Set the Message Boundary.
    */
    private function _setBoundaries()
    {
      //set the multipart/alternative boundary:
      $this->_altBoundary = "B_ALT_".uniqid('');
      
      //set the attachment boundary:
      $this->_atcBoundary = "B_ATC_".uniqid(''); // attachment boundary
    }
    
    
    /**
    * Get the Message ID.
    */
    private function _getMessageId()
    {
      $from = $this->_headers['Return-Path'];
      $from = str_replace('>', '', $from);
      $from = str_replace('<', '', $from);
      
      return '<' . uniqid('') . strstr($from, '@') . '>';
    }
    
    
    /**
    * Get the Mail Protocol
    */
    private function _getProtocol($return = true)
    {
      $this->_protocol = strtolower($this->_protocol);
      $this->_protocol = (!in_array($this->_protocol, $this->_protocols, true)) ? 'mail' : $this->_protocol;
      
      if ($return == true)
      {
        return $this->_protocol;
      }
    }
    
    
    /**
    * Get the Mail Encoding.
    */
    private function _getEncoding($return = true)
    {
      $this->_encoding = (!in_array($this->_encoding, $this->_bitDepths)) ? '8bit' : $this->_encoding;
      
      foreach ($this->_baseCharsets as $charset)
      {
        if (strncmp($charset, $this->_charSet, strlen($charset)) == 0)
        {
          $this->_encoding = '7bit';
        }
      }
      
      if ($return == true)
      {
        return $this->_encoding;
      }
    }
    
    
    /**
    * Get the content type (text / html / attachment).
    */
    private function _getContentType()
    {
      if (($this->_mailType == 'html') && (count($this->_attachName) == 0))
      {
        return 'html';
      }
      else if (($this->_mailType == 'html') && (count($this->_attachName) > 0))
      {
        return 'html-attach';
      }
      else if (($this->_mailType == 'text') && (count($this->_attachName) > 0))
      {
        return 'plain-attach';
      }
      else
      {
        return 'plain';
      }
    }
    
    
    /**
    * Set RFC 822 Date.
    */
    private function _setDate()
    {
      $timezone = date('Z');
      $operator = (strncmp($timezone, '-', 1) == 0) ? '-' : '+';
      $timezone = abs($timezone);
      $timezone = floor($timezone/3600) * 100 + ($timezone % 3600 ) / 60;
      
      return sprintf("%s %s%04d", date("D, j M Y H:i:s"), $operator, $timezone);
    }
    
    
    /**
    * Get a Mime message.
    */
    private function _getMimeMessage()
    {
      return "This is a multi-part message in MIME format." . $this->_newLine . 
             "Your email application may not support this format.";
    }
    
    
    /**
    * Validate an array of email addresses.
    */
    private function _validateEmail($email)
    {
      if (!is_array($email))
      {
        $this->_setErrorMessage('email_must_be_array');
        return false;
      }
      
      foreach ($email as $val)
      {
        if (!$this->_validEmail($val))
        {
          $this->_setErrorMessage('email_invalid_address', $val);
          return false;
        }
      }
      
      return true;
    }
    
    
    /**
    * Email validation.
    */
    private function _validEmail($address)
    {
      return (!preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $address)) ? false : true;
    }
    
    
    /**
    * Clean extended email address (e.g. "Willem van Zyl <willem@geekology.co.za>").
    */
    private function _cleanEmail($email)
    {
      if (!is_array($email))
      {
        if (preg_match('/\<(.*)\>/', $email, $match))
        {
          return $match['1'];
        }
        else
        {
          return $email;
        }
      }
      
      $cleanEmail = array();
      
      foreach ($email as $address)
      {
        if (preg_match( '/\<(.*)\>/', $address, $match))
        {
          $cleanEmail[] = $match['1'];
        }
        else
        {
          $cleanEmail[] = $address;
        }
      }
      
      return $cleanEmail;
    }
    
    
    /**
    * Build alternative plain-text message by stripping HTML if no text version was supplied.
    */
    private function _getAltMessage()
    {
      if ($this->_altMessage != '')
      {
        return $this->_wordWrap($this->_altMessage, '76');
      }
      
      if (preg_match('/\<body.*?\>(.*)\<\/body\>/si', $this->_body, $match))
      {
        $body = $match['1'];
      }
      else
      {
        $body = $this->_body;
      }
      
      $body  = trim(strip_tags($body));
      $body = preg_replace( '#<!--(.*)--\>#', "", $body);
      $body = str_replace("\t", "", $body);
      
      for ($i = 20; $i >= 3; $i--)
      {
        $n = '';
        
        for ($x = 1; $x <= $i; $x++)
        {
          $n .= "\n";
        }
        
        $body = str_replace($n, "\n\n", $body);
      }
      
      return $this->_wordWrap($body, '76');
    }
    
    
    /**
    * Word wrapping.
    */
    private function _wordWrap($str, $charLimit = '')
    {
      //set the character limit:
      if ($charLimit == '')
      {
        $charLimit = ($this->_wrapChars == '') ? '76' : $this->_wrapChars;
      }
      
      //reduce multiple spaces:
      $str = preg_replace("| +|", " ", $str);
      
      //standardize newlines:
      if (strpos($str, "\r") !== false)
      {
        $str = str_replace(array("\r\n", "\r"), "\n", $str);
      }
      
      //if the current word is surrounded by {unwrap} tags, strip the entire chunk
      //and replace it with a marker:
      $unwrap = array();
      if (preg_match_all("|(\{unwrap\}.+?\{/unwrap\})|s", $str, $matches))
      {
        for ($i = 0; $i < count($matches['0']); $i++)
        {
          $unwrap[] = $matches['1'][$i];
          $str = str_replace($matches['1'][$i], "{{unwrapped" . $i . "}}", $str);
        }
      }
      
      //do the initial wordwrap with PHP's native function:
      $str = wordwrap($str, $charLimit, "\n", false);
      
      //split the string into individual lines of text and cycle through them:
      $output = '';
      foreach (explode('\n', $str) as $line)
      {
        //is the line within the allowed character count?
        if (strlen($line) <= $charLimit)
        {
          $output .= $line . $this->_newLine;
          continue;
        }
        
        $temp = '';
        while ((strlen($line)) > $charLimit)
        {
          //if the over-length word is a URL we won't wrap it:
          if (preg_match("!\[url.+\]|://|wwww.!", $line))
          {
            break;
          }
          
          //trim the word:
          $temp .= substr($line, 0, $charLimit - 1);
          $line = substr($line, $charLimit - 1);
        }
        
        //if $temp contains data, an over-length word was split, add it back to current line:
        if ($temp != '')
        {
          $output .= $temp . $this->_newLine . $line;
        }
        else
        {
          $output .= $line;
        }
        
        $output .= $this->_newLine;
      }
      
      //put our markers back:
      if (count($unwrap) > 0)
      {
        foreach ($unwrap as $key => $val)
        {
          $output = str_replace("{{unwrapped" . $key . "}}", $val, $output);
        }
      }
      
      return $output;
    }
    
    
    /**
    * Build the final headers.
    */
    private function _buildHeaders()
    {
      $this->_setHeader('X-Sender', $this->_cleanEmail($this->_headers['From']));
      $this->_setHeader('X-Mailer', $this->_userAgent);
      $this->_setHeader('X-Priority', $this->_priorities[$this->_priority - 1]);
      $this->_setHeader('Message-ID', $this->_getMessageId());
      $this->_setHeader('Mime-Version', '1.0');
    }
    
    
    /**
    * Write the headers as a string.
    */
    private function _writeHeaders()
    {
      if ($this->_protocol == 'mail')
      {
        $this->_subject = $this->_headers['Subject'];
        unset($this->_headers['Subject']);
      }
      
      reset($this->_headers);
      $this->_headerStr = '';
      
      foreach ($this->_headers as $key => $val)
      {
        $val = trim($val);
        
        if ($val != '')
        {
          $this->_headerStr .= $key . ': ' . $val . $this->_newLine;
        }
      }
      
      if ($this->_getProtocol() == 'mail')
      {
        $this->_headerStr = rtrim($this->_headerStr);
      }
    }
    
    
    /**
    * Build the final body and attachments.
    */
    private function _buildMessage()
    {
      if (($this->_wordWrap === true) and ($this->_mailType != 'html'))
      {
        $this->_body = $this->_wordWrap($this->_body);
      }
      
      $this->_setBoundaries();
      $this->_writeHeaders();
      
      $header = ($this->_getProtocol() == 'mail') ? $this->_newLine : '';
      
      switch ($this->_getContentType())
      {
        case 'plain':
          $header .= "Content-Type: text/plain; charset=" . $this->_charSet . $this->_newLine;
          $header .= "Content-Transfer-Encoding: " . $this->_getEncoding();
          
          if ($this->_getProtocol() == 'mail')
          {
            $this->_headerStr .= $header;
            $this->_finalBody = $this->_body;
            
            return;
          }
          
          $header .= $this->_newLine . $this->_newLine . $this->_body;
          
          $this->_finalBody = $header;
          
          return;
          
          break;
        
        case 'html':
          if ($this->_sendMultipart === false)
          {
            $header .= "Content-Type: text/html; charset=" . $this->_charSet . $this->_newLine;
            $header .= "Content-Transfer-Encoding: quoted-printable";
          }
          else
          {
            $header .= "Content-Type: multipart/alternative; boundary=\"" . $this->_altBoundary . "\"" . $this->_newLine . $this->_newLine;
            $header .= $this->_getMimeMessage() . $this->_newLine . $this->_newLine;
            $header .= "--" . $this->_altBoundary . $this->_newLine;
            
            $header .= "Content-Type: text/plain; charset=" . $this->_charSet . $this->_newLine;
            $header .= "Content-Transfer-Encoding: " . $this->_getEncoding() . $this->_newLine . $this->_newLine;
            $header .= $this->_getAltMessage() . $this->_newLine . $this->_newLine . "--" . $this->_altBoundary . $this->_newLine;
            
            $header .= "Content-Type: text/html; charset=" . $this->_charSet . $this->_newLine;
            $header .= "Content-Transfer-Encoding: quoted-printable";
          }
          
          $this->_body = $this->_prepQuotedPrintable($this->_body);
          
          if ($this->_getProtocol() == 'mail')
          {
            $this->_headerStr .= $header;
            $this->_finalBody = $this->_body . $this->_newLine . $this->_newLine;
            
            if ($this->_sendMultipart !== false)
            {
              $this->_finalBody .= '--' . $this->_altBoundary . '--';
            }
            
            return;
          }
          
          $header .= $this->_newLine . $this->_newLine;
          $header .= $this->_body . $this->_newLine . $this->_newLine;
          
          if ($this->_sendMultipart !== false)
          {
            $header .= '--' . $this->_altBoundary . '--';
          }
          
          $this->_finalBody = $header;
          
          return;
          
          break;
        
        case 'plain-attach':
          $header .= "Content-Type: multipart/" . $this->_multipart . "; boundary=\"" . $this->_atcBoundary . "\"" . $this->_newLine . $this->_newLine;
          $header .= $this->_getMimeMessage() . $this->_newLine . $this->_newLine;
          $header .= '--' . $this->_atcBoundary . $this->_newLine;
          
          $header .= "Content-Type: text/plain; charset=" . $this->_charSet . $this->_newLine;
          $header .= "Content-Transfer-Encoding: " . $this->_getEncoding();
          
          if ($this->_getProtocol() == 'mail')
          {
            $this->_headerStr .= $header;
            
            $body = $this->_body . $this->_newLine . $this->_newLine;
          }
          
          $header .= $this->_newLine . $this->_newLine;
          $header .= $this->_body . $this->_newLine . $this->_newLine;
          
          break;
        
        case 'html-attach':
          $header .= "Content-Type: multipart/" . $this->_multipart . "; boundary=\"" . $this->_atcBoundary."\"" . $this->_newLine . $this->_newLine;
          $header .= $this->_getMimeMessage() . $this->_newLine . $this->_newLine;
          $header .= '--' . $this->_atcBoundary . $this->_newLine;
          
          $header .= "Content-Type: multipart/alternative; boundary=\"" . $this->_altBoundary . "\"" . $this->_newLine .$this->_newLine;
          $header .= '--' . $this->_altBoundary . $this->_newLine;
          
          $header .= "Content-Type: text/plain; charset=" . $this->_charSet . $this->_newLine;
          $header .= "Content-Transfer-Encoding: " . $this->_getEncoding() . $this->_newLine . $this->_newLine;
          $header .= $this->_getAltMessage() . $this->_newLine . $this->_newLine . '--' . $this->_altBoundary . $this->_newLine;
          
          $header .= "Content-Type: text/html; charset=" . $this->_charSet . $this->_newLine;
          $header .= "Content-Transfer-Encoding: quoted-printable";
          
          $this->_body = $this->_prepQuotedPrintable($this->_body);
          
          if ($this->_getProtocol() == 'mail')
          {
            $this->_headerStr .= $header;
            
            $body = $this->_body . $this->_newLine . $this->_newLine;
            $body .= '--' . $this->_altBoundary . '--' . $this->_newLine . $this->_newLine;
          }
          
          $header .= $this->_newLine . $this->_newLine;
          $header .= $this->_body . $this->_newLine . $this->_newLine;
          $header .= '--' . $this->_altBoundary . '--' . $this->_newLine . $this->_newLine;
          
          break;
      }
      
      $attachment = array();
      
      $z = 0;
      
      for ($i=0; $i < count($this->_attachName); $i++)
      {
        $filename = $this->_attachName[$i];
        $basename = basename($filename);
        $ctype = $this->_attachType[$i];
        
        if (!file_exists($filename))
        {
          $this->_setErrorMessage('email_attachment_missing', $filename);
          return false;
        }
        
        $h = '--' . $this->_atcBoundary . $this->_newLine;
        $h .= 'Content-type: ' . $ctype . '; ';
        $h .= "name=\"" . $basename . "\"" . $this->_newLine;
        $h .= 'Content-Disposition: ' . $this->_attachDisp[$i] . ';' . $this->_newLine;
        $h .= 'Content-Transfer-Encoding: base64' . $this->_newLine;
        
        $attachment[$z++] = $h;
        $file = filesize($filename) + 1;
        
        if (!$fh = fopen($filename, 'r'))
        {
          $this->_setErrorMessage('email_attachment_unreadable', $filename);
          return false;
        }
        
        $attachment[$z++] = chunk_split(base64_encode(fread($fh, $file)));
        fclose($fh);
      }
      
      if ($this->_getProtocol() == 'mail')
      {
        $this->_finalBody = $body . implode($this->_newLine, $attachment) . $this->_newLine .
                            '--' . $this->_atcBoundary . '--';
        
        return;
      }
      
      $this->_finalBody = $header . implode($this->_newLine, $attachment) . $this->_newLine .
                          '--' . $this->_atcBoundary . '--';
      
      return;
    }
    
    
    /**
    * Prepare quoted and printable content.
    */
    private function _prepQuotedPrintable($str, $charLimit = '')
    {
      //set the character limit, don't allow anything over 76:
      if (($charLimit == '') or ($charLimit > '76'))
      {
        $charLimit = '76';
      }
      
      //reduce multiple spaces:
      $str = preg_replace("| +|", " ", $str);
      
      //kill nulls:
      $str = preg_replace('/\x00+/', '', $str);
      
      //standardize newlines:
      if (strpos($str, "\r") !== false)
      {
        $str = str_replace(array("\r\n", "\r"), "\n", $str);
      }
      
      //take out '{unwrap}':
      $str = str_replace(array('{unwrap}', '{/unwrap}'), '', $str);
      
      //break into an array of lines:
      $lines = explode("\n", $str);
      
      $escape = '=';
      $output = '';
      
      foreach ($lines as $line)
      {
        $length = strlen($line);
        $temp = '';
        
        //loop through each character in the line to add soft-wrapping:
        for ($i = 0; $i < $length; $i++)
        {
          //grab the next character:
          $char = substr($line, $i, 1);
          $ascii = ord($char);
          
          //convert spaces and tabs but only if it's the end of the line:
          if ($i == ($length - 1))
          {
            $char = (($ascii == '32') or ($ascii == '9')) ? $escape . sprintf('%02s', dechex($ascii)) : $char;
          }
          
          //encode = signs
          if ($ascii == '61')
          {
            $char = $escape . strtoupper(sprintf('%02s', dechex($ascii)));  //=3D
          }
          
          //if we're at the character limit, add the line to the output and reset temp variable:
          if ((strlen($temp) + strlen($char)) >= $charLimit)
          {
            $output .= $temp . $escape . $this->_crlf;
            $temp = '';
          }
          
          //add the character to our temporary line:
          $temp .= $char;
        }
        
        //add our completed line to the output:
        $output .= $temp . $this->_crlf;
      }
      
      //get rid of extra CRLF tacked onto the end:
      $output = substr($output, 0, strlen($this->_crlf) * -1);
      
      return $output;
    }
    
    
    /**
    * Prepare Q Encoding.
    */
    private function _prepQEncoding($str, $from = false)
    {
      $str = str_replace(array("\r", "\n"), array('', ''), $str);
      
      //line length must not exceed 76 characters, so adjust for a space, 7 extra chars, and charset:
      $limit = 75 - 7 - strlen($this->_charSet);
      
      //these special characters must be converted too:
      $convert = array('_', '=', '?');
      
      if ($from === true)
      {
        $convert[] = ',';
        $convert[] = ';';
      }
      
      $output = '';
      $temp = '';
      
      for ($i = 0, $length = strlen($str); $i < $length; $i++)
      {
        //grab the next character:
        $char = substr($str, $i, 1);
        $ascii = ord($char);
        
        //convert all non-printable ASCII characters and specials:
        if (($ascii < 32) or ($ascii > 126) or (in_array($char, $convert)))
        {
          $char = '=' . dechex($ascii);
        }
        
        //handle regular spaces a bit more compactly than =20:
        if ($ascii == 32)
        {
          $char = '_';
        }
        
        //if the character limit has been reached, add the line to the output and reset the temp variable:
        if ((strlen($temp) + strlen($char)) >= $limit)
        {
          $output .= $temp . $this->_crlf;
          $temp = '';
        }
        
        //add the character to the temporary line:
        $temp .= $char;
      }
      
      $str = $output . $temp;
      
      $str = trim(preg_replace('/^(.*)$/m', ' =?' . $this->_charSet . '?Q?$1?=', $str));
      
      return $str;
    }
    
    
    /**
    * Send an email.
    * 
    * @access  public
    * @return  bool
    */
    public function send()
    {
      if ($this->_replytoFlag == false)
      {
        $this->_replyTo($this->_headers['From']);
      }
      
      if ((!isset($this->_recipients)) and (!isset($this->_headers['To'])) and
          (!isset($this->_bccArray)) and (!isset($this->_headers['Bcc'])) and
          (!isset($this->_headers['Cc'])))
      {
        $this->_setErrorMessage('email_no_recipients');
        return false;
      }
      
      $this->_buildHeaders();
      
      if (($this->_bccBatchMode) and (count($this->_bccArray) > 0))
      {
        if (count($this->_bccArray) > $this->_bccBatchSize)
        {
          return $this->_batchBccSend();
        }
      }
      
      $this->_buildMessage();
      
      if (!$this->_spoolEmail())
      {
        $this->_logMessage('debug', 'Failed to send email(s)');
        return false;
      }
      else
      {
        $this->_logMessage('debug', 'Email(s) sent');
        return true;
      }
    }
    
    
    /**
    * Batch Bcc send.
    */
    public function batchBccSend()
    {
      $float = $this->_bccBatchSize - 1;
      
      $set = '';
      
      $chunk = array();
      
      for ($i = 0; $i < count($this->_bccArray); $i++)
      {
        if (isset($this->_bccArray[$i]))
        {
          $set .= ", " . $this->_bccArray[$i];
        }
        
        if ($i == $float)
        {
          $chunk[] = substr($set, 1);
          $float = $float + $this->_bccBatchSize;
          $set = '';
        }
        
        if ($i == (count($this->_bccArray) - 1))
        {
          $chunk[] = substr($set, 1);
        }
      }
      
      for ($i = 0; $i < count($chunk); $i++)
      {
        unset($this->_headers['Bcc']);
        unset($bcc);
        
        $bcc = $this->_strToArray($chunk[$i]);
        $bcc = $this->_cleanEmail($bcc);
        
        if ($this->protocol != 'smtp')
        {
          $this->_setHeader('Bcc', implode(', ', $bcc));
        }
        else
        {
          $this->_bccArray = $bcc;
        }
        
        $this->_buildMessage();
        $this->_spoolEmail();
      }
    }
    
    
    /**
    * Unwrap special elements.
    */
    private function _unwrapSpecials()
    {
      $this->_finalBody = preg_replace_callback("/\{unwrap\}(.*?)\{\/unwrap\}/si", array($this, '_removeNlCallback'), $this->_finalBody);
    }
    
    
    /**
    * Strip line-breaks via callback.
    */
    private function _removeNlCallback($matches)
    {
      if ((strpos($matches[1], "\r") !== false) or (strpos($matches[1], "\n") !== false))
      {
        $matches[1] = str_replace(array("\r\n", "\r", "\n"), '', $matches[1]);
      }
      
      return $matches[1];
    }
    
    
    /**
    * Spool mail to the mail server.
    */
    private function _spoolEmail()
    {
      $this->_unwrapSpecials();
      
      switch ($this->_getProtocol())
      {
        case 'mail':
          if (!$this->_sendWithMail())
          {
            $this->_setErrorMessage('email_send_failure_phpmail');
            return false;
          }
          break;
        
        case 'sendmail':
          if (!$this->_sendWithSendmail())
          {
            $this->_setErrorMessage('email_send_failure_sendmail');
            return false;
          }
          break;
        
        case 'smtp':
          if (!$this->_sendWithSmtp())
          {
            $this->_setErrorMessage('email_send_failure_smtp');
            return false;
          }
          break;
      }
      
      //$this->_setErrorMessage('email_sent', $this->_getProtocol(true));
      //$this->_setErrorMessage('email_sent');
      
      return true;
    }
    
    
    /**
    * Send using mail().
    */
    private function _sendWithMail()
    {
      if ($this->_safeMode == true)
      {
        if (!mail($this->_recipients, $this->_subject, $this->_finalBody, $this->_headerStr))
        {
          return false;
        }
        else
        {
          return true;
        }
      }
      else
      {
        if (!mail($this->_recipients, $this->_subject, $this->_finalBody, $this->_headerStr,
                  '-f ' . $this->_cleanEmail($this->_headers['From'])))
        {
          return false;
        }
        else
        {
          return true;
        }
      }
    }
    
    
    /**
    * Send using sendmail.
    */
    private function _sendWithSendmail()
    {
      $fh = @popen($this->_mailPath . ' -oi -f ' . $this->_cleanEmail($this->_headers['From']) . ' -t', 'w');
      
      fputs($fh, $this->_headerStr);
      fputs($fh, $this->_finalBody);
      
      $status = pclose($fh);
      
      if ($status != 0)
      {
        $this->_setErrorMessage('email_exit_status', $status);
        $this->_setErrorMessage('email_no_socket');
        return false;
      }
      
      return true;
    }
    
    
    /**
    * Send using SMTP.
    */
    private function _sendWithSmtp()
    {
      if ($this->_smtpHost == '')
      {
        $this->_setErrorMessage('email_no_hostname');
        return false;
      }
      
      $this->_smtpConnect();
      $this->_smtpAuthenticate();
      
      $this->_sendCommand('from', $this->_cleanEmail($this->_headers['From']));
      
      foreach ($this->_recipients as $val)
      {
        $this->_sendCommand('to', $val);
      }
      
      if (count($this->_ccArray) > 0)
      {
        foreach ($this->_ccArray as $val)
        {
          if ($val != '')
          {
            $this->_sendCommand('to', $val);
          }
        }
      }
      
      if (count($this->_bccArray) > 0)
      {
        foreach ($this->_bccArray as $val)
        {
          if ($val != '')
          {
            $this->_sendCommand('to', $val);
          }
        }
      }
      
      $this->_sendCommand('data');
      
      //perform dot transformation on any lines that begin with a dot:
      $this->_sendData($this->_headerStr . preg_replace('/^\./m', '..$1', $this->_finalBody));
      
      $this->_sendData('.');
      
      $reply = $this->_getSmtpData();
      
      $this->_setErrorMessage($reply);
      
      if (strncmp($reply, '250', 3) != 0)
      {
        $this->_setErrorMessage('email_smtp_error', $reply);
        return false;
      }
      
      $this->_sendCommand('quit');
      
      return true;
    }
    
    
    /**
    * SMTP Connect.
    */
    private function _smtpConnect()
    {
      $this->_smtpConnect = fsockopen($this->_smtpHost,
                                      $this->_smtpPort,
                                      $errno,
                                      $errstr,
                                      $this->_smtpTimeout);
      
      if (!is_resource($this->_smtpConnect))
      {
        $this->_setErrorMessage('email_smtp_error', "{$errno} {$errstr}");
        return false;
      }
      
      $this->_setErrorMessage($this->_getSmtpData());
      
      return $this->_sendCommand('hello');
    }
    
    
    /**
    * Send SMTP command.
    */
    private function _sendCommand($command, $data = '')
    {
      switch ($command)
      {
        case 'hello':
          if (($this->_smtpAuth) or ($this->_getEncoding() == '8bit'))
          {
            $this->_sendData("EHLO {$this->_getHostname()}");
          }
          else
          {
            $this->_sendData("HELO {$this->_getHostname()}");
          }
          
          $response = 250;
          
          break;
        
        case 'from':
          $this->_sendData("MAIL FROM:<{$data}>");
          
          $response = 250;
          
          break;
        
        case 'to':
          $this->_sendData("RCPT TO:<{$data}>");
          
          $response = 250;
          
          break;
        
        case 'data':
          $this->_sendData('DATA');
          
          $response = 354;
          
          break;
        
        case 'quit':
          $this->_sendData('QUIT');
          
          $response = 221;
          
          break;
      }
      
      $reply = $this->_getSmtpData();
      
      $this->_debugMsg[] = "<pre>{$command}: {$reply}</pre>";
      
      if (substr($reply, 0, 3) != $response)
      {
        $this->_setErrorMessage('email_smtp_error', $reply);
        
        return false;
      }
      
      if ($command == 'quit')
      {
        fclose($this->_smtpConnect);
      }
      
      return true;
    }
    
    
    /**
    * SMTP Authenticate.
    */
    private function _smtpAuthenticate()
    {
      if (!$this->_smtpAuth)
      {
        return true;
      }
      
      if (($this->_smtpUser == '') and ($this->_smtpPass == ''))
      {
        $this->_setErrorMessage('email_no_smtp_unpw');
        
        return false;
      }
      
      $this->_sendData('AUTH LOGIN');
      
      $reply = $this->_getSmtpData();
      
      if (strncmp($reply, '334', 3) != 0)
      {
        $this->_setErrorMessage('email_failed_smtp_login', $reply);
        
        return false;
      }
      
      $this->_sendData(base64_encode($this->_smtpUser));
      
      $reply = $this->_getSmtpData();
      
      if (strncmp($reply, '334', 3) != 0)
      {
        $this->_setErrorMessage('email_smtp_auth_un', $reply);
        
        return false;
      }
      
      $this->_sendData(base64_encode($this->_smtpPass));
      
      $reply = $this->_getSmtpData();
      
      if (strncmp($reply, '235', 3) != 0)
      {
        $this->_setErrorMessage('email_smtp_auth_pw', $reply);
        
        return false;
      }
      
      return true;
    }
    
    
    /**
    * Send SMTP data.
    */
    private function _sendData($data)
    {
      if (!fwrite($this->_smtpConnect, $data . $this->_newLine))
      {
        $this->_setErrorMessage('email_smtp_data_failure', $data);
        
        return false;
      }
      else
      {
        return true;
      }
    }
    
    
    /**
    * Get SMTP data.
    */
    private function _getSmtpData()
    {
      $data = '';
      
      while ($str = fgets($this->_smtpConnect, 512))
      {
        $data .= $str;
        
        if (substr($str, 3, 1) == ' ')
        {
          break;
        }
      }
      
      return $data;
    }
    
    
    /**
    * Get Hostname.
    */
    private function _getHostname()
    {
      return (isset($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : 'localhost.localdomain';
    }
    
    
    /**
    * Get IP.
    */
    private function _getIp()
    {
      if ($this->_ip !== false)
      {
        return $this->_ip;
      }
      
      $cip = ((isset($_SERVER['HTTP_CLIENT_IP'])) and ($_SERVER['HTTP_CLIENT_IP'] != '')) ? $_SERVER['HTTP_CLIENT_IP'] : false;
      $rip = ((isset($_SERVER['REMOTE_ADDR'])) and ($_SERVER['REMOTE_ADDR'] != '')) ? $_SERVER['REMOTE_ADDR'] : false;
      $fip = ((isset($_SERVER['HTTP_X_FORWARDED_FOR'])) and ($_SERVER['HTTP_X_FORWARDED_FOR'] != "")) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : false;
      
      if ($cip && $rip)
        $this->_ip = $cip;
      else if ($rip)
        $this->_ip = $rip;
      else if ($cip)
        $this->_ip = $cip;
      else if ($fip)
        $this->_ip = $fip;
      
      if (strstr($this->_ip, ','))
      {
        $x = explode(',', $this->_ip);
        $this->_ip = end($x);
      }
      
      if (!preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/", $this->_ip))
      {
        $this->_ip = '0.0.0.0';
      }
      
      unset($cip);
      unset($rip);
      unset($fip);
      
      return $this->_ip;
    }
    
    
    /**
    * Get debug message.
    */
    public function getDebugger()
    {
      $msg = '';
      
      if (count($this->_debugMsg) > 0)
      {
        foreach ($this->_debugMsg as $val)
        {
          $msg .= $val;
        }
      }
      
      $msg .= "<pre>{$this->_headerStr}\n" . htmlspecialchars($this->_subject) . "\n" .
              htmlspecialchars($this->_finalBody) . "</pre>";
      
      return $msg;
    }
    
    
    /**
    * Set message.
    */
    private function _setErrorMessage($message, $val = '')
    {
      $this->_debugMsg[] = str_replace('%s', $val, $message) . '<br />';
    }
    
    
    /**
    * Mime types.
    */
    private function _mimeTypes($extension = '')
    {
      $mimes = array('hqx'   => 'application/mac-binhex40',
                     'cpt'   => 'application/mac-compactpro',
                     'doc'   => 'application/msword',
                     'bin'   => 'application/macbinary',
                     'dms'   => 'application/octet-stream',
                     'lha'   => 'application/octet-stream',
                     'lzh'   => 'application/octet-stream',
                     'exe'   => 'application/octet-stream',
                     'class' => 'application/octet-stream',
                     'psd'   => 'application/octet-stream',
                     'so'    => 'application/octet-stream',
                     'sea'   => 'application/octet-stream',
                     'dll'   => 'application/octet-stream',
                     'oda'   => 'application/oda',
                     'pdf'   => 'application/pdf',
                     'ai'    => 'application/postscript',
                     'eps'   => 'application/postscript',
                     'ps'    => 'application/postscript',
                     'smi'   => 'application/smil',
                     'smil'  => 'application/smil',
                     'mif'   => 'application/vnd.mif',
                     'xls'   => 'application/vnd.ms-excel',
                     'ppt'   => 'application/vnd.ms-powerpoint',
                     'wbxml' => 'application/vnd.wap.wbxml',
                     'wmlc'  => 'application/vnd.wap.wmlc',
                     'dcr'   => 'application/x-director',
                     'dir'   => 'application/x-director',
                     'dxr'   => 'application/x-director',
                     'dvi'   => 'application/x-dvi',
                     'gtar'  => 'application/x-gtar',
                     'php'   => 'application/x-httpd-php',
                     'php4'  => 'application/x-httpd-php',
                     'php3'  => 'application/x-httpd-php',
                     'phtml' => 'application/x-httpd-php',
                     'phps'  => 'application/x-httpd-php-source',
                     'js'    => 'application/x-javascript',
                     'swf'   => 'application/x-shockwave-flash',
                     'sit'   => 'application/x-stuffit',
                     'tar'   => 'application/x-tar',
                     'tgz'   => 'application/x-tar',
                     'xhtml' => 'application/xhtml+xml',
                     'xht'   => 'application/xhtml+xml',
                     'zip'   => 'application/zip',
                     'mid'   => 'audio/midi',
                     'midi'  => 'audio/midi',
                     'mpga'  => 'audio/mpeg',
                     'mp2'   => 'audio/mpeg',
                     'mp3'   => 'audio/mpeg',
                     'aif'   => 'audio/x-aiff',
                     'aiff'  => 'audio/x-aiff',
                     'aifc'  => 'audio/x-aiff',
                     'ram'   => 'audio/x-pn-realaudio',
                     'rm'    => 'audio/x-pn-realaudio',
                     'rpm'   => 'audio/x-pn-realaudio-plugin',
                     'ra'    => 'audio/x-realaudio',
                     'rv'    => 'video/vnd.rn-realvideo',
                     'wav'   => 'audio/x-wav',
                     'bmp'   => 'image/bmp',
                     'gif'   => 'image/gif',
                     'jpeg'  => 'image/jpeg',
                     'jpg'   => 'image/jpeg',
                     'jpe'   => 'image/jpeg',
                     'png'   => 'image/png',
                     'tiff'  => 'image/tiff',
                     'tif'   => 'image/tiff',
                     'css'   => 'text/css',
                     'html'  => 'text/html',
                     'htm'   => 'text/html',
                     'shtml' => 'text/html',
                     'txt'   => 'text/plain',
                     'text'  => 'text/plain',
                     'log'   => 'text/plain',
                     'rtx'   => 'text/richtext',
                     'rtf'   => 'text/rtf',
                     'xml'   => 'text/xml',
                     'xsl'   => 'text/xml',
                     'mpeg'  => 'video/mpeg',
                     'mpg'   => 'video/mpeg',
                     'mpe'   => 'video/mpeg',
                     'qt'    => 'video/quicktime',
                     'mov'   => 'video/quicktime',
                     'avi'   => 'video/x-msvideo',
                     'movie' => 'video/x-sgi-movie',
                     'doc'   => 'application/msword',
                     'word'  => 'application/msword',
                     'xl'    => 'application/excel',
                     'eml'   => 'message/rfc822'
                    );
      
      return (!isset($mimes[strtolower($extension)])) ? "application/x-unknown-content-type" : 
                                                        $mimes[strtolower($extension)];
    }
  }

?>