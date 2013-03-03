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

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/

 if (! $_REQUEST['import_id']) return;	// no time for funny buggers

// Make sure these are loaded before the session data is loaded, so that the class definitions are in place
require_once(dirname(__FILE__).'/../importerBaseClass.php');
require_once(dirname(__FILE__).'/../biblio/importRefer.php');
require_once(dirname(__FILE__).'/../biblio/importEndnoteRefer.php');
require_once(dirname(__FILE__).'/../biblio/importZotero.php');

require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');


jump_sessions();

$session_data = &$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['heurist-import-' . $_REQUEST['import_id']];
if (! $session_data) return;	// print out something about session expiry, even though that's really unlikely

$out_entries = array();
// Mainly: grab all the entries that failed validation or had other data errors.
foreach (array_keys($session_data['non_out_entries']) as $i)
	$out_entries[] = &$session_data['non_out_entries'][$i];

// Also: check if there are any import-ready entries that haven't been inserted (in which case they'll have bookmark IDs)
foreach (array_keys($session_data['out_entries']) as $i)
	if (! $session_data['out_entries'][$i]->getBookmarkID()) $out_entries[] = &$session_data['out_entries'][$i]->_foreign;

header('Content-type: text/plain');

print $session_data['parser']->outputEntries($out_entries);

?>
