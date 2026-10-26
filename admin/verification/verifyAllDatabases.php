<?php
/**
* verifyAllDatabases.php - Perform the simpler integrity verifications on all databases on the server.
*
* @fileOverview This script performs all the quicker integrity verification tasks on the list of provided databases.
*                 This includes checks for:
*                   Term duplication, record values to field types, default values, entity definitions, record pointers,
*                   Parent/Child pointers, date indexing and geospatial values.
*                 This script does not perform any sort of fixing (except automatic fixes) and only reports the issues.
*                 The reports are all simplified versions of the actual report, the full report is still available through Admin > Verify integrity
*                 When run from the command line, the report will be emailed to the database owner instead.
*
* @project     Heurist academic knowledge management system
* @package     Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay <blmckay13@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7
*/

$requestedDatabases = [];
$requestedActions = [];
$emailOutput = false;
$newline = '<br>';

// Check if the script is run from the command line
if(isset($argv)){

	$ARGV = []; // Stores parsed command-line arguments
	$i = 0;

	// Parse command-line arguments
	while($i < count($argv)){
		// Handle arguments starting with '-'
		if($argv[$i][0] === '-'){
			// Check for arguments with values, e.g., -key value
			if(isset($argv[$i + 1]) && $argv[$i + 1][0] !== '-'){
				$ARGV[$argv[$i]] = $argv[$i + 1];
				++$i; // Skip the next value as it's already assigned
			}elseif(strpos($argv[$i], '-db=') === 0){
				$ARGV['-db'] = substr($argv[$i], 4);
			}elseif(strpos($argv[$i], '-a=') === 0){
                $ARGV['-a'] = substr($argv[$i], 3);
            }
		}else{
			// Add standalone arguments to the list
			$ARGV[] = $argv[$i];
		}
		++$i;
	}
	
	// Parse the database argument (-db)
	if(isset($ARGV['-db'])){
		$requestedDatabases = explode(',', $ARGV['-db']);
	}
    if(isset($ARGV['-a'])){
        $requestedActions = explode(',', @$ARGV['-action'] ?? @$ARGV['-a']);
    }

    $newline = "\n";

    $emailOutput = true;

}else{

    define('ADMIN_PWD_REQUIRED', 1);

    $requestedDatabases = isset($_REQUEST['db']) && is_string($_REQUEST['db']) ? explode(',', $_REQUEST['db']) : [];
    $requestedActions = isset($_REQUEST['a']) && is_string($_REQUEST['a']) ? explode(',', $_REQUEST['a']) : [];
}

define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once 'verifyValue.php';

use hserv\utilities\DbVerify;

$system = new hserv\System();
if(!$system->init(null, false, false)){
    exit("Cannot establish connection to sql server\n");
}

// Setup server name
if(!defined('HEURIST_SERVER_NAME') && isset($serverName)){
	define('HEURIST_SERVER_NAME', $serverName); // 'heurist.huma-num.fr'
}
	
// Validate server name
if(!defined('HEURIST_SERVER_NAME') || empty(HEURIST_SERVER_NAME)){
	exit("The script was unable to determine the server's name, please define it within heuristConfigIni.php then re-run this script.\n");
}
$serverNameVar = HEURIST_SERVER_NAME;

$mysqli = $system->getMysqli();
$databases = mysql__getdatabases4($mysqli, false);

// Allowed verify actions, non-slow actions only
$allowedActions = [
    'dup_terms' => 'Invalid/Duplicate Terms',
    'field_type' => 'Field Types',
    'default_values' => 'Default Values',
    'defgroups' => 'Definitions Groups',
    'title_mask' => 'Title Masks',
    'owner_ref' => 'Record Owner/Creator',
    'pointer_targets' => 'Pointer Targets',
    'target_parent' => 'Invalid Parents',
    'empty_fields' => 'Empty Fields',
    'nonstandard_fields' => 'Non-Standard Fields',
    'dateindex' => 'Date Index',
    'multi_swf_values' => 'Multiple Workflow Stages',
    'geo_values' => 'Geo Values',
    'term_values' => 'Term Values',
    'expected_terms' => 'Expected Terms'
];

$mysqli = $system->getMysqli();
$dbVerify = new DbVerify($system, true);
$verificationResults = [];

if(!defined('HEURIST_RTY_ICON')){
    define('HEURIST_RTY_ICON', '_HEURIST_RTY_ICON_');
}

