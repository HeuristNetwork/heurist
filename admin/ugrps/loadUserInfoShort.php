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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



	/*<!--
	* filename, brief description, date of creation, by whom
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristNetwork.org
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
