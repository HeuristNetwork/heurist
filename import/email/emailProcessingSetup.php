<?php

/** 
 *  emailProcessorSetup.php : configure access to an imap email account which can be 
 *  used to receive emails forwarded or copied to it, which can then be extracted
 *  and turned into records for a particuaklr user 
 * @version $Id$
 * @copyright 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

if (! is_logged_in()) {
    header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
    return;
}
        
if(!is_admin()){
    print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=../../php/login.php?logout=1>Log out</a></p></body></html>";
    exit;
}

?>

<html>

<head>
 <style type="text/css"> </style>
 <title>Heurist Email harvester</title>
</head>

<body class="popup" width=600 height=500 style="font-size: 11px;">

<h3>Heurist Email harvester</h3>

<p>Heurist will connect to an email server using the login details stored in the 
   database properties (sysIdentification table) and retrieve emails received from 
   specific email addresses (set in each user's profile). The emails are dissected
   and used to create Heurist records owned by that user. 
   The email server must support IMAP.</p>

<p>This function allows the owner of a Heurist database to set up an email 
   account to which users of the database can forward emails they receive or copy 
   emails that they send, in order to have them archived in the Heurist database. 

<p>The default behaviour is to create a record of type Email, but in future this may be 
   overriden with commands at the start of the email to create any specifid type, which will also allow tags 
   and other information to be added to the record.</p>

   
<p><img src="../../common/images/external_link_16x16.gif"/> 
   <a href="../../admin/setup/editSysIdentification.php" target=_blank>
   Configure connection to IMAP mail server</a>   
   

<p> <img src="../../common/images/external_link_16x16.gif"/>
   <a href="../../admin/ugrps/editUser.html?db=<?=HEURIST_DBNAME?>&recID=<?=get_user_id()?>" target=_blank>
   Configure email addresses to be harvested</a>   
   
   
<p> <b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="../../import/email/emailProcessor.php">
   Harvest email from IMAP server</a>   </b> <br>
   
   
   
</body>
</html>

