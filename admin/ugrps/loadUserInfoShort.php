<?php

	/*<!--
	* filename, brief description, date of creation, by whom
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	-->*/

	/* load the user's search preferences into JS -- saved searches are all that spring to mind at the moment */

	define("SAVE_URI", "disabled");

	// using ob_gzhandler makes this stuff up on IE6-

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	//require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

	header('Content-type: text/javascript');

	print "if (!top.HEURIST.baseURL) top.HEURIST.baseURL = ".json_format(HEURIST_BASE_URL) . ";\n";
?>

	top.HEURIST.is_logged_in = function() { return <?= intval(is_logged_in()) ?> > 0; };
	top.HEURIST.get_user_id = function() { return <?= intval(get_user_id()) ?>; };
	top.HEURIST.get_user_name = function() { return "<?= addslashes(get_user_name()) ?>"; };
	top.HEURIST.get_user_username = function() { return "<?= addslashes(get_user_username()) ?>"; };
	top.HEURIST.is_admin = function() { return <?= intval(is_admin()) ?>; };
	top.HEURIST.grpDbOwners = <?=HEURIST_SYS_GROUP_ID ?>;
	top.HEURIST.is_wgAdmin = function(wgID) {
		if (!top.HEURIST.workgroups || !top.HEURIST.workgroups[wgID]) return false;
		var usrID = top.HEURIST.get_user_id(),
			i,
			wgAdmins = top.HEURIST.workgroups[wgID];
		for (i=0; i < wgAdmins.length; i++) {
			if ( wgAdmins[i].id == usrID) return true;
		}
		return false;
	};
<?php if (! is_admin()) { ?>
top.document.body.className += " is-not-admin";
<?php }
	if (! is_logged_in()) { ?>
top.document.body.className += " is-not-logged-in";
<?php } ?>
top.HEURIST.fireEvent(window, "heurist-obj-user-loaded");
