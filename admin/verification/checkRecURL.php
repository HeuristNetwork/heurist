<?php
/*
* Copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* checkRecURL.php - Verifies the validity of URLs stored in Heurist records.
*
* @fileOverview This script iterates through records in a Heurist database and checks the validity
*               of URLs found in predefined fields (typically `rec_URL` and other text fields
*               that might contain URLs). It uses the `DbVerifyURLs` class to perform the checks.
*               The output is an HTML page listing any invalid URLs found.
*               Requires admin privileges.
*
* @package     Heurist academic knowledge management system
* @subpackage  /admin/verification
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
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
        </div>
        <div id="page-inner">
<?php


$isHeuristReferenceIndex = (strcasecmp(HEURIST_DBNAME, HEURIST_INDEX_DATABASE)==0);
$checker = new DbVerifyURLs($system, HEURIST_SERVER_URL, $isHeuristReferenceIndex);
$results = $checker->checkURLs(true, $list_only);

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
