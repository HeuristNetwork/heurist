<?php
    require_once(dirname(__FILE__).'/../../hsapi/System.php');

    $res = '';

    if(@$_REQUEST['db'] && @$_REQUEST['session']){
        
        $system = new System();
        $dbname = @$_REQUEST['db'];
        $error = System::dbname_check($dbname);
        if(!$error){
        
            if(!defined('HEURIST_SCRATCH_DIR')){
                //$system->initPathConstants($dbname);
                $upload_root = $system->getFileStoreRootFolder();
                $filestore_dir = $upload_root . $dbname . '/';
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
                mysql__update_progress($mysqli, $_REQUEST['session'], false, $res);
            }else{
                //retrieve current status
                $res = mysql__update_progress($mysqli, $_REQUEST['session'], false, null);
            }
        }
        if($res==null) $res = '';
        

/*        
        if($system->init($dbname, true, false)){
        
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
        print 'terminate';
    }
?>
