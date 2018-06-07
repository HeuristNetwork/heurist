<?php
    require_once(dirname(__FILE__).'/../../hserver/System.php');

    $res = '';

    if(@$_REQUEST['db'] && @$_REQUEST['session']){
        
        $system = new System();
        if($system->init($_REQUEST['db'], true, false)){
        
            $mysqli = $system->get_mysqli();
            
            if(@$_REQUEST['terminate']==1){
                $res = 'terminate';
                mysql__update_progress($mysqli, $_REQUEST['session'], false, $res);
            }else{
                $res = mysql__update_progress($mysqli, $_REQUEST['session'], false, null);

            }
            
            $mysqli->close();
        }
        
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
