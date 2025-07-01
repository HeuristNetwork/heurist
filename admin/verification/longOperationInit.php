<?php
/**
* longOperationInit.php - Wrapper for initiating long-running verification/rebuild operations.
*
* @fileOverview This script acts as an iframe wrapper to display a "waiting" message
*               while another, potentially long-running, script executes. It is used to
*               initiate operations such as:
*               - `listUploadedFilesErrors.php`
*               - `rebuildRecordTitles.php`
*               - `rebuildCalculatedFields.php`
*               - `checkRecURL.php`
*               - `rebuildEntryMasks.php`
*               It takes a `type` parameter to determine which script to load into the iframe
*               and passes along `db` and optional `recTypeIDs` parameters.
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.1.0
*/

set_time_limit(0);

$recTypeIDs = (@$_REQUEST['recTypeIDs']!=null)?htmlspecialchars($_REQUEST['recTypeIDs']):null;
$dbname = htmlspecialchars($_REQUEST['db']);

if(@$_REQUEST['type']=='titles'){
    if($recTypeIDs){
        $srcURL = 'rebuildRecordTitles.php?recTypeIDs='.$recTypeIDs.'&db='.$dbname;
    }else{
        $srcURL = 'rebuildRecordTitles.php?db='.$dbname;
    }
    $sTitle = 'Recalculation of composite record titles';

}elseif(@$_REQUEST['type']=='calcfields'){
    if($recTypeIDs){
        $srcURL = 'rebuildCalculatedFields.php?recTypeIDs='.$recTypeIDs.'&db='.$dbname;
    }else{
        $srcURL = 'rebuildCalculatedFields.php?db='.$dbname;
    }
    $sTitle = 'Recalculation of calculated fields';

}elseif(@$_REQUEST['type']=='files'){
    $srcURL = 'listUploadedFilesErrors.php?db='.$dbname;
    $sTitle = 'Verifying files';
}elseif(@$_REQUEST['type']=='urls'){
    $srcURL = 'checkRecURL.php?db='.$dbname;
    $sTitle = 'Check Records URL';
}elseif(@$_REQUEST['type']=='entrymask'){

    $srcURL = "rebuildEntryMasks.php?&db={$dbname}" . ($recTypeIDs ? "&recTypeIDs={$recTypeIDs}" : '');
    $sTitle = 'Re-apply Entry Masks';

}else{
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title><?php echo $sTitle; ?></title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <link rel="stylesheet" type="text/css" href="../../h4styles.css">

        <script type="text/javascript">

            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    var dosframe = document.getElementById('verification_output');

                    dosframe.addEventListener('load', function() {
                        dosframe.style.width = '97%';
                        dosframe.style.height = '97%';
                        dosframe.style.display = 'block';

                        var inProgress = document.getElementById('in_porgress');
                        if (inProgress) {
                            inProgress.style.display = 'none';
                        }
                    });

                    dosframe.src = "<?php echo $srcURL; ?>";
                }, 500);
            });

        </script>
        <style>
        div#in_porgress{
            background-color:#FFF;
            background-image: url(../../hclient/assets/loading-animation-white.gif);
            background-repeat: no-repeat;
            background-position:50%;
            cursor: wait;
            width:100%;
            height:100%;
            min-height:250px;
        }
        </style>
    </head>
    <body class="popup" style="overflow:hidden">
        <div id='in_porgress'><h2><?php echo $sTitle; ?>. This may take up to a few minutes for large databases...</h2></div>
        <iframe  title="Verification Output" id="verification_output" style="display:none;border:none;width:1;height:1;position:absolute;">
        </iframe>
    </body>
</html>