foreach($databases as $database){

    if($requestedDatabases !== [] && !in_array($database, $requestedDatabases)){
        continue;
    }

    // Reset term lookup
    global $trmLookup;
    $trmLookup = [];
    VerifyValue::reset();

    mysql__usedatabase($mysqli, $database);

    $databaseResults = [];
    foreach($allowedActions as $action => $actionName){

        if($requestedActions !== [] && !in_array($action, $requestedActions)){
            continue;
        }

        $funcName = "check_{$action}";

        $actionResponse = $dbVerify->$funcName([]);

        if($actionResponse === false){

            $databaseResults[$action] = 'Failed';

            continue;
        }elseif(!$actionResponse || $actionResponse['status'] === true){
            continue;
        }

        $actionMsg = trim($actionResponse['message']);
        if($actionMsg === ''){
            continue;
        }

        $actionMsg = str_replace(HEURIST_RTY_ICON, HEURIST_BASE_URL . "?db={$database}&icon=", $actionMsg);
        $actionMsg = str_replace(['<h3', '</h3>'], ['<div', '</div>'], $actionMsg);
        $actionMsg = strip_tags($actionMsg, '<div><span><a><strong><em><br><table><tr><td>');

        $actionMsg = "<h4>{$actionName}</h4>{$actionMsg}";

        $databaseResults[$action] = $actionMsg;
    }

    if($emailOutput){

        if(empty($databaseResults)){ // skip email
            continue;
        }

        $databaseResults = implode('<hr>', $databaseResults);

        $owner = user_getDbOwner($mysqli);

        if(!$owner){
            echo "Unable to send email to owner of {$database}{$newline}";
            continue;
        }

        $databaseResults = <<<EMAIL
        Hello {$owner['ugr_FirstName']},<br>
        This is a simplified integrity report for your Heurist database <strong>{$database}</strong> on the server <strong>{$serverNameVar}</strong></em>.<br>
        You can run the integrity report yourself by: logging into your Heurist database and going to Admin > Verify integrity.
        From there you can also fix any issues/errors that have been found by the checker.
        {$databaseResults}
        EMAIL;

        $emailResult = sendPHPMailer(null, null, $owner['ugr_eMail'], "{$database} - Verify Integrity", $databaseResults, null, true);

        echo $emailResult
            ? "Sent email to {$owner['ugr_eMail']} for database {$database}{$newline}"
            : "Failed to send email to {$owner['ugr_eMail']} for database {$database}{$newline}";
    }

    $verificationResults[$database] = !empty($databaseResults) ? $databaseResults : 'No Issues Found';
}

if($emailOutput || $newline !== '<br>'){
    echo "{$newline}{$newline}Script done";
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

    <head>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">

        <link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />

        <title>Verify Integrity - All Databases</title>

        <style>
            body{
                margin: 0px;
            }

            h3{
                font-size: 1.1em;
            }
            h2{
    
                font-size: 1.2em;

                margin-block: 0px 1em;
                padding-block: 1em 0px;

                border-top: 1px solid black;
            }
            h1{

                font-size: 1.3em;

                margin-block: 0.9em;
            }

            #filters{

                position: sticky;
                top: 0px;

                background-color: white;

                border-bottom: 1px solid black;

                padding: 1em 0.5em;
            }

            #results{

                padding: 0px 5px 5px 10px;
            }

            #byDatabase{
                margin-left: 5px;
            }
            #scrollToTop{

                position:absolute;
                right: 1em;

                cursor: pointer;

                color: blue;

                text-decoration: underline;
            }
        </style>

        <script>

            let verifyResults = <?php echo json_encode($verificationResults); ?>;
            let actions = <?php echo json_encode($allowedActions); ?>;

            function _fillDatabaseDropdown(){

                let selectDatabase = document.getElementById('byDatabase');

                if(!selectDatabase){
                    return;
                }

                selectDatabase.innerHtml = '';
                selectDatabase.removeEventListener('change', _changeDisplayedDatabase);

                for(const database in verifyResults){

                    let option = document.createElement('option');

                    option.value = database;
                    option.textContent = database;

                    selectDatabase.appendChild(option);
                }

                selectDatabase.addEventListener('change', _changeDisplayedDatabase);

                _changeDisplayedDatabase();
            }

            function _changeDisplayedDatabase(){

                let selectDatabase = document.getElementById('byDatabase');

                if(!selectDatabase){
                    return;
                }

                let selectedDatabase = selectDatabase.value;

                if(!selectedDatabase || !Object.hasOwn(verifyResults, selectedDatabase)){
                    return;
                }

                _updateDisplayedResults(selectedDatabase);
            }

            function _updateDisplayedResults(database){

                if(!Object.hasOwn(verifyResults, database)){
                    return;
                }

                let results = verifyResults[database];

                const dbHeader = document.getElementById('databaseName');
                dbHeader.textContent = database;
                dbHeader.setAttribute('class', '');
                dbHeader.classList.add(database);

                const dbContainer = document.getElementById('databaseResults');
                dbContainer.innerHTML = '';

                if(typeof results === 'string'){
                    dbContainer.textContent = results;
                    return;
                }

                for(const action in results){

                    /*
                    const actionHeaders = document.createElement('h2');
                    actionHeaders.textContent = actions[action] ?? action;
                    actionHeaders.classList.add(action, 'action-header');
                    dbContainer.appendChild(actionHeaders);
                    */

                    const actionResults = document.createElement('div');
                    actionResults.innerHTML = results[action];
                    actionResults.classList.add(`${database}-${action}`, 'action-results');
                    dbContainer.appendChild(actionResults);
                }
            }

            function _setupScrollControls(){

                const scrollToTopLink = document.getElementById('scrollToTop');

                window.onscroll = () => {
                    scrollToTopLink.style.display = window.scrollY > 0 ? 'inline' : 'none';
                };

                scrollToTopLink.addEventListener('click', () => {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', () => {
                _fillDatabaseDropdown();
                _setupScrollControls();
            });
        </script>

    </head>

    <body>

        <div id="filters">
            Showing report for 
            <select id="byDatabase"></select>
            <span id="scrollToTop" style="display: none;">Scroll to top &#8593;</span>
        </div>

        <div id="results">

            <h1 id="databaseName"></h1>

            <div id="databaseResults"></div>

        </div>

    </body>

</html>