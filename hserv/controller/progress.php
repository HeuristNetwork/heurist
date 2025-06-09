<?php
/**
* progres.php - Handles progress updates and termination for background processes
*
* This script is used to get the current progress of a long-running task
* or to signal a task to terminate. Progress is typically stored in
* a temporary location (e.g., scratch directory).
*
* Parameters:
* - db: The database name.
* - session: The session ID for the progress tracking.
* - terminate: (Optional) If set to 1, signals the process to terminate.
*
* @package     Heurist academic knowledge management system
* @subpackage  controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       6.6
*/
    require_once dirname(__FILE__).'/../../autoload.php';

    $res = '';

    if(@$_REQUEST['db'] && @$_REQUEST['session']){

        $system = new hserv\System();
        $dbname = @$_REQUEST['db'];
        $error = mysql__check_dbname($dbname);
        if($error==null){

            [,$dbname] = mysql__get_names($dbname);

            if(!defined('HEURIST_SCRATCH_DIR')){
                $warn = '';
                $dir = $system->getSysDir(DIR_SCRATCH, basename($dbname));
                if(!file_exists($dir)){
                    $warn = folderCreate2($dir, '', false);
                }
                if($warn==''){
                    define('HEURIST_SCRATCH_DIR', $dir);
                }
            }
            $mysqli = null;

            //keep progress value in HEURIST_SCRATCH_DIR
            if(@$_REQUEST['terminate']==1){
                $res = 'terminate';
                mysql__update_progress($mysqli, intval($_REQUEST['session']), false, $res);
            }else{
                //retrieve current status
                $res = mysql__update_progress($mysqli, intval($_REQUEST['session']), false, null);
            }
        }
        if($res==null) {$res = '';}
        print $res;
    }else{
        print 'terminate';
    }
?>
