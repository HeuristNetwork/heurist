<?php
/**
* findOrphanedFileFolder.php - Identifies filestore directories that do not have a corresponding database.
*
* @fileOverview This script scans the Heurist filestore root directory and compares the found
*               subdirectories (potential database filestores) against the list of actual
*               databases existing in the MySQL server. Any subdirectory in the filestore
*               that does not have a matching database is considered orphaned.
*               The script outputs an HTML page listing these orphaned folders and provides
*               checkboxes to select them for removal. If run with `?mail=1`, it can
*               email a report of orphaned folders to the admin.
*               Requires owner-level access and an admin password.
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.1
*/

ini_set('max_execution_time', '0');

define('ADMIN_PWD_REQUIRED',1);
define('OWNER_REQUIRED',1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

if(!@$_REQUEST['mail']){
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>List of orphaned file folders. Ie without databases</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>
    <body class="popup">

        <script>window.history.pushState({}, '', '<?php echo htmlspecialchars($_SERVER['PHP_SELF']);?>')</script>
        <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px">
        <form name="actionForm">
<?php
}else{
    $_REQUEST['dbs'] = null; //reset - only report
}

    $mysqli = $system->getMysqli();
    //1. find all database
    $databases = mysql__getdatabases4($mysqli, false);

    $root = $system->getFileStoreRootFolder();

    //get all subfolder in HEURIST_FILESTORE
    $folders = folderSubs($root, ['AAA_LOGS','_PURGES_IMPORTS', '_PURGES_SYSARCHIVE', '_DELETED_DATABASES', '_BATCH_PROCESS_ARCHIVE_PACKAGE', '_DB_STATS', '_ALL_SERVER_STATS'], false);

    $rep = '';
    $orphaned = array();

    //loop folders - check that database exists
    foreach ($folders as $db_name){

        $dbname = mysql__select_value($mysqli,
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \''
                .$mysqli->real_escape_string(HEURIST_DB_PREFIX.$db_name).'\'');
        if(!$dbname){
            $orphaned[] =$db_name;
        }
    }
    $cnt = 0;
    $cnt_del = 0;
    if(!empty($orphaned)){

        $to_delete = null;
        if(@$_REQUEST['dbs']){
            $to_delete = $_REQUEST['dbs'];
            if(isEmptyArray($to_delete)){
                $to_delete = null;
            }

        }

        $rep.='<h3>ORPHANED FOLDERS in HEURIST_FILESTORE_DIR</h3>';

        foreach ($orphaned as $db_name){
            $res = false;
            if(is_array($to_delete) && in_array($db_name, $to_delete)){
                //remove folder
                $res = folderDelete2($root.$db_name, true);
                if($res){
                    $cnt_del++;
                }
            }else{
                $cnt++;
            }
            $db_name = htmlspecialchars($db_name);
            if($res){
                $rep .= '<label>REMOVED: '.$db_name.'</label><br>';
            }else{
                $rep .= '<label><input name="dbs[]" type="checkbox" value="'.$db_name.'">'.$db_name.'</label><br>';
            }

        }
        $rep.='<hr>';
    }

    if(@$_REQUEST['mail']){
        if($cnt>0){
            $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, 'List of orphaned file folders (ie without databases)',
                                            $rep, true);
        }
    }else{
        if($cnt>0 || $cnt_del>0){
            print $rep;
        }
        if($cnt>0){
            print '<br><button type="submit" class="ui-button-action">Remove selected folders</button></form>';
        }else{
            print 'No orphaned folders detected';
        }
        print '</div></body></html>';

    }
?>