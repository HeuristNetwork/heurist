<?php


/**
* checkRecURL.php - Verifies the validity of URLs stored in Heurist records.
*
* @fileOverview This script iterates through records in a Heurist database and checks the validity
*               of URLs found in predefined fields (typically `rec_URL` and other text fields
*               that might contain URLs). It uses the `DbVerifyURLs` class to perform the checks.
*               The output is an HTML page listing any invalid URLs found.
*               Requires admin privileges.
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       3.1.0
*/

set_time_limit(0);

define('PDIR','../../');

use hserv\utilities\DbVerifyURLs;

require_once dirname(__FILE__).'/../../autoload.php';

$list_only = (@$_REQUEST['list']==1);

$system = new hserv\System();
if( ! $system->init(@$_REQUEST['db']) ){
    //get error and response
    print $system->getErrorMsg();
    return;
}
if(!$system->isAdmin()){ //  $system->isDbOwner()
    print '<span>You must be logged in as Database Administrator to perform this operation</span>';
}

// -----------------------------------------------------------------------------
// TSV download of the most recent results (stored in DB setting "Invalid URLs").
// Usage: checkRecURL.php?db=DBNAME&download=1
// -----------------------------------------------------------------------------
if(@$_REQUEST['download']==1){

    $results = $system->settings->getDatabaseSetting('Invalid URLs');
    if(!$results || !is_array($results)){
        header('Content-Type: text/plain; charset=utf-8');
        print "No saved results found. Run the check first.\n";
        exit;
    }

    $db = $system->dbname();
    $fname = 'invalid_urls_'.$db.'_'.date('Ymd_His').'.tsv';

    header('Content-Type: text/tab-separated-values; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$fname.'"');

    // TSV header
    $cols = ["scope","rec_ID","detailTypeID","href","link_text","resolved_url","error"];
    print implode("\t",$cols)."\n";

    // Records.rec_URL
    if(isset($results['record']) && is_array($results['record'])){
        foreach($results['record'] as $recId=>$href){
            $resolved = $results['record_resolved'][$recId] ?? $href;
            $err = $results['record_error'][$recId] ?? 'Invalid';
            $text = $results['record_text'][$recId] ?? $href;
            $row = ["record", $recId, "", $href, $text, $resolved, $err];
            $row = array_map(function($v){ $v=strval($v); $v=str_replace(["\t","\r","\n"]," ",$v); return $v; }, $row);
            print implode("\t",$row)."\n";
        }
    }

    // Field-based (text / file)
    foreach(['text','file'] as $scope){
        if(isset($results[$scope]) && is_array($results[$scope])){
            foreach($results[$scope] as $recId=>$fields){
                foreach($fields as $dty=>$hrefs){
                    foreach($hrefs as $href){
                        $text = $results[$scope.'_text'][$recId][$dty][$href] ?? $href;
                        $resolved = $results[$scope.'_resolved'][$recId][$dty][$href] ?? $href;
                        $err = $results[$scope.'_error'][$recId][$dty][$href] ?? 'Invalid';
                        $row = [$scope, $recId, $dty, $href, $text, $resolved, $err];
                        $row = array_map(function($v){ $v=strval($v); $v=str_replace(["\t","\r","\n"]," ",$v); return $v; }, $row);
                        print implode("\t",$row)."\n";
                    }
                }
            }
        }
    }

    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
        <title>Check Records URL</title>
    </head>
    <body class="popup">
        <div class="banner">
            <h3>Check Records URL</h3>
<p><a href="?db=<?php echo htmlentities(@$_REQUEST['db']); ?>&download=1">Download TSV of latest results</a></p>
<p style="color:#666">Use <code>&amp;max=0</code> to scan the whole database (default), or set a positive limit for quicker testing.</p>
        </div>
        <div id="page-inner">
<?php


$isHeuristReferenceIndex = (strcasecmp($system->dbname(), HEURIST_INDEX_DATABASE)==0);
$checker = new DbVerifyURLs($system, HEURIST_SERVER_URL, $isHeuristReferenceIndex);
$maxCount = isset($_REQUEST['max']) ? intval($_REQUEST['max']) : 0; // 0 = whole database
$results = $checker->checkURLs(true, $list_only, $maxCount);

/* heurist instances   THIS IS NOT A COMPREHENSIVE LSIT NOR MAINTAINED
$heurist_instances = array(
 'https://heuristref.net',
 'https://heurist.huma-num.fr',
 'https://heurist.sfb1288.uni-bielefeld.de',
 'https://heurisko.io',
 'https://heurist.eie.gr',
 'https://ship.lub.lu.se',
 'https://heurist.fdm.uni-hamburg.de',
 'http://fedora.gwin.gwiss.uni-hamburg.de',
 'https://pfcmati.bnf.fr',
 'https://heurist.researchsoftware.unimelb.edu.au',
 'https://heurist.unige.ch',
 'https://dcsrs-test-ssp.ad.unil.ch',
 );
*/
?>
</div></body></html>
