<?php

/** 
 *  email_processor_setup.php : configure access to an imap email account which can be 
 *  used to receive emails forwarded or copied to it, which can then be extracted
 *  and turned into records for a particuaklr user 
 * @version $Id$
 * @copyright 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

require_once('../../php/modules/cred.php');

if(!is_logged_in()){
    header('Location: '.BASE_PATH.'login.php');
    exit;
}
        
if(!is_admin()){
    print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=../../php/login.php?logout=1>Log out</a></p></body></html>";
    exit;
}

require_once('../../php/modules/db.php');
require_once('../../legacy/.ht_stdefs');
?>

<html>

<head>
 <style type="text/css"> </style>
 <title>Heurist Email processor setup</title>
</head>

<body>

<h3>Heurist Email processor setup</h3>
<p>When a user requests it, Heurist will connect to an email server using the 
login details below and retrieve emails received from specific email addresses 
(configured under each user), dissect them and create Heurist records for that 
user. The email server must support IMAP.</p>

<p>This function allows the owner of a Heurist database to set up an email 
account to which users of the database can forward emails they receive or copy 
emails that they send, in order to have them archived in the Heurist database. 
The default is to create a record of type Email, but in future this may be 
overriden with commands at the start of the email, which will also allow tags 
and other information to be added to the record.</p>

<?php
    mysql_connection_db_insert(DATABASE);

    $updated=false;
    $message='';
    if(isset($_POST['savebutton'])){
        $updated=true;
        $update_values=array('server'=>mysql_real_escape_string($_POST['server']),
                             'port'=>mysql_real_escape_string($_POST['port']),
                             'protocol'=>mysql_real_escape_string($_POST['protocol']),
                             'username'=>mysql_real_escape_string($_POST['username']),
                             'password'=>mysql_real_escape_string($_POST['password']));
        //print_r($update_values);
        mysql_query("update `system` set `sys_email_imap_server`='".$update_values['server']."', `sys_email_imap_port`='".$update_values['port']."', `sys_email_imap_protocol`='".$update_values['protocol']."', `sys_email_imap_username`='".$update_values['username']."', `sys_email_imap_password`='".$update_values['password']."'");
        $message=mysql_error();
        if(!$message){
            $message='Configuration saved successfully.';
        }
    }

    $query_result=mysql_query("select `sys_email_imap_server`, `sys_email_imap_port`, `sys_email_imap_protocol`, `sys_email_imap_username`, `sys_email_imap_password` from `system`");
    $fetch_result=mysql_fetch_assoc($query_result);
    //print_r($fetch_result);
?>

<form method="POST">
    
    <table border="0">
        <tr>
            <td colspan="5"><b><?php if($updated){echo $message;} ?></b></td>
        </tr>
        <tr>
            <td width="20">&nbsp;</td>
            <td width="100">Server</td>
            <td width="226"><input type="text" name="server" size="20" value="<?php echo $fetch_result['sys_email_imap_server'] ?>"> </td>
            <td width="23">&nbsp;</td>
            <td>&nbsp;eg. imap.gmail.com</td>
        </tr>
        <tr>
            <td width="4">&nbsp;</td>
            <td>Port</td>
            <td width="226"><input type="text" name="port" size="9" value="<?php echo $fetch_result['sys_email_imap_port'] ?>"></td>
            <td width="23">&nbsp;</td>
            <td>&nbsp;eg. 993 for gmail</td>
        </tr>
        <tr>
            <td width="4">&nbsp;</td>
            <td>Protocol</td>
            <td width="226">
            <fieldset style="width: 226px; height: 24px; padding: 2">
            <legend></legend>
            
            <input type="radio" name="protocol" value="noprotocol" <?php if($fetch_result['sys_email_imap_protocol']=='noprotocol'){echo "checked";} ?> >None&nbsp;&nbsp;&nbsp;
            <input type="radio" name="protocol" value="ssl" <?php if($fetch_result['sys_email_imap_protocol']=='ssl'){echo "checked";} ?> >SSL&nbsp;&nbsp;&nbsp;
            <input type="radio" name="protocol" value="tls" <?php if($fetch_result['sys_email_imap_protocol']=='tls'){echo "checked";} ?> >TLS</fieldset></td>

            <td width="23"></td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <td width="4">&nbsp;</td>
            <td>User name</td>
            <td width="226"><input type="text" name="username" size="31" value="<?php echo $fetch_result['sys_email_imap_username'] ?>"></td>
            <td width="23">&nbsp;</td>
            <td>eg. myname123@gmail.com</td>
        </tr>
        <tr>
            <td width="4">&nbsp;</td>
            <td>Password&nbsp;&nbsp;&nbsp; </td>
            <td width="226"><input type="text" name="password" size="20" value="<?php echo $fetch_result['sys_email_imap_password'] ?>"></td>
            <td width="23">&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <td width="4">&nbsp;</td>
            <td>&nbsp;</td>
            <td width="226">&nbsp;</td>
            <td width="23">&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <td width="4">&nbsp;</td>
            <td>&nbsp;</td>
            <td width="226">&nbsp;</td>
            <td width="23">&nbsp;</td>
            <td><input type="submit" value="Save" name="savebutton">&nbsp;&nbsp;&nbsp;
        </tr>
    </table>
</form>

</body>
</html>