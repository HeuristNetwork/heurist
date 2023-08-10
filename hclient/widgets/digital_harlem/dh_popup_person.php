<?php

/**
*
* dh_popup_person.php (Digital Harlem): Content for detail info popup - it is included into dh_popup.php
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
if(function_exists('dbs_GetTerms')) {

if(!isset($terms)){   //global
    $terms = dbs_GetTerms($system);
}
if($recTypeID==RT_PERSON){

    ?>
    <html>
        <head>
        <title><?=(@$_REQUEST['db']?htmlspecialchars($_REQUEST['db']):'').'. '.HEURIST_TITLE ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="dh_style.css">
        <head>
        <body>
            <div class="infowindow">


                <h3><?php echo getFieldValue($records, $recID, DT_NAME).' '
                    .getFieldValue($records, $recID, DT_PERSON_SECONDNAME).' '
                .getFieldValue($records, $recID, DT_GIVEN_NAMES); ?></h3>
                <br />
                <p>
                <b>Background Data:</b>

                    <ul>
                        <li>
                            <?php echo getTermById_2( getFieldValue($records, $recID, DT_PERSON_RACE), 'race' ).', '
                            .getTermById_2( getFieldValue($records, $recID, DT_GENDER), 'gender' ).', from '
                            .getTermById_2( getFieldValue($records, $recID, DT_PERSON_BIRTHPLACE), 'origin' ); ?>
                        </li>


                        <?php
                        //find reports  Report->Person
                        $records_reports = recordSearch_2('[{"t":"15"},{"related_to:10":"'. $recID .'"} ]');
                        foreach($records_reports['order'] as $reportID){
                            $relation1 = recordGetRealtionship_2($system, $reportID , $recID, 'report for person');

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
                        ?>
                    </ul>
                </p>

                <br />


                <p>
                <b>Known Addresses:</b>

                    <ul>
                        <?php
                        //find Person->Address
                        $records_address = recordSearch_2('[{"t":"12"},{"relatedfrom:10":"'. $recID .'"} ]');
                        if($records_address['count']>0) {
                            foreach($records_address['order'] as $addrID){
                                $relation1 = recordGetRealtionship_2($system, $recID , $addrID, 'address for person');
                                $comment = getFieldValue($relation1, 0, DT_EXTENDED_DESCRIPTION);

                                print '<li>'.getTermById_2( getFieldValue($relation1, 0, DT_RELATION_TYPE))
                                .' at <a href="dh_popup.php?db='.HEURIST_DBNAME.'&full=1&recID='.$addrID.'">'
                                .getFieldValue($records_address, $addrID, 'rec_Title')
                                .'</a> on '.composeDates( $relation1, 0).' '
                                .$comment
                                .'</li>';
                            }
                        }else{
                            echo '<li>None found</li>';
                        }
                        ?>
                    </ul>
                </p>

                <br />


                <p>
                <b>Documentary Sources:</b>

                    <ul>
                        <?php

                        // fi1nd Reports related to Person
                            $records_reports = recordSearch_2(
                            '{"any":[{"all":[{"t":"15"},{"related_to:10":"'. $recID .'"}]},'.
                                    '{"all":[{"t":"15"},{"relatedfrom:10":"'. $recID .'"}]}]}');

                        if(@$records_reports['reccount']>0){
                            foreach($records_reports['order'] as $repID){
                                //TODO: Remove, enable or explain: getFieldValue($records_reports, $repID, DT_ORIGINAL_RECORD_ID)

                                //find DA Report name
                                $da_report = '';
                                $da_repID = getFieldValue($records_reports, $repID, DT_REPORT_DALINK);
                                if($da_repID>0){
                                    $da_report = recordSearch_2('ids:'.$da_repID);
                                    $da_report = getFieldValue($da_report, 0, DT_NAME);
                                }
                                ?>
                                <li>
                                    (#<?php echo $repID.')&nbsp;<em>'.getFieldValue($records_reports, $repID, 'rec_Title')
                                    .'</em>. ['.getFieldValue($records_reports, $repID, DT_DATE).']. '
                                    .getTermById( getFieldValue($records_reports, $repID, DT_REPORT_SOURCE_TYPE) ).'&nbsp'
                                    .$da_report.' '
                                    .getFieldValue($records_reports, $repID, DT_REPORT_CITATION); ?>
                                </li>
                                <?php
                            }//foreach
                        }else{
                            echo '<li>None recorded</li>';
                        }
                        ?>
                    </ul>
                </p>


                <p>
                    <b>Involved in events: </b>

                    <ul>
                        <?php
                        // find related events
                        $records_events = recordSearch_2('[{"t":"14"},{"related_to:10":"'. $recID .'"} ]');

                        if(@$records_events['reccount']>0){
                            foreach($records_events['order'] as $eventID){

                                //get relationship record for related address (or search events with relation at once?)
                                $term_id = recordGetRealtionshipType( $system, $eventID, $recID );

                                //date and time
                                $date_out = composeDates( $records_events, $eventID, '<b>Date: </b>');
                                $time_out = composeTime( $records_events, $eventID, '<b>Time: </b>' );

                                print '<li><a href="dh_popup.php?db='.HEURIST_DBNAME.'&full=1&recID='.$eventID.'">'
                                .getFieldValue($records_events, $eventID, DT_NAME).'</a> ('
                                .getTermById( getFieldValue($records_events, $eventID, DT_EVENT_TYPE) )
                                .')';

                                print  '<ul><li>'.$date_out.'&nbsp;'.$time_out.'</li>';

                                //get address of event
                                $records_address = recordSearch_2('[{"t":"12"},{"relatedfrom:14":"'. $eventID .'"} ]');

                                foreach($records_address['order'] as $addrID){

                                    $relation1 = recordGetRealtionship_2($system, $eventID , $addrID, 'address for event');
                                    // TODO: Remove, enable or explain: $term_id = recordGetRealtionshipType( $system, $recID, $recID );

                                    $event_address = '';
                                    $term_id = getFieldValue($relation1, 0, DT_RELATION_TYPE);
                                    if($term_id==TERM_MAIN_CRIME_LOCATION){
                                        $event_address = 'Location: ';
                                    }else{
                                        $event_address = getTermById_2( $term_id );
                                    }

                                    $comment = getFieldValue($relation1, 0, DT_EXTENDED_DESCRIPTION);

                                    print '<li>'.$event_address.' '
                                    .' <a href="dh_popup.php?db='.HEURIST_DBNAME.'&full=1&recID='.$addrID.'">'
                                    .getFieldValue($records_address, $addrID, 'rec_Title')
                                    .'</a><br/> '.$comment
                                    .'</li>';
                                }//for address

                                print '</ul>'; //type of location
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