<?php

/*
* Copyright (C) 2005-2013 University of Sydney
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
* getListOfdatabases.php
* returns list of databases on the current server, with links
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

    // Deals with all the database connections stuff

    mysql_connection_select(DATABASE);
    if(mysql_error()) {
        die("Could not get database structure from given database source.");
    }
?>
<html>
<head>
	<title>Open Database</title>
	<link rel=stylesheet href='../../common/css/global.css'>
	<link rel=stylesheet href='../../common/css/admin.css'>
</head>

<body class="popup" width="300" height="800" style="font-size: 11px;overflow:auto;">

<?=(@$_REQUEST['popup']?"":"<div class='banner'><h2>Open Database</h2></div>") ?>
<div id='page-inner'>
<?php

	$email = null;
	$role = null;

	if(is_logged_in() && get_user_id()>0){

		//current user email
		$query = 'select '.USERS_EMAIL_FIELD.' from '.USERS_TABLE.' where '.USERS_ID_FIELD.'='.get_user_id();

		$res = mysql_query($query);
		while ($row = mysql_fetch_assoc($res)) {
			if ($row[USERS_EMAIL_FIELD])
				$email = $row[USERS_EMAIL_FIELD];
			else
				$email = null;
		}

		if(array_key_exists('role',$_REQUEST)){
			$role = $_REQUEST['role'];
		}else{
			$role = 'user'; // by default
		}
	}

	if($email){

		if(!(($role=='user')||($role=='admin'))){
			$role = null;
		}

		print "<div>Filter list: <select onchange='{document.location.href=\"getListOfDatabases.php?db=".HEURIST_DBNAME."&role=\"+this.value;}'>";
		print "<option ".
					(($role==null)?'selected':'')." value='0'>All</option><option ".
					(($role=='user')?'selected':'')." value='user'>User</option><option ".
					(($role=='admin')?'selected':'')." value='admin'>Administrator</option></select></div>";
	}


    print "<br /><div>Click on the database name to open in new window</div>";
	print "<ul class='dbList'>";

	$list = mysql__getdatabases(false, $email, $role);
	foreach ($list as $name) {
            print("<li><a href=".HEURIST_BASE_URL."?db=$name target=_blank>$name</a></li>");
	}
	print "</ul>";
?>
</div>
</body>
</html>

