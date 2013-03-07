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
* brief description of file
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
<br>Please select a database from the list <!-- or <a href="../../admin/setup/createNewDB.php">Create New Database</a> -->
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
