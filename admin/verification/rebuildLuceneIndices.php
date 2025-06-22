<?php
/**
* rebuildLuceneIndices.php - Rebuilds all Lucene (Elasticsearch) indices for the current database.
*
* @fileOverview This script triggers a full rebuild of the Elasticsearch indices
*               for the currently selected Heurist database. This is typically used
*               when the search index needs to be refreshed due to schema changes,
*               data corruption, or other administrative reasons.
*               It is accessed from the Heurist admin menu.
*
* @package     Heurist academic knowledge management system
* @subpackage  /admin/verification
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Jan Jaap de Groot    <jjedegroot@gmail.com> 
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

    define('PDIR','../../');//need for proper path to js and css

    require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
    require_once dirname(__FILE__).'/../../hserv/records/indexing/elasticSearch.php';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <title>Check Invalid Characters</title>
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>

    <body class="popup">
        <div class="banner"><h2>Rebuilding Lucene indices for all tables</h2></div>
        <div id="page-inner" style="overflow:auto;padding-left: 6px;">
            <div>
                This function rebuilds lucene indices
                <br>&nbsp;<hr>
            </div>
<?php

    $code = ElasticSearch::buildAllIndices(HEURIST_DBNAME);
    if ($code ==0) {
        print '<div>Database indices have been rebuilt, please check for errors above</div>';
    } else {
        print '<div class="ui-state-error">Failed to rebuild indices, please '.CONTACT_HEURIST_TEAM.' (error code: '.$code.')</div>';
    }
?>
    </body>
</html>
