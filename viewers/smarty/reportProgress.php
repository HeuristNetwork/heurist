<?php
    require_once dirname(__FILE__).'/../../hserv/System.php';

    $res = '';

    if(@$_REQUEST['db'] && @$_REQUEST['session']){
        
        $system = new System();
        $dbname = @$_REQUEST['db'];
        $error = System::dbname_check($dbname);
        if(!$error){
        
            if(!defined('HEURIST_SCRATCH_DIR')){
                //$system->initPathConstants($dbname);
                $upload_root = $system->getFileStoreRootFolder();
                $filestore_dir = $upload_root . htmlspecialchars($dbname) . '/';
                if(file_exists($filestore_dir)){
                    $dir = $filestore_dir.'scratch/';
                    $warn = folderCreate2($dir, '', false);
                    if($warn==''){
                        define('HEURIST_SCRATCH_DIR', $dir);        
                    }
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
        if($res==null) $res = '';
        print $res;
    }else{
        print 'terminate';
    }
?>
