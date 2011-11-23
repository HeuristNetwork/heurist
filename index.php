<?php

/**
 * index.php, the main entry point to Heurist, redirects to the search page
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/
 	define('ROOTINIT',"1");

	require_once(dirname(__FILE__).'/common/config/initialise.php');
	   // correct parameters, connection, existence of database are all checked in initialise.php

	if (@$_SERVER["QUERY_STRING"]) {
		$q = $_SERVER["QUERY_STRING"];
	}else{
		$q = "db=".HEURIST_DBNAME;
	}

	 	header('Location: '.HEURIST_URL_BASE.'search/search.html?'.$q);

	/*
	print "<h2>*logo* Heurist *vsn* &nbsp; db: " . HEURIST_DBNAME . "</h2>";
	print "<p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href=http://heuristscholar.org/h3/index.html target=_blank>Heurist Project</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
	print "<a href=http://heuristscholar.org/h3/help/webhelp target=_blank>Intro and Guides</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
	print "<a href = " . HEURIST_URL_BASE . "search/search.html?".$q . "&w=bookmark&q=tag:Favourites>My Favourites</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
	//TODO: NEED TO SUBSTITUTE THE USERS ID BELOW
	print "<a href = " . HEURIST_URL_BASE . "viewers/blog/index.html?u=101&".$q . ">My Blog</a><p>&nbsp;";

	print "<p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

	print "<b>Find</b> &nbsp;&nbsp; <input id=q name=q type=text>";
	print "<input id=find-button type=submit value='my bookmarks'>";
	print "<input id=find-button type=submit value='all records'>";

	print " <a href = " . HEURIST_URL_BASE . "search/search.html?".$q . "&w=bookmark>my bookmarks</a>";
	print " <a href = " . HEURIST_URL_BASE . "search/search.html?". $q ."&w=all>all records</a>";
	print "&nbsp;&nbsp;&nbsp;&nbsp;<a href = GOOGLE NEEDED HERE >Google</a><p>";
	*/

?>



