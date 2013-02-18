<?php

    /* getListOfdatabases.php - returns list of databases on the current server, with links
    * Ian Johnson 10/8/11
    * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
    * @link: http://HeuristScholar.org
    * @license http://www.gnu.org/licenses/gpl-3.0.txt
    * @package Heurist academic knowledge management system
    * @todo
    *
    -->*/

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

