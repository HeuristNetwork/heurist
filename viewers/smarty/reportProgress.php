<?php
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

    if(@$_REQUEST['db'] && @$_REQUEST['session']){
        
        
        $mysqli = mysqli_connection_overwrite(DATABASE);
        
        if(@$_REQUEST['terminate']==1){
            $res = 'terminate';
            updateProgress($mysqli, $_REQUEST['session'], false, $res);
        }else{
            $res = updateProgress($mysqli, $_REQUEST['session'], false, null);

        }
        
        $mysqli->close();
        
/*        
        $dbname_full = 'hdb_'.$_REQUEST['db'];
        session_start();

        if(@$_REQUEST['terminate']==1){
            $_SESSION[$dbname_full.'.heurist']['smarty_progress2'] = 'terminate';
            $res = 'terminate';
        }else{
            $res = @$_SESSION[$dbname_full.'.heurist']['smarty_progress2'];
        }
 */       
        
        
        print $res;
    }else{
        print 'done';
    }
?>
