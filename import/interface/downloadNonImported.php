<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

if (! $_REQUEST['import_id']) return;	// no time for funny buggers

// Make sure these are loaded before the session data is loaded, so that the class definitions are in place
require_once(dirname(__FILE__).'/../importerBaseClass.php');
require_once(dirname(__FILE__).'/../biblio/importRefer.php');
require_once(dirname(__FILE__).'/../biblio/importEndnoteRefer.php');
require_once(dirname(__FILE__).'/../biblio/importZotero.php');

require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');


jump_sessions();

$session_data = &$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['heurist-import-' . $_REQUEST['import_id']];
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
