<?php

/**
*
* dh_popup_place.php (Digital Harlem): Content for detail info popup - it is included into dh_popup.php
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
if(function_exists('dbs_GetTerms')) {

if(!isset($terms)){   //global
    $terms = dbs_GetTerms($system);
}
if($recTypeID==RT_ADDRESS){

    //find linked place roles  RT_PLACE_ROLE
    
    $isRiot = (@$_REQUEST['riot']==1);
    $riotMinDate = new DateTime('1934-12-31T23:59:59');
    $riotMaxDate = new DateTime('1936-01-01');

    $date_filter = $isRiot
                    ?'{"f:'.DT_START_DATE.'":"1934-12-31T23:59:59.999Z<>1936-01-01"},'
                    :'{"f:'.DT_START_DATE.'":"1914-12-31T23:59:59.999Z<>1935-01-01"},'; //for start date
    $date_filter2 = $isRiot
                    ?'{"f:'.DT_DATE.'":"1934-12-31T23:59:59.999Z<>1936-01-01"},'
                    :'{"f:'.DT_DATE.'":"1914-12-31T23:59:59.999Z<>1935-01-01"},'; //for single date
    // place roles for address related event or person
    $records_placeroles = recordSearch_2('[{"t":"16"},'.$date_filter.'{"linked_to:12:90":"'. $recID .'" }]');
    
    ?>


    <html>

        <head>
        <title><?=(@$_REQUEST['db']?$_REQUEST['db']:'').'. '.HEURIST_TITLE ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="dh_style.css">
        <head>


        <body>
            <div class="infowindow">

                <h3><?php echo getFieldValue($records, $recID, 'rec_Title'); ?></h3>

                <br />

                <p>
                    <b>Type of Building or Location: </b>
                    <ul>

                        <?php
                        $sourcesInfoFromPlaceRoles = '';
                        
                        if(@$records_placeroles['reccount']>0){
                            foreach($records_placeroles['order'] as $roleID){

                                $date_out = composeDates( $records_placeroles, $roleID, '<b>Date: </b>');
                                ?>
                                <li>
                                    <em><?php echo getFieldValue($records_placeroles, $roleID, DT_NAME); ?></em>
                                    <?php echo getTermById( getFieldValue($records_placeroles, $roleID, DT_PLACE_USAGE_TYPE) ); ?>&nbsp;on&nbsp;
                                    <?php echo $date_out; ?>
                                    <em>(<?php echo getFieldValue($records_placeroles, $roleID, DT_EXTENDED_DESCRIPTION); ?>)</em>
                                    <br><br>Owner:<?php echo getTermById( getFieldValue($records_placeroles, $roleID, DT_PERSON_RACE) ); ?>
                                </li>
                                <?php
                                
                                $citation = getFieldValue($records_placeroles, $roleID, DT_REPORT_CITATION);
                                
                                if($citation){
                                    $sourcesInfoFromPlaceRoles = $sourcesInfoFromPlaceRoles.'<li>'
                                        .getTermById( getFieldValue($records_placeroles, $roleID, DT_REPORT_SOURCE_TYPE) ).' '
                                        .getFieldValue($records_placeroles, $roleID, DT_REPORT_CITATION).'</li>'; 
                                }
                                
                            }
                        }else{
                            echo '<li>None recorded</li>';
                        }
                        ?>

                    </ul>
                </p>

                <br />

                
                
                <p>
                    <b>What Happened Here: </b>

                    <ul>

                        <?php
                        // find related events
                        $records_events = recordSearch_2('[{"t":"14"},'.$date_filter.'{"related_to:12":"'. $recID .'"} ]');

                        if(@$records_events['reccount']>0){
                            foreach($records_events['order'] as $eventID){

                                //get relationship record for related address (or search events with relation at once?)
                                $term_id = recordGetRealtionshipType( $system, $eventID, $recID );
                                $event_address = '';
                                if($term_id==TERM_MAIN_CRIME_LOCATION){
                                    $event_address = 'Crime location';
                                }else{
                                    $event_address = getTermById_2( $term_id ).' this address';
                                }

                                //date and time
                                $date_out = composeDates( $records_events, $eventID, '<b>Date: </b>');
                                //print 'AAA'.print_r($records_events['records'][$eventID]['d'], true);
                                $time_out = composeTime( $records_events, $eventID, '<b>Time: </b>' );


                                print '<li><a href="dh_popup.php?db='.HEURIST_DBNAME.'&full=1&recID='.$eventID.'">'
                                .getFieldValue($records_events, $eventID, DT_NAME).'</a> ('
                                .getTermById( getFieldValue($records_events, $eventID, DT_EVENT_TYPE) )
                                .')';

                                print ' <ul><li>'.$event_address.'</li>'; //type of location
                                print  '<li>'.$date_out.'&nbsp;'.$time_out.'</li>';

                                //involved persons
                                $records_persons = recordSearch_2('[{"t":"10"},{"relatedfrom:14":"'. $eventID .'"} ]');

                                foreach($records_persons['order'] as $personID){
                                    //find relationship
                                    $person_role = getTermById_2( recordGetRealtionshipType( $system, $eventID, $personID ) );

                                    print '<li>Involved '.getFieldValue($records_persons, $personID, DT_GIVEN_NAMES).' '
                                    .getFieldValue($records_persons, $personID, DT_NAME)
                                    .' ('.$person_role.'), '
                                    .getTermById_2( getFieldValue($records_persons, $personID, DT_PERSON_RACE), 'race' ).', '
                                    .getTermById_2( getFieldValue($records_persons, $personID, DT_GENDER), 'gender' ).', from '
                                    .getTermById_2( getFieldValue($records_persons, $personID, DT_PERSON_BIRTHPLACE), 'origin' ).'</li>';
                                }

                                //involved reports
                                $records_reports = recordSearch_2('[{"t":"15"},{"relatedfrom:14":"'. $eventID .'"} ]');

                                foreach($records_reports['order'] as $reportID){

                                    $charge_initial = getFieldValue($records_reports, $reportID, DT_CHARGE_INITIAL);
                                    if($charge_initial>0){

                                        print '<li>Resulted in a charge of '.getTermById_2($charge_initial);

                                        $judical_outcome = getFieldValue($records_reports, $reportID, DT_JUDICIAL_OUTCOME);
                                        if($judical_outcome>0){

                                            print ' ('.getTermById_2($judical_outcome).' '
                                            .getFieldValue($records_reports, $reportID, DT_OUTCOME_DATE).' '
                                            .getTermById_2(getFieldValue($records_reports, $reportID, DT_CHARGE_FINAL)).')';

                                        }

                                        print '</li>';
                                    }
                                    print '<li>Event described in source #'.$reportID.'</li>';
                                }
                                print '</ul></li>';
                            }//for events
                        }else{
                            echo '<li>None recorded</li>';
                        }
                        ?>

                    </ul></p>

                <br />


                <p>
                    <b>People Connected to This Place:</b>

                    <ul>

                        <?php

                        // find related persons
                        $records_persons = recordSearch_2('[{"t":"10"},{"related_to:12":"'. $recID .'"} ]');

                        if(@$records_persons['reccount']>0){
                            
                            $isSomeoneRelated = false;
                            
                            foreach($records_persons['order'] as $personID){

                                //find how person was related to this address
                                $records_address = recordSearch_2('[{"t":"12"},{"relatedfrom:10":"'. $personID .'"} ]');
                                $sRealtionOfPersonToAddress = '';
                                foreach($records_address['order'] as $addrID){
                                    $relation1 = recordGetRealtionship_2($system, $personID , $addrID, 'address for person');
                                    
                                    //allows 1935 year only
                                    $isInRiotRange = isDateInRange($relation1, 0, $riotMinDate, $riotMaxDate);
                                    
                                    if($isRiot!==$isInRiotRange){
                                        continue;
                                    }
                                    /*if($isRiot && !isDateInRange($relation1, 0, $riotMinDate, $riotMaxDate) ){ 
                                        continue;
                                    }*/

                                    $sRealtionOfPersonToAddress = $sRealtionOfPersonToAddress.
                                    '<li>'.getTermById_2( getFieldValue($relation1, 0, DT_RELATION_TYPE))
                                    .' at <a href="dh_popup.php?db='.HEURIST_DBNAME.'&full=1&recID='.$addrID.'">'
                                    .getFieldValue($records_address, $addrID, 'rec_Title')
                                    .'</a> on '.composeDates( $relation1, 0)
                                    .'</li>';
                                }
                                
                                if($sRealtionOfPersonToAddress=='') continue;
                            
                                $isSomeoneRelated = true;    
                                
                                //role on address
                                $person_role = getTermById_2( recordGetRealtionshipType( $system, $personID, $recID ) );

                                print '<li>'.getFieldValue($records_persons, $personID, DT_NAME).' '
                                .getFieldValue($records_persons, $personID, DT_GIVEN_NAMES).' '
                                .'('.$person_role.')';

                                print '<ul>'
                                .'<li>'.getTermById_2( getFieldValue($records_persons, $personID, DT_PERSON_RACE), 'race' ).', '
                                .getTermById_2( getFieldValue($records_persons, $personID, DT_GENDER), 'gender' ).', from '
                                .getTermById_2( getFieldValue($records_persons, $personID, DT_PERSON_BIRTHPLACE), 'origin' ).'</li>';


                                //find reports  Report->Person
                                $records_reports = recordSearch_2('[{"t":"15"},{"related_to:10":"'. $personID .'"} ]');
                                foreach($records_reports['order'] as $reportID){
                                    $relation1 = recordGetRealtionship_2($system, $reportID , $personID, 'report for person');

                                    foreach($relation1['order'] as $relID){
                                        $person_age = getFieldValue($relation1, $relID, DT_PERSON_AGE);
                                        if($person_age>0){
                                            print '<li>'.$person_age.'&nbsp;years of age on&nbsp;'.
                                            getFieldValue($records_reports, $reportID, DT_DATE).'</li>';
                                        }
                                        $occupation_termid = getFieldValue($relation1, $relID, DT_PERSON_OCCUPATION);
                                        if($occupation_termid>0){
                                            print '<li>'.getTermById_2($occupation_termid).'&nbsp;on&nbsp;'.
                                            getFieldValue($records_reports, $reportID, DT_DATE).'</li>';
                                        }
                                    }
                                }
                                
                                //print how person was related to this address
                                print $sRealtionOfPersonToAddress;

                                print '</ul></li>';

                            }//for persons
                            if(!$isSomeoneRelated){
                                echo '<li>Nobody recorded '.($isRiot?' in year 1935':'').'</li>';
                            }
                        }else{
                            echo '<li>Nobody recorded</li>';
                        }
                        ?>

                    </ul></p>

                <br />


                <p>
                    <b>Documentary Sources:</b>

                    <ul>

                        <?php
                        // find Reports related to Events related to this address
                        $records_eventreports = recordSearch_2('[{"t":"15"},'.$date_filter2.'{"relatedfrom:14":[{"t":"14"},{"related_to:12":"'. $recID .'"} ] }]');

                        // Report (15)  -> DA Report (13) <- Place Role (16) -> Address
                        $records_eventreports2 = recordSearch_2(
                            '[{"t":"15"},'.$date_filter2.'{"linked_to:13:78":[{"t":"13"},{"linkedfrom:16:78":[{"t":"16"},{"linked_to:12:90":"'. $recID .'"} ] }]  }] ');

                        if(count($records_eventreports2['records'])>0){

                            $records_eventreports['records'] = mergeRecordSets($records_eventreports['records'], $records_eventreports2['records']);

                            $records_eventreports['order'] = array_merge($records_eventreports['order'], 
                                                                        array_keys($records_eventreports2['records']));
                            $records_eventreports['order'] = array_unique($records_eventreports['order']);
                        }

                        
                        // Newspaper Item (26) <- Place Role (16) -> Address
                        $records_placerolereports = recordSearch_2(
                            '[{"t":"26"},'.$date_filter2.'{"linkedfrom:16:151":[{"t":"16"},{"linked_to:12:90":"'. $recID .'"} ]  }] ');
                            
                        if($sourcesInfoFromPlaceRoles!='' || 
                            count($records_eventreports['records'])>0 || 
                            count($records_placerolereports['records'])>0){

                            //DA REPORT
                            foreach($records_eventreports['order'] as $repID){
                                //getFieldValue($records_eventreports, $repID, DT_ORIGINAL_RECORD_ID)

                                //find DA Report name
                                $da_report = '';
                                $da_repID = getFieldValue($records_eventreports, $repID, DT_REPORT_DALINK);
                                if($da_repID>0){
                                    $da_report = recordSearch_2('ids:'.$da_repID);
                                    $da_report = getFieldValue($da_report, 0, DT_NAME);
                                }

                                echo '<li>(#'.$repID.')&nbsp;<em>'.getFieldValue($records_eventreports, $repID, 'rec_Title')
                                    .'</em>. ['.getFieldValue($records_eventreports, $repID, DT_DATE).']. '
                                    .getTermById( getFieldValue($records_eventreports, $repID, DT_REPORT_SOURCE_TYPE) ).'&nbsp'
                                    .$da_report.' '
                                    .getFieldValue($records_eventreports, $repID, DT_REPORT_CITATION).'</li>'; 
                            }
                            
                            
                            
                            //NEWSPAPER
                            foreach($records_placerolereports['order'] as $repID){
                                
                                echo '<li><em>'.getFieldValue($records_placerolereports, $repID, 'rec_Title')
                                    .'</em></li>';
                            }
                            
                            if($sourcesInfoFromPlaceRoles!=''){
                                echo $sourcesInfoFromPlaceRoles;     
                            }
                            
                        }else{
                            echo '<li>None recorded</li>';
                        }
                        ?>
                    </ul>
                </p>

            </div>

        </body></html>

    <?php
    exit();
}

}
?>