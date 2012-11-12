<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/
	define('NO_DB_ALLOWED',1);


    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

	$msg = "Ambiguous database name, or no database name supplied.";
	if(@$_REQUEST['msg']){
		$msg = $_REQUEST['msg'];
	}
?>
<html>
<head>
<title>Heurist Reference Database - Error</title>

<link rel=stylesheet type='text/css' href='../../common/css/global.css'>
<link rel=stylesheet type='text/css' href='../../common/css/admin.css'>
<link rel=stylesheet type='text/css' href='../../common/css/login.css'>
</head>
<body>
<div id=page style="padding: 20px;">

<div id="logo" title="Click the logo at top left of any Heurist page to return to your Favourites"></div>

<div>
<h2><?=$msg?></h2>
Please select the desired database from the list or <a href="../../admin/setup/createNewDB.php">Create New Database</a>
</div>
<div id="loginDiv" style="height:auto; margin-top:44px; overflow-y:auto;text-align:left;">
<ul class='dbList'>
<?php
	$list = mysql__getdatabases(false);
	foreach ($list as $name) {
            print("<li><a href='".HEURIST_BASE_URL."?db=$name'>$name</a></li>");
	}
?>
</ul>
</div>
</div>
</body>
</html>
