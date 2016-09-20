<?php                                                

/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../../configIni.php');
    require_once(dirname(__FILE__).'/../../common/php/dbUtils.php');
    
    /** Db check */
    if(!is_systemadmin) {
        echo "You must be logged in as a system administrator to delete databases";
        exit();
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
                    // "Delete" database
                    $db = $_POST['database']; 
                    db_delete($db); // dbUtils.php
                    
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
