<?php
    if($_REQUEST['db']){
        
        $dbname_full = 'hdb_'.$_REQUEST['db'];

        session_start();

        if(@$_REQUEST['terminate']==1){
            $_SESSION[$dbname_full.'.heurist']['smarty_progress2'] = 'terminate';
            $res = 'terminate';
        }else{
//error_log('progress '.@$_COOKIE['heurist-sessionid'].'  '.@$_SESSION[$dbname_full.'.heurist']['user_name']);
            $res = @$_SESSION[$dbname_full.'.heurist']['smarty_progress2'];
        }
//error_log(session_id().' get smarty_progress='.$res);         
        print $res;
    }else{
        print 'done';
    }

?>
