<?php
/**
* dh_popup.php : Content for map bubble and info popup
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
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

    require_once (dirname(__FILE__).'/../../php/System.php');
    require_once (dirname(__FILE__).'/../../php/common/db_recsearch.php');
    require_once (dirname(__FILE__).'/../../php/common/db_structure.php');
    require_once (dirname(__FILE__).'/../../php/common/utils_db.php');

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

    //echo '>1>'.@$_REQUEST['recID'];
    
    $recID = $_REQUEST['recID'];
    $ids = $recID;
    $need_cnt = 1;

    $eventID = @$_REQUEST['eventID'];
    if($eventID) { $ids = $ids.','.$eventID; $need_cnt++;}
    $addrID = @$_REQUEST['addrID'];
    if($addrID) { $ids = $ids.','.$addrID;  $need_cnt++;}

    //find record and details
    
    $params = array(
      'q'=>'ids:'.$ids,
      'detail'=>'detail'
    );

    $records = recordSearch($system, $params);
  
    if(@$records['status']!='ok' || count(@$records['data']['records'])<$need_cnt){
        echo 'Some records not found '.$ids.'  '.print_r($records,true);
        return;
    }

    //these rectypes are specific for digital harlem
    define('RT_ADDRESS', 12);
    define('RT_EVENT', 14);
    define('DT_EVENT_TYPE', 74);
    define('DT_EVENT_TIME_START', 75);
    define('DT_EVENT_TIME_END', 76);
    
    define('TERM_MAIN_CRIME_LOCATION', 4536);

    global $terms;
    //echo '<br>A'.print_r($records, true);

    $records = $records['data'];
    
    $recTypeID = getFieldValue($records, $recID, 'rec_RecTypeID');
    
    $moredetailLink =  '<p><a href="javascript:void(0)" class="moredetail" '.
        'onClick="top.HEURIST4.util.showDialog(\''.$_SERVER['REQUEST_URI'].'&full=1\');">More Detail</a></p>';
    
    
    if($recTypeID==RT_ADDRESS){
        $comment = getFieldValue($records, $recID, DT_SHORT_SUMMARY);
?>        
<div class="infowindow">
    <h3><?php echo getFieldValue($records, $recID, 'rec_Title'); ?></h3>
    <?php echo $comment? 'Building details:<br/>'.$comment:''; ?>
    <?php echo $moredetailLink; ?>        
</div>  
<?php 
    }else{
        
        if(!isset($terms)){   //global
            $terms = dbs_GetTerms($system);
        }
     
    if($recTypeID==RT_PERSON){
        
        if($eventID){
             
             //get relationship record for related event
             $relation1 = recordGetRealtionship($system, $eventID, $recID );        
             if(@$relation1['status']!='ok'){
                return 'Can not get related event for person';
             }else{
                $relation1 = $relation1['data']; 
             }
             // get person role in the event
             $term_id  = getFieldValue($relation1, 0, DT_RELATION_TYPE);
             $person_role = getTermById($term_id);

             //get relationship record for address of related event
             $relation2 = recordGetRealtionship($system, $eventID, $addrID );   
             if(@$relation2['status']!='ok'){
                return 'Can not get related addrss for event';
             }else{
                $relation2 = $relation2['data']; 
             }
                          
             $event_address = '';
             $term_id = getFieldValue($relation2, 0, DT_RELATION_TYPE);
             if($term_id==TERM_MAIN_CRIME_LOCATION){
                 $event_address = 'in which the crime scene was at';
             }else{
                 $event_address = 'which '.getTermById( $term_id );
             }
             $event_address = $event_address.' this address ,';
             
             $comment = getFieldValue($relation2, 0, DT_EXTENDED_DESCRIPTION);
             
             //date and time 
             $date_out = '';
             $date_start = getFieldValue($records, $eventID, DT_START_DATE);
             if($date_start){
                $date_out = '<b>Date: </b>'.$date_start;

                $date_end = getFieldValue($records, $eventID, DT_END_DATE);
                if($date_end && $date_end!=$date_start){
                    $date_out = $date_out.(' to '.$date_end);
                }
             }

             $time_out = '';
             $time_start = getFieldValue($records, $eventID, DT_EVENT_TIME_START);
             if($time_start){
                $time_out = '<b>Time: </b>'.getTermById($time_start);

                $time_end = getFieldValue($records, $eventID, DT_EVENT_TIME_END);
                if($time_end && $time_end!=$time_start){
                    $time_out = $time_out.(' to '.getTermById($time_end) );
                }
             }
             
             
?>
<div class="infowindow">
    <h3><?php echo getFieldValue($records, $recID, 'rec_Title') ?></h3>
    
    <p><b><?php echo $person_role; ?></b> 
    
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
             $relation1 = recordGetRealtionship($system, $recID , $addrID);        
             if(@$relation1['status']!='ok'){
                return 'Can not get related address for event';
             }else{
                $relation1 = $relation1['data']; 
             }
             // get person role in the event
             $term_id  = getFieldValue($relation1, 0, DT_RELATION_TYPE);
             $person_role = getTermById($term_id);
            
             $comment = getFieldValue($relation1, 0, DT_EXTENDED_DESCRIPTION);
             
             //date and time 
             $date_out = '';
             $date_start = getFieldValue($relation1, 0, DT_START_DATE);
             if($date_start){
                $date_out = '<b>Date: </b>'.$date_start;

                $date_end = getFieldValue($relation1, 0, DT_END_DATE);
                if($date_end && $date_end!=$date_start){
                    $date_out = $date_out.(' to '.$date_end);
                }
             }
             
?>            
        
<div class="infowindow">
    <h3><?php echo getFieldValue($records, $recID, 'rec_Title') ?></h3>

        <p><b><?php echo $person_role;?></b></p>
        <p>At this address: <?php echo getFieldValue($records, $addrID, 'rec_Title'); ?></p>
        <p><?php echo $comment;?></p>
        <p><?php echo $date_out;?></p>
        <?php echo $moredetailLink; ?>        
</div>        
<?php            
        }
    }else if($recTypeID==RT_EVENT){
        
             //get relationship record for related address
             $relation2 = recordGetRealtionship($system, $recID, $addrID );   
             if(@$relation2['status']!='ok'){
                return 'Can not get related addrss for event';
             }else{
                $relation2 = $relation2['data']; 
             }
                          
             $event_address = '';
             $term_id = getFieldValue($relation2, 0, DT_RELATION_TYPE);
             if($term_id==TERM_MAIN_CRIME_LOCATION){
                 $event_address = 'Location of the event at';
             }else{
                 $event_address = 'This event '. getTermById( $term_id ).' this address: ';
             }
             
             $comment = getFieldValue($relation2, 0, DT_EXTENDED_DESCRIPTION);
             
             //date and time 
             $date_out = '';
             $date_start = getFieldValue($records, $recID, DT_START_DATE);
             if($date_start){
                $date_out = '<b>Date: </b>'.$date_start;

                $date_end = getFieldValue($records, $recID, DT_END_DATE);
                if($date_end && $date_end!=$date_start){
                    $date_out = $date_out.(' to '.$date_end);
                }
             }

             $time_out = '';
             $time_start = getFieldValue($records, $recID, DT_EVENT_TIME_START);
             if($time_start){
                $time_out = '<b>Time: </b>'.getTermById($time_start);

                $time_end = getFieldValue($records, $recID, DT_EVENT_TIME_END);
                if($time_end && $time_end!=$time_start){
                    $time_out = $time_out.(' to '.getTermById($time_end) );
                }
             }

        
?>        
<div class="infowindow">
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
    
       /*
function recordGetRealtionship($system, $sourceID, $targetID ){
    
    $record = recordGetRealtionship($system, $sourceID, $targetID );
    if(@$record['status']!='ok'){
        return 'Can not get relationship record';
    }else{
        return $record['data']; 
    }
}    */

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

?>
