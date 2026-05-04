<?php
/**
 * viewRecord.php - UI wrapper for viewing a Heurist record, typically within an iframe.
 *
 * @fileOverview This script acts as a simple HTML wrapper that embeds `renderRecordData.php`
 * within an iframe. It handles initial record ID or bookmark ID validation,
 * permission checks, and then delegates the actual rendering of record details
 * to `renderRecordData.php`. It is often used when displaying a record in a popup
 * or an embedded context.
 * @project     Heurist academic knowledge management system
 * @package  Viewers\Record
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Tom Murtagh
 * @author      Kim Jackson
 * @author      Stephen White
 * @author      Ian Johnson ian.johnson.heurist@gmail.com
 * @since       3.0.0
 */
require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../hserv/records/search/recordSearch.php';

$system = new hserv\System();

global $globalMessage;

if(!defined('ERROR_INCLUDE')){
    define('ERROR_INCLUDE', dirname(__FILE__).'/../../hclient/framecontent/infoPage.php');
}

define('ERROR_INCLUDE2', dirname(__FILE__).'/../../hclient/framecontent/infoMessage.php');

if(!$system->init(@$_REQUEST['db'])){
    $globalMessage = $system->getErrorMsg();
    include_once ERROR_INCLUDE2;
    exit;
}

$mysqli = $system->getMysqli();

$rec_id = 0;

$bkm_ID = intval($_REQUEST['bkmk_id']);
if ($bkm_ID>0) {  //find record by bookmark id
    $rec_id = mysql__select_value($mysqli, 'select * from usrBookmarks where bkm_ID = ' . $bkm_ID);
    if(!($rec_id>0)){
        $globalMessage = 'Can\'t find record by bookmark ID';
        include_once ERROR_INCLUDE2;
        exit;
    }
} else {
    $rec_id = intval(@$_REQUEST['recID']);
    if(!($rec_id>0)){
        $globalMessage = 'Parameter recID not defined';
        include_once ERROR_INCLUDE2;
        exit;
    }
}

// check if this record has been replaced (merged)
$rec_id = recordSearchReplacement($mysqli, $rec_id, 0);

//validate permissions
$rec = mysql__select_row_assoc($mysqli,
        'select rec_Title, rec_NonOwnerVisibility, rec_OwnerUGrpID from Records where rec_ID='.$rec_id);

if($rec==null){
    $globalMessage = 'Record #'.$rec_id.' not found';
    include_once ERROR_INCLUDE2;
    exit;
}

$hasAccess = ($rec['rec_NonOwnerVisibility'] == 'public' ||
    ($system->getUserId()>0 && $rec['rec_NonOwnerVisibility'] !== 'hidden') ||    //visible for logged
    $system->isMember($rec['rec_OwnerUGrpID']) );//owner

if(!$hasAccess){
        $globalMessage = 'You are not a member of the workgroup that owns the Heurist record #'
        .$rec_id.', and cannot therefore view or edit this information.';
        include_once ERROR_INCLUDE2;
        exit;
}

//find bookmark by rec id
if(!($bkm_ID>0) && $system->getUserId()>0 ){ //logged in
    $bkm_ID = mysql__select_value($mysqli, 'select bkm_ID from usrBookmarks where bkm_recID = ' . $rec_id
            . ' and bkm_UGrpID = ' . $system->getUserId());
}


$noclutter = array_key_exists('noclutter', $_REQUEST)? '&noclutter' : '';
$hideImages = array_key_exists('hideImages', $_REQUEST) ? '&hideImages='.intval($_REQUEST['hideImages']) : '';
$hideImages = '&privateDetails=' . (array_key_exists('privateDetails', $_REQUEST) ? intval($_REQUEST['privateDetails']) : 1);

$rec_title = $rec['rec_Title'];

$record_renderer_url = HEURIST_BASE_URL.'viewers/record/renderRecordData.php?db='
        .$system->dbname().'&'.($bkm_ID>0 ? ('bkmk_id='.intval($bkm_ID)) : ('recID='.intval($rec_id)))
        .$noclutter
        .$hideImages;

if(!@$_REQUEST['popup']){
    redirectURL($record_renderer_url);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>HEURIST - View record</title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="robots" content="noindex,nofollow">
    
    <link rel="icon" href="<?=HEURIST_BASE_URL?>favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="<?=HEURIST_BASE_URL?>favicon.ico" type="image/x-icon">

    <link rel="stylesheet" type="text/css" href="<?=HEURIST_BASE_URL?>h4styles.css">
</head>

<body style="margin: 0px;<?php if (@$_REQUEST['popup']) { ?>width: 480px; height: 600px; background-color: transparent;<?php } ?>" class="popup">
    <div>
    <!--<h3><?= htmlspecialchars($rec_title) ?></h3>-->
    <iframe title="viewer" name="viewer" frameborder="0" style="width: 100%;height: 100%;" src="<?php echo $record_renderer_url;?>"></iframe>
    </div>
</body>
</html>
