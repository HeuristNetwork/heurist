<?php
    require_once (dirname(__FILE__).'/../System.php');
    
    $system = new System();
    if( ! $system->init(@$_REQUEST['db']) ){
        //get error and response
        print $system->getError();
        return;
    }
    
    
    if(!$system->is_admin()){ //  $system->is_dbowner()
        print '<span>You must be logged in as Database Administrator to perform this operation</span>';
    }

    include(dirname(__FILE__).'/../utilities/utils_db_load_script.php'); // used to execute SQL script

    $isok = true;
    
    $query = "drop table IF EXISTS recLinks";

    if (!$system->get_mysqli()->query($query)) {
        print "Cannot drop table cache table: " . $mysqli->error;
        return;
    }
                
    if(!db_script(HEURIST_DBNAME_FULL, dirname(__FILE__)."/../dbaccess/sqlCreateRecLinks.sql")){
            $system->addError(HEURIST_DB_ERROR, "Cannot execute script sqlCreateRecLinks.sql");
            $response = $system->getError();
            $isok = false;
    }
    
    if($isok){
        print 'Success';
    }else{
        print $response['message']; //print_r($response, true);
    }
?>
