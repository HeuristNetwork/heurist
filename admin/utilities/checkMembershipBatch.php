<?php
/**
* checkMembershipBatch.php - Processes Tickets records (8-23) and 
* sets field "Membership" (1623-1067) with current membership status (1623-7643)
*
* @fileOverview 
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay <blmckay13@gmail.com>
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6
*/

use hserv\System;
use hserv\structure\ConceptCode;

// example:
//  sudo php -f /var/www/html/heurist/admin/utilities/checkMembershipBatch.php -- -db=database_1
//  If dbs are not specified by default  Heurist_Job_Tracker 
// php -f checkMembershipBatch.php

// Default values for arguments
$db_name = 'Heurist_Job_Tracker'; // Database names or paths??

// Check if the script is run from the command line
if (isset($argv)) {
	$ARGV = []; // Stores parsed command-line arguments
	$i = 0;
	
	// Parse command-line arguments
	while ($i < count($argv)) {
		// Handle arguments starting with '-'
		if ($argv[$i][0] === '-') {
			// Check for arguments with values, e.g., -key value
			if (isset($argv[$i + 1]) && $argv[$i + 1][0] !== '-') {
				$ARGV[$argv[$i]] = $argv[$i + 1];
				++$i; // Skip the next value as it's already assigned
			} 
				
			// Handle inline argument formats like -db=value
			elseif (strpos($argv[$i], '-db=') === 0) {
				$ARGV['-db'] = substr($argv[$i], 4);
			}
		} else {
			// Add standalone arguments to the list
			$ARGV[] = $argv[$i];
		}
		++$i;
	}
	
	// Parse the database argument (-db)
	if (isset($ARGV['-db'])) {
		$db_name = $ARGV['-db'];
	}
} else {

	exit('This function is for command line execution');
}

// Define base directory
define('HEURIST_DIR', dirname(__FILE__) . '/../../');

// Import necessary utilities and functions
use hserv\utilities\USystem;

// Include the autoloader to load classes and interfaces if they are currently not defined (by include/require).
require_once HEURIST_DIR . 'autoload.php';

// Establish a connection to the SQL server to retrieve list of databases
$system = new hserv\System();

if (!$system->init($db_name, true, false)) {
	exit("Cannot establish connection to database $db_name\n");
}

// Setup server name
if (!defined('HEURIST_SERVER_NAME') && isset($serverName)) {
	define('HEURIST_SERVER_NAME', $serverName); // 'heurist.huma-num.fr'
}
// Validate server name
if (!defined('HEURIST_SERVER_NAME') || empty(HEURIST_SERVER_NAME)) {
	exit("The script was unable to determine the server's name, please define it within heuristConfigIni.php then re-run this script.\n");
}
echo "checkMembershipBatch: check db $db_name on ".HEURIST_SERVER_NAME."\n";
	
//1. Find all "Ticket" records (8-23) and field "Reporter" (1317-242) order by reporter
$rty_ID = ConceptCode::getRecTypeLocalID('8-23');
$dty_ID_reporter = ConceptCode::getDetailTypeLocalID('1317-242');
$dty_ID_membershipStatus = ConceptCode::getDetailTypeLocalID('1623-1067');
$trm_ID_member = ConceptCode::getTermLocalID('1623-7643');

if($rty_ID==null){
    exit("checkMembershipBatch: Ticket record type (8-23) not defined in database $db_name\n");
}
if($dty_ID_reporter==null){
    exit("checkMembershipBatch: Reporter field (1623-1067) not defined in database $db_name\n");
}
if($dty_ID_membershipStatus==null){
    exit("checkMembershipBatch: Membership status field (1623-1067) not defined in database $db_name\n");
}
if($trm_ID_member==null){
    exit("checkMembershipBatch: Membership term (1623-7643) not defined in database $db_name\n");
}
$rty_ID = (int)$rty_ID;
$dty_ID_reporter = (int)$dty_ID_reporter;
$dty_ID_membershipStatus = (int)$dty_ID_membershipStatus;
$trm_ID_member = (int)$trm_ID_member;

$query = 'SELECT dtl_RecID, dtl_Value FROM recDetails, Records WHERE dtl_RecID=rec_ID AND rec_RecTypeID='.$rty_ID
    .' and dtl_DetailTypeID='.$dty_ID_reporter.' ORDER BY dtl_Value';

$mysqli = $system->getMysqli();
$res = $mysqli->query($query);    
if (!$res){
    exit('checkMembershipBatch: database error - ' .$mysqli->error. "\n");
}    

$email = null;
$rec_IDs = [];
$cnt_members = 0;
$cnt_non_members = 0;
$cnt_members_tickets = 0;
$cnt_non_members_tickets = 0;
while ( true ){ 
    $row = $res->fetch_row();
    if(!$row || $email!=$row[1]){

        if($email){
            //2. Find membership status for reporter
            $status = checkHeuristNetworkMembership('', $email);
            
            //3. Update all Tickets for this reporter. Set field "Membership" (1623-1067) 
            // Membership status (1623-7643), other remove this field for nonmember
            $query = 'DELETE FROM recDetails WHERE dtl_DetailTypeID='.$dty_ID_membershipStatus
                .' AND dtl_RecID IN ('.implode(',', $rec_IDs).')';
                
            $res2 = $mysqli->query($query);
            if(!$res2){
                exit("checkMembershipBatch: database error - " .$mysqli->error. "\n");
            }
            if ($status!='' && $status!='nonmember'){
                foreach($rec_IDs as $id){
                    $query = "INSERT INTO recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) VALUES ($id,$dty_ID_membershipStatus,$trm_ID_member)";
                    $res2 = $mysqli->query($query);
                    if(!$res2){
                        exit("checkMembershipBatch: database error - " .$mysqli->error. "\n");
                    }
                }
                $cnt_members++;
                $cnt_members_tickets = $cnt_members_tickets + count($rec_IDs);
            }else{
                $cnt_non_members++;
                $cnt_non_members_tickets = $cnt_non_members_tickets + count($rec_IDs);
            }
            
        }elseif(!$row){
            break;
        }
        
        
        $email = $row[1];
        $rec_IDs = [];
    }
    array_push($rec_IDs, (int)$row[0] );
}
$res->close();
echo "checkMembershipBatch:\n $cnt_members_tickets tickets for $cnt_members members \n $cnt_non_members_tickets tickets for $cnt_non_members non-members \n";

    
