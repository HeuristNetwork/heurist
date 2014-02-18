<?php
/**
* Library to search records
* 
* recordSearchMinMax - Find minimal and maximal values for given detail type and record type
* recordSearch
*/

require_once (dirname(__FILE__).'/db_users.php');
require_once (dirname(__FILE__).'/db_files.php');
require_once (dirname(__FILE__).'/compose_sql.php');
require_once (dirname(__FILE__).'/db_structure.php');

/**
* Find minimal and maximal values for given detail type and record type
* 
* @param mixed $system
* @param mixed $params - array  rt - record type, dt - detail type
*/
function recordSearchMinMax($system, $params){

    if(@$params['rt'] && @$params['dt']){

        $mysqli = $system->get_mysqli();
        $currentUser = $system->getCurrentUser();

        $query = "select min(cast(dtl_Value as decimal)) as min, max(cast(dtl_Value as decimal)) as max from Records, recDetails where rec_ID=dtl_RecID and rec_RecTypeID="
                .$params['rt']." and dtl_DetailTypeID=".$params['dt'];

        //@todo - current user constraints

        $res = $mysqli->query($query);
        if (!$res){
            $response = $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
        }else{
            $row = $res->fetch_assoc();
            if($row){
                $response = array("status"=>HEURIST_OK, "data"=> $row);
            }else{
                $response = array("status"=>HEURIST_NOT_FOUND);
            }
            $res->close();
        }

    }else{
        $response = $system->addError(HEURIST_INVALID_REQUEST);
    }

   return $response;
}

/**
* put your comment there...
*
* @param mixed $system
* @param mixed $params
* @param mixed $need_structure
* @param mixed $need_details
*/
function recordSearch($system, $params, $need_structure, $need_details)
{
    $mysqli = $system->get_mysqli();
    $currentUser = $system->getCurrentUser();

    if ( $system->get_user_id()<1 ) {
        //$currentUser['ugr_Groups'] = user_getWorkgroups( $mysqli, $currentUser['ugr_ID'] );
        $params['w'] = 'all'; //does not allow to search bookmarks if not logged in
    }

    if(@$params['q'] && !is_string($params['q'])){
        return $system->addError(HEURIST_INVALID_REQUEST, "Invalid search request");
    }


    $query = 'select SQL_CALC_FOUND_ROWS '
            .'bkm_ID,'
            .'bkm_UGrpID,'
            .'rec_ID,'
            .'rec_URL,'
            .'rec_RecTypeID,'
            .'rec_Title,'
            .'rec_OwnerUGrpID,'
            .'rec_NonOwnerVisibility ';
            /*.'rec_URLLastVerified,'
            .'rec_URLErrorMessage,'
            .'bkm_PwdReminder ';*/

    $query = compose_sql_query($query, $params, $currentUser);

//DEGUG print error_log("AAA ".$query);

    $res = $mysqli->query($query);
    if (!$res){
        $response = $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
    }else{

        $fres = $mysqli->query('select found_rows()');
        if (!$fres)     {
            $response = $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
        }else{

            $num_rows = $fres->fetch_row();
            $num_rows = $num_rows[0];
            $fres->close();


            $_flds =  $res->fetch_fields();
            $fields = array();
            foreach($_flds as $fld){
                array_push($fields, $fld->name);
            }
            array_push($fields, 'rec_ThumbnailURL'); //last one

            $records = array();
            $rectype_structures  = array();
            $rectypes = array();

            while ( ($row = $res->fetch_row()) && (count($records)<1001) ) {
                
                array_push( $row, fileGetThumbnailURL($system, $row[2]) );
                
                $records[$row[2]] = $row;
                array_push($rectypes, $row[4]);
            }
            $res->close();

            $rectypes = array_unique($rectypes);

            if($need_details){
               //search for specific details
               // @todo - we may use getAllRecordDetails
               $res = $mysqli->query(
                    "select dtl_RecID,
                            dtl_DetailTypeID,
                            dtl_Value,
                            astext(dtl_Geo),
                            dtl_UploadedFileID
                            from recDetails where dtl_RecID in (" . join(",", array_keys($records)) . ")");
               if (!$res){
                    $response = $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
               }else{
                    while ($row = $res->fetch_row()) {
                           $recID = array_shift($row);
                           if( !array_key_exists("d", $records[$recID]) ){
                               $records[$recID]["d"] = array();
                           }
                           $dtyID = $row[0];
                           if( !array_key_exists($dtyID, $records[$recID]["d"]) ){
                                $records[$recID]["d"][$dtyID] = array();
                           }

                           if($row[2]){
                               $val = $row[1]." ".$row[2]; //for geo
                           }else if($row[3]){
                               $val = $row[3];
                           }else{
                               $val = $row[1];
                           }
                           array_push($records[$recID]["d"][$dtyID], $val);
                    }

                    if($need_structure){
                         //description of recordtype and used detail types
                         $rectype_structures = dbs_GetRectypeStructures($system, $rectypes, 1); //no groups
                    }
               }
            }

            //"query"=>$query,
            $response = array("status"=>HEURIST_OK,
                "data"=> array(
                        //"query"=>$query,
                        "count"=>$num_rows,
                        "offset"=> get_offset($params),
                        "fields"=>$fields,
                        "records"=>$records,
                        "rectypes"=>$rectypes,
                        "structures"=>$rectype_structures));
        }

    }

    return $response;

}
?>
