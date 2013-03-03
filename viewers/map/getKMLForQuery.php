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

?>

<?php

// ARTEM: TO REMOVE


/**
   ARTEM: TOREMOVE ???

 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php
// Date in the past
header("Expires: Mon, 1 Jul 1982 00:00:00 GMT");
// always modified
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
// HTTP/1.1
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
// HTTP/1.0
header("Pragma: no-cache");
//XML Header
header("content-type:application/vnd.google-earth.kml+xml");
//header("content-type: text/xml");

/**
 * include requiered files
 */

// This is called by applyCredentials require_once(dirname(__FILE__).'/../../common/config/initialise.php');



define('SEARCH_VERSION', 1);
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');
require_once('class.searchCursor.php');
require_once('generateKMLFromCursor.php');

mysql_connection_select(DATABASE);


/**
 * format the request to a usable database search querry
 */
if (! @$_REQUEST['q']  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
	construct_legacy_search();	// migration path

if ($_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark')
	$search_type = BOOKMARK;	// my bookmarks
else
	$search_type = BOTH;	// all records

//multilevel parameter added to subvert multiple views for complex geography
if (@$_REQUEST['multilevel'] == "false"){
	$multilevel = false;
} else {
	$multilevel = true;
}

$querry = REQUEST_to_query('select distinct rec_ID, rec_URL, rec_ScratchPad, rec_RecTypeID ', $search_type);
/*****DEBUG****///error_log($querry);



/**
 * Create new Search and fromat the results to KML
 */

$search = new Search($querry);
$kmlbuilder = new KMLBuilder($search);
$kmlbuilder->build($multilevel);
print $kmlbuilder->getResult();



?>
