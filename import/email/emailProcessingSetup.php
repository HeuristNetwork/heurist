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
	<title>Heurist Email harvester</title>
	<link rel="stylesheet" type="text/css" href="../../common/css/global.css">

	<script type="text/javascript">

	function _editUser(userID) {
		var URL = "";

		if(userID>0) {
			URL = top.HEURIST.basePath + "admin/ugrps/editUser.html?db=<?=HEURIST_DBNAME?>&recID="+userID;
		}
		else {
			return;
		}
		top.HEURIST.util.popupURL(top, URL, {
			"close-on-blur": false,
			"no-resize": false,
			height: 600,
			width: 640
		});
	}

	</script>
</head>

<body class="popup" width="600" height="300" style="font-size: 11px;overflow:auto;">

<p>Heurist will connect to an email server using the login details stored in the
   database properties (sysIdentification table) and retrieve emails received from
   specific email addresses (set in each user's profile). The emails are dissected
   and used to create Heurist records owned by that user.
   The email server must support IMAP.</p>
<p><a href="../../admin/setup/editSysIdentification.php?db=<?=HEURIST_DBNAME?>" target="_blank">
	<img src="../../common/images/external_link_16x16.gif"/>
   Configure connection to IMAP mail server</a></p>

<p>This function allows the owner of a Heurist database to set up an email
   account to which users of the database can forward emails they receive or copy
   emails that they send, in order to have them archived in the Heurist database.</p>
<p><a href="#" onClick="_editUser(<?=get_user_id()?>); return false;"> <img src="../../common/images/external_link_16x16.gif"/>
   Configure email addresses to be harvested</a></p>

<p>The default behaviour is to create a record of type Email, but in future this may be
   overriden with commands at the start of the email to create any specifid type, which will also allow tags
   and other information to be added to the record.</p>

<div style="width:100%; text-align: center;">
	<button onClick="window.open('../../import/email/emailProcessor.php?db=<?=HEURIST_DBNAME?>', '_self')" class="button">
   		<b>Harvest email from IMAP server</b>
   	</button>
</div>



</body>
</html>

