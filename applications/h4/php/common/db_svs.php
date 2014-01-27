<?php
/**
* 
* CRUD for Saved Searches (usrSavedSearches)
* 
* svs - prefix for functions
* 
*/

require_once (dirname(__FILE__).'/../System.php');

/**
* Get all saved searches for given user
*
* @param mixed $system
* @param mixed $ugrID - if not defined it searches all
*/
function svsGetByUser($system, $ugrID=null){

    if (!$ugrID) {
        $ugrID = $system->get_user_id();

        $current_User = $system->getCurrentUser();
        if($current_User && @$current_User['ugr_Groups'] && count(array_keys($current_User['ugr_Groups']))>0 ){
            $ugrID = implode(",", array_keys($current_User['ugr_Groups'])).",".$ugrID;
        }
    }
    if(!$ugrID) {
        $ugrID = 0; //get saved searches for guest
    }


    $mysqli = $system->get_mysqli();

    $query = "SELECT svs_ID, svs_Name, svs_Query, svs_UGrpID FROM usrSavedSearches WHERE svs_UGrpID in (".$ugrID.")";

    $res = $mysqli->query($query);

    if ($res){
        $result = array();
        while ($row = $res->fetch_row()){
            $id = array_shift($row);
            $result[$id] = $row;
        }
        $res->close();
        return $result;
   }else{
        $system->addError(HEURIST_DB_ERROR, 'Can not get saved searches', $mysqli->error);
        return false;
    }
}

/**
* Insert/update saved search
*
* @param mixed $system
* @param mixed $record  - [ svs_ID, svs_UGrpID, svs_Name, svs_Query ]
*/
function svsSave($system, $record){

    if(!@$record['svs_Name']){
        $system->addError(HEURIST_INVALID_REQUEST, "Name not defined");
    }else if(!@$record['svs_Query']){
        $system->addError(HEURIST_INVALID_REQUEST, "Query not defined");
    }else{

        $record['svs_UGrpID'] = $system->is_admin2(@$record['svs_UGrpID']);
        if (!$record['svs_UGrpID']) {
            $system->addError(HEURIST_REQUEST_DENIED);
        }else{

            $res = mysql__insertupdate($system->get_mysqli(), "usrSavedSearches", "svs", $record);
            if(is_numeric($res)>0){
                return $res; //returns affected record id
            }else{
                $system->addError(HEURIST_DB_ERROR, 'Can not update record in database', $res);
            }

        }
    }
    return false;
}

/**
* Delete saved search
*
* @param mixed $system
* @param mixed $rec_ids  - comma separeted list of IDs
*/
function svsDelete($system, $rec_ids, $ugrID=null){

    //verify that current user can delete
    $ugrID = $system->is_admin2($ugrID);
    if (!$ugrID) {
        $system->addError(HEURIST_REQUEST_DENIED);
        return false;
    }else{

        if(is_string($rec_ids)){
            $rec_ids = explode(",", $rec_ids);
        }

        if (is_array($rec_ids) && count($rec_ids)>0) {

            $query = 'delete from usrSavedSearches where svs_ID in ('. join(', ', $rec_ids) .') and svs_UGrpID='.$ugrID;

            $mysqli = $system->get_mysqli();
            $res = $mysqli->query($query);

            if($res){
                $cnt = $mysqli->affected_rows;
                if($cnt>0){
                    return array("status"=>HEURIST_OK, "data"=> $cnt);
                }else{
                    $system->addError(HEURIST_NOT_FOUND);
                    return false;
                }
            }else{
                $system->addError(HEURIST_DB_ERROR,"Can not delete saved search", $mysqli->error );
                return false;
            }

        }else{
            $system->addError(HEURIST_INVALID_REQUEST);
            return false;
        }

    }
}
?>

