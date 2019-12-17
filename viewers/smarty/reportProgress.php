<?php
    require_once(dirname(__FILE__).'/../../hsapi/System.php');

    $res = '';

    if(@$_REQUEST['db'] && @$_REQUEST['session']){
        
        $system = new System();
        
        if(!defined('HEURIST_SCRATCH_DIR')){
            //$system->initPathConstants($_REQUEST['db']);
            $upload_root = $system->getFileStoreRootFolder();
            $filestore_dir = $upload_root . $_REQUEST['db'] . '/';
            if(file_exists($filestore_dir)){
                $dir = $filestore_dir.'scratch/';
                $warn = folderCreate2($dir, '', false);
                if($warn==''){
                    define('HEURIST_SCRATCH_DIR', $dir);        
                }
            }
        }
        if(@$_REQUEST['terminate']==1){
            $res = 'terminate';
            mysql__update_progress($mysqli, $_REQUEST['session'], false, $res);
        }else{
            //retrieve current status
            $res = mysql__update_progress($mysqli, $_REQUEST['session'], false, null);
        }
        

/*        
        if($system->init($_REQUEST['db'], true, false)){
        
            $mysqli = $system->get_mysqli();
            
            if(@$_REQUEST['terminate']==1){
                $res = 'terminate';
                mysql__update_progress($mysqli, $_REQUEST['session'], false, $res);
            }else{
                //retrieve current status
                $res = mysql__update_progress($mysqli, $_REQUEST['session'], false, null);
            }
            
            $mysqli->close();
        }
error_log('repror '.$_REQUEST['session'].'   '.$res);        
*/        
        print $res;
    }else{
        print 'done';
    }
?>
