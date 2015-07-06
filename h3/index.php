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
* index.php
* the main entry point to Heurist, redirects to the search page
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


	define('ROOTINIT',"1");//signal that we are enterring from the root

	require_once(dirname(__FILE__).'/common/config/initialise.php');
	// correct parameters, connection, existence of database are all checked in initialise.php

	if (@$_SERVER["QUERY_STRING"]) {
		$q = $_SERVER["QUERY_STRING"];
		if (!preg_match("/db=/",$q)){
			$q .= "&db=".HEURIST_DBNAME;
		}
	}else{
		$q = "db=".HEURIST_DBNAME;
	}

		header('Location: '.HEURIST_BASE_URL_V4.'?'.$q);


?>

