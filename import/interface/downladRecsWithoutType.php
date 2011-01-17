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

header('Content-type: text/plain');

print $session_data['parser']->outputEntries($session_data['no_reftype_entries']);

?>
