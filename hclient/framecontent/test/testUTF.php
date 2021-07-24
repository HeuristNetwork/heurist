<?php
      require_once (dirname(__FILE__).'/../../../hsapi/System.php');

      $system = new System();
      if($system->init('osmak_9')){

        //header("Access-Control-Allow-Origin: *");
        //header('Content-type: application/json;charset=UTF-8');
          
        $val = mysql__select_value($system->get_mysqli(),'select rty_TitleMask from defRecTypes where rty_ID=100');
        print $val.'<br>';
        
        $val1 = TitleMask::execute($val, 100, 2, null, _ERR_REP_SILENT);
        print 'AB>'.print_r($val1,true).'<br>';
        
        
        //$val = array($val);
        //print '>>>'.json_encode($val);
      }
?>
