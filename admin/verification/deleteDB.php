<?php                                                
    require_once(dirname(__FILE__).'/../../common/php/dbUtils.php');
    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../configIni.php');
    
    /** Password check */
    if(isset($_POST['password'])) {
        $pw = $_POST['password'];
        
        // Password in configIni.php must be at least 6 characters
        if(strlen($passwordForDatabaseDeletion) > 6) {
            $comparison = strcmp($pw, $passwordForDatabaseDeletion);
            if($comparison == 0) {
                // Correct password, check if db is set
                if(isset($_POST['dbname'])) {
                    $db = $_POST['dbname'];
                    echo db_dump($db);  
                }else{
                    // Authorization call
                    header('HTTP/1.0 200 OK');
                    echo "Correct password";    
                }     
            }else{
                // Invalid password
                header('HTTP/1.0 401 Unauthorized');
                echo "Invalid password";
            }    
        }else{
            header('HTTP/1.0 406 Not Acceptable');
            echo "Password in configIni.php must be at least 6 characters";  
        }
        exit(); 
    }
    
    echo "Invalid request";
?>
