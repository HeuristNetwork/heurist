<?php

/**
*
* dh_popup.php (Digital Harlem): Content for map bubble and info popup
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

    require_once (dirname(__FILE__).'/../../../hsapi/System.php');
    require_once (dirname(__FILE__).'/../../../hsapi/dbaccess/db_recsearch.php');
    require_once (dirname(__FILE__).'/../../../hsapi/dbaccess/db_structure.php');
    require_once (dirname(__FILE__).'/../../../hsapi/dbaccess/utils_db.php');

    require_once ( dirname(__FILE__).'/../../../hsapi/utilities/Temporal.php');
    
    
    $response = array();

    $system = new System();
    if( ! $system->init(@$_REQUEST['db']) ){
        //get error and response
        echo $system->getError();
        return;
    }else if(!@$_REQUEST['recID']){
        echo 'Record ID is not defined';
        return;
    }
    
    $system->defineConstants();

    //echo '>1>'.@$_REQUEST['recID'];

    $recID = $_REQUEST['recID'];
    $ids = explode(',',$recID);
    $need_cnt = count($ids);
    
    $entities_per_address = $need_cnt;
    if($entities_per_address>1){
        $recID = end($ids);
    }
    
    
    $eventID = @$_REQUEST['eventID'];
    if($eventID) { array_push($ids, $eventID); $need_cnt++;}
    $addrID = @$_REQUEST['addrID'];
    if($addrID) { array_push($ids, $addrID);  $need_cnt++;}
    
    //find record and details

    $records = recordSearch_2('ids:'.implode(',',$ids));

    if(count(@$records['records'])<$need_cnt){
        echo 'Some records not found '.implode(',',$ids).'  '.print_r($records,true);
        return;
    }

    //these rectypes are specific for digital harlem
    define('RT_ADDRESS', 12);
    define('RT_EVENT', 14);
    define('RT_PLACE_ROLE', 16);

    define('DT_EVENT_TYPE', 74);
    define('DT_EVENT_TIME_START', 75);
    define('DT_EVENT_TIME_END', 76);

    define('DT_REPORT_SOURCE_TYPE', 77);
    define('DT_REPORT_DALINK', 78);
    define('DT_REPORT_CITATION', 79);
    define('DT_CHARGE_INITIAL', 80);
    define('DT_CHARGE_FINAL', 81);
    define('DT_JUDICIAL_OUTCOME', 83);
    define('DT_OUTCOME_DATE', 84);


    define('DT_PLACE_USAGE_TYPE', 89);

    define('DT_PERSON_OCCUPATION', 92);
    define('DT_PERSON_AGE', 93);
    define('DT_PERSON_SECONDNAME', 96);

    define('DT_PERSON_RACE', 98);
    define('DT_PERSON_BIRTHPLACE', 97);



    define('TERM_MAIN_CRIME_LOCATION', 4536);

    global $terms;
    //echo '<br>A'.print_r($records, true);

    $recTypeID = getFieldValue($records, $recID, 'rec_RecTypeID');


    $moredetailLink =  '<p><a href="javascript:void(0)" class="moredetail" '.
        'onClick="window.hWin.HEURIST4.msg.showDialog(\''.$_SERVER['REQUEST_URI'].'&full=1\');">More Detail</a></p>';

        
    if($recTypeID==RT_ADDRESS){

        if( @$_REQUEST['full']==1 ){
            require ('dh_popup_place.php');
        }

        $comment = getFieldValue($records, $recID, DT_SHORT_SUMMARY);
?>

<div class="infowindow infowindow-map">
    <h3><?php echo getFieldValue($records, $recID, 'rec_Title'); ?></h3>
    <?php echo $comment? 'Building details:<br/>'.$comment:''; ?>
    <?php echo $moredetailLink; ?>
</div>

<?php
    }else{ 

        if(!isset($terms)){   //global
            $terms = dbs_GetTerms($system);
        }

    if($recTypeID==RT_PLACE_ROLE){

            if( @$_REQUEST['full']==1 ){
            
                $recTypeID = RT_ADDRESS;
                $recID = $addrID;

                require ('dh_popup_place.php');
            }

            $comment = getFieldValue($records, $recID, DT_EXTENDED_DESCRIPTION);
            //date
            $date_out = composeDates( $records, $recID, '<b>Date: </b>');
?>

<div class="infowindow infowindow-map">
    <h3><?php echo getFieldValue($records, $recID, DT_NAME) ?></h3>
        <p><b><?php echo getTermById(getFieldValue($records, $recID, DT_PLACE_USAGE_TYPE)) ?> </b></p>

        <?php echo getFieldValue($records, $addrID, 'rec_Title'); ?></p>
        <p><?php echo @$comment?$comment:''; ?></p>

        <p>
            <?php echo $date_out; ?>&nbsp;
        </p>
        <?php echo $moredetailLink; ?>
</div>

<?php
        
    }else if($recTypeID==RT_PERSON){

        if( @$_REQUEST['full']==1 ){
            require ('dh_popup_person.php');
        }

        if($eventID){

             // get person role in the event
             $person_role = getTermById_2( recordGetRealtionshipType( $system, $eventID, $recID ) );

             // TODO: Remove, enable or explain: $relation1 = recordGetRealtionship_2($system, $eventID, $recID, 'event for person' );

             //get relationship record for address of related event
             $relation2 = recordGetRealtionship_2($system, $eventID, $addrID, 'address for event' );

             $event_address = '';
             $term_id = getFieldValue($relation2, 0, DT_RELATION_TYPE);
             if($term_id==TERM_MAIN_CRIME_LOCATION){
                 $event_address = 'in which the crime scene was at';
             }else{
                 $event_address = 'which '.getTermById_2( $term_id );
             }
             $event_address = $event_address.' this address ,';

             $comment = getFieldValue($relation2, 0, DT_EXTENDED_DESCRIPTION);

             //date and time
             $date_out = composeDates( $records, $eventID, '<b>Date: </b>' );
             $time_out = composeTime( $records, $eventID, '<b>Time: </b>' );


?>

<div class="infowindow infowindow-map">
    <h3><?php echo getFieldValue($records, $recID, 'rec_Title') ?></h3>

    <p><b><?php echo htmlspecialchars($person_role); ?></b>

    in the event: <b><?php echo getFieldValue($records, $eventID, DT_NAME); ?></b>

    <?php echo $event_address;?>

    <?php echo getFieldValue($records, $addrID, 'rec_Title'); ?></p>
    <p><?php echo $comment; ?></p>

    <p>
        <?php echo $date_out; ?>&nbsp;
        <?php echo $time_out; ?>&nbsp;
    </p>
    <?php echo $moredetailLink; ?>
</div>

<?php
        }else{

             //get relationship record for related address
             $relation1 = recordGetRealtionship_2($system, $recID , $addrID, 'address for person');

             // get person role in the event
             $term_id  = getFieldValue($relation1, 0, DT_RELATION_TYPE);
             $person_role = getTermById_2($term_id);

             $comment = getFieldValue($relation1, 0, DT_EXTENDED_DESCRIPTION);

             //date and time
             $date_out = composeDates( $relation1, 0, '<b>Date: </b>');
?>

<div class="infowindow infowindow-map">
    <h3><?php echo getFieldValue($records, $recID, 'rec_Title') ?></h3>

        <p><b><?php echo $person_role;?></b></p>
        <p><b>At</b>: <?php echo getFieldValue($records, $addrID, 'rec_Title'); ?></p>
        <p><?php echo $comment;?></p>
        <p><?php echo $date_out;?></p>
        <?php echo $moredetailLink; ?>
</div>

<?php
        }
    }else if($recTypeID==RT_EVENT){

        if( @$_REQUEST['full']==1 ){
            require ('dh_popup_event.php');
        }
        
        if($entities_per_address>1){
            $moredetailLink =  $moredetailLink.'<p><a href="javascript:void(0)" class="moredetail" '.
            'onClick="window.hWin.HEURIST4.msg.showDialog(\''.$_SERVER['SCRIPT_NAME'].'?recID='.$addrID.'&full=1\');">'.
            ($entities_per_address-1).' more event'.($entities_per_address>2?'s':'').' at this address</a></p>';
        }

             //get relationship record for related address
             $relation2 = recordGetRealtionship_2($system, $recID, $addrID, 'address for event' );

             $event_address = '';
             $term_id = getFieldValue($relation2, 0, DT_RELATION_TYPE);
             if($term_id==TERM_MAIN_CRIME_LOCATION){
                 $event_address = 'Location of the event at';
             }else{
                 $event_address = 'This event '. getTermById( $term_id ).' this address: ';
             }

             $comment = getFieldValue($relation2, 0, DT_EXTENDED_DESCRIPTION);

             //date and time
             $date_out = composeDates( $records, $recID, '<b>Date: </b>');
             $time_out = composeTime( $records, $recID, '<b>Time: </b>' );
?>

<div class="infowindow infowindow-map">
    <h3><?php echo getFieldValue($records, $recID, DT_NAME) ?></h3>
        <p><b><?php echo getTermById(getFieldValue($records, $recID, DT_EVENT_TYPE)) ?> </b></p>

        <p><?php echo $event_address;?>

        <?php echo getFieldValue($records, $addrID, 'rec_Title'); ?></p>
        <p><?php echo $comment; ?></p>

        <p>
            <?php echo $date_out; ?>&nbsp;
            <?php echo $time_out; ?>&nbsp;
        </p>
        <?php echo $moredetailLink; ?>
</div>

<?php
    }
    }

//
//
//
function getFieldValue($records, $recID, $fieldID, $needall=false){

    if(!@$records['records']){
        return null;
    }
    $recs = $records['records'];

    if($recID<1){
        //if recID is not defined - get first in array
        $recs = array_values($recs);
        $record = $recs[0];

        //echo 'B>'.print_r($record, true);
    }else{
        $record = @$recs[$recID];
    }

    if(!$record) return null;

    //echo 'B>'.print_r($record, true);

    if(is_numeric($fieldID)){ //detail

        $detail = @$record['d'][$fieldID];
        if($needall){
            return $detail;
        }else{
            return $detail[0];
        }
    }else{
        $fieldidx = array_search($fieldID, $records['fields'], true);
        if($fieldidx===false){
            return null;
        }else{
            return @$record[$fieldidx];
        }
    }
}


//
//
//
function isDateInRange( $records, $recID, $min, $max) {

    $date_start = getFieldValue($records, $recID, DT_START_DATE);
     
    if($date_start){
         try{
            $date_start = new DateTime($date_start);
            return $date_start>$min && $date_start<$max;

         } catch (Exception  $e){
             return false;
         }
     
    }else{ //date not defined - allow
        return true;
    }
}

//
//
//
function composeDates( $records, $recID, $prefix='') {
     $date_out = '';
     $date_start = getFieldValue($records, $recID, DT_START_DATE);
     if($date_start){
         
        if(strpos($date_start,"|")!==false){  
          return $prefix.temporalToHumanReadableString($date_start);
        }
        
        $date_out = $prefix.$date_start;

        $date_end = getFieldValue($records, $recID, DT_END_DATE);
        if($date_end && $date_end!=$date_start){
            $date_out = $date_out.('&nbsp;to&nbsp;'.$date_end);
        }
     }
     return $date_out;
}

//
//
//
function composeTime( $records, $recID, $prefix='') {

             if(!($recID>0)){
                 $recID = @$records['order'][0];
             }

             $time_out = '';
             $time_start = getFieldValue($records, $recID, DT_EVENT_TIME_START);
             if($time_start){
                $time_out = $prefix.getTermById( $time_start );

                $time_end = getFieldValue($records, $recID, DT_EVENT_TIME_END);
                if($time_end && $time_end!=$time_start){
                    $time_out = $time_out.(' to '.getTermById($time_end) );
                }
             }

             return $time_out;
}



//
// find term by termid, returns human readable message if not found
//
function getTermById_2($term_id, $type_suffix=''){
    $ret = getTermById($term_id);
    if(null==$ret || strpos(strtolower($ret),'unknown')!==false ){
        return ($type_suffix=='') ?'' :' unknown '.$type_suffix;
    }else{
        return $ret;
    }
}



//
//
//
function recordGetRealtionship_2($system, $sourceID, $targetID, $remark=' record' ){

    $res = recordGetRealtionship($system, $sourceID, $targetID );
     if(@$res['status']!='ok'){
        echo 'Cannot get related '.$remark.'. '.@$res['message'];
        exit();
     }else{
        return $res['data'];
     }
}



function recordSearch_2( $query ){

     global $system;

     $records = recordSearch($system, array(
            'q'=>$query,
            'detail'=>'detail'));

     if(@$records['status']!='ok'){
        echo 'Cannot get records '.$records['message'];
        exit();
     }else{
        return $records['data'];
     }
}



//
// returns relationship records(s) for given source and target records
//
function recordGetRealtionship($system, $sourceID, $targetID ){

    $mysqli = $system->get_mysqli();

    //find all target related records
    $query = 'SELECT rl_RelationID FROM recLinks '
        .'WHERE rl_SourceID='.$sourceID.' AND rl_TargetID='.$targetID.' AND rl_RelationID IS NOT NULL';


    $res = $mysqli->query($query);
    if (!$res){
        return $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
    }else{
            $ids = array();
            while ($row = $res->fetch_row()) {
                array_push($ids, intval($row[0]));
            }
            $res->close();

            return recordSearch($system, array('q'=>'ids:'.implode(',', $ids), 'detail'=>'detail'));
    }


}



//
// returns only first relationship type ID for 2 given records
//
function recordGetRealtionshipType($system, $sourceID, $targetID ){

    $mysqli = $system->get_mysqli();

    //find all target related records
    $query = 'SELECT rl_RelationTypeID FROM recLinks '
        .'WHERE rl_SourceID='.$sourceID.' AND rl_TargetID='.$targetID.' AND rl_RelationID IS NOT NULL';
    $res = $mysqli->query($query);
    if (!$res){
        return null;// $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
    }else{
        if($row = $res->fetch_row()) {
            return $row[0];
        }else{
            return null;
        }
    }
}

?>
