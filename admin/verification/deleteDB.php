<?php                                                
    require_once(dirname(__FILE__).'/../../common/php/dbUtils.php');
    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../configIni.php');
    
    /** Zips the $db directory */
    function zip_directory($db) {
        $result = "";
        $input = "/heur-filestore/".$db;
        $zip_file = "/DELETED_DATABASES/".$db."_".time().".zip"; 

        if ($handle = opendir($input)) {
            $zip = new ZipArchive();

            if ($zip->open($zip_file, ZIPARCHIVE::CREATE)!==TRUE) 
            {
                echo "Unable to open/create zip file ".$zip_file;
                return false;
            }
            echo "<br/>Zip file has been created at ".$zip_file;
             
            // Recursively add all files to ZIP
            while (false !== ($file = readdir($handle))) 
            {
                $zip->addFile($input."/".$file);
            }
            closedir($handle);
            
            // Echo result
            $size = filesize($zip_file) / pow(1024, 2);
            echo "<br/>The zip file contains ".$zip->numFiles." files and is ".sprintf("%.2f", $size)."MB";

            $zip->close();
            return true;
        }else{
            echo "Unable to open directory ".$input;
            return false;
        }
    }
    
    /** Password check */
    if(isset($_POST['password'])) {
        $pw = $_POST['password'];
        
        // Password in configIni.php must be at least 6 characters
        if(strlen($passwordForDatabaseDeletion) > 6) {
            $comparison = strcmp($pw, $passwordForDatabaseDeletion);  // Check password
            if($comparison == 0) {
                // Correct password, check if db is set
                if(isset($_POST['database'])) {
                    $db = $_POST['database']; 
                    
                    // Dump database to .sql file
                    if(db_dump($db)) { // dbUtils.php
                        // Zip directory
                        zip_directory($db);
                    }
                    
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
