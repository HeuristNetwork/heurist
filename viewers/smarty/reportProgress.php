<?php
    require_once(dirname(__FILE__).'/../../hsapi/System.php');

    $res = '';

    if(@$_REQUEST['db'] && @$_REQUEST['session']){
        
        $system = new System();
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
//error_log('repror '.$res);        
        print $res;
    }else{
        print 'done';
    }
?>
