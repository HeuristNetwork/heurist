<?php
/**
* dh_popup_details.php : Content for detail info popup - it is included into dh_popup.php
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

    if(!isset($terms)){   //global
        $terms = dbs_GetTerms($system);
    }

    if($recTypeID==RT_ADDRESS){

        //find linked place roles  RT_PLACE_ROLE

        $records_placeroles = recordSearch($system, array(
                'q'=>'[{"t":"16"},{"linked_to:12:90":"'. $recID .'" }]',
                'detail'=>'detail'));
             if(@$records_placeroles['status']!='ok'){
                return 'Cannot get linked place roles for address-related event for person';
             }else{
                $records_placeroles = $records_placeroles['data'];
             }

?>
<div class="infowindow">

    <h3><?php echo getFieldValue($records, $recID, 'rec_Title'); ?></h3>

<br />

<p><b>Type of Building or Location: </b>

<ul>

<?php
        if(@$records_placeroles['reccount']>0){
        foreach($records_placeroles['records'] as $roleID => $record){

            $date_out = composeDates( $records_placeroles, $roleID, '<b>Date: </b>');
?>
    <li>
        <em><?php echo getFieldValue($records_placeroles, $roleID, DT_NAME); ?></em>
        <?php echo getTermById( getFieldValue($records_placeroles, $roleID, DT_PLACE_USAGE_TYPE) ); ?>&nbsp;on&nbsp;
        <?php echo $date_out; ?>
        <em><?php echo getFieldValue($records_placeroles, $roleID, DT_EXTENDED_DESCRIPTION); ?></em>
    </li>
<?php
        }
        }else{
            echo '<li>None recorded</li>';
        }
?>
</ul></p>



<br />

<p><b>Documentary Sources:</b>

<ul>

[foreach MYLNK_EVENT_TO_ADDRESS foreachevent]

    <li>

        [select MYEVENT eventselect]

            [foreach MYLNK_EVENT_TO_REPORT foreachreport]

                [select MYREPORT selectreport]

                    <li>(#[RP_ID]).&nbsp;<em>[RP_TITLE]</em>. &#91;[RP_SOURCE_DATE]&#93;. [LKP_SOURCE_TYPE_VALUE]&nbsp;[LKP_REPORT_VALUE][RP_SOURCE_CITATION]</li>

                [end-select]

            [end-foreach][not-found][end-not-found]

        [end-select]

    </li>

[end-foreach][not-found]<li>None found</li>[end-not-found]

</ul></p>



<br />

<p><b>What Happened Here: </b>

<ul>

[foreach MYLNK_EVENT_TO_ADDRESS foreachevent]

    <li>

        [select MYEVENT eventselect]

            <a href="eventpopup.php?EV_ID=[EV_ID]">[EV_NAME]</a> ([LKP_EVENT_TYPE_VALUE])

                <ul>

                    <li>[if-eq:LKP_EVAD_RELATIONSHIP_TYPE_ID:"9"]Crime Location[else][LKP_EVAD_RELATIONSHIP_TYPE_VALUE] this address[end-if-eq]</li>

                    <li>[EV_START_DATE][if-eq:EV_START_DATE:EV_END_DATE][else] to [EV_END_DATE][end-if-eq]&nbsp;Time: [EV_START_TIME][LKP_TIME_VALUE][if:EV_END_TIME] to [EV_END_TIME][LKP_TIME_END_VALUE][end-if]</li>



                    [foreach MYLNK_INDIVIDUAL_TO_EVENT foreachindividual]

                        [select MYINDIVIDUAL selectindividual]

                    <li>Involved [IV_NAME_FIRSTNAME] [IV_NAME_SURNAME] ([LKP_INDIVIDUAL_TYPE_VALUE]), [if:IV_RACE][if-eq:IV_RACE:"4"]unknown race, [else][LKP_RACE_VALUE] [end-if-eq][else]Unknown race, [end-if][if-eq:IV_GENDER:"3"]unknown M/F[else][LKP_GENDER_VALUE][end-if-eq][if:IV_BIRTHPLACE], from [LKP_BIRTHPLACE_VALUE][else], of unknown origin.[end-if]</li>

                        [end-select]

                    [end-foreach][not-found][end-not-found]



                    [foreach MYLNK_EVENT_TO_REPORT foreachreport]

                        [select MYREPORT selectreport]

                    [if:RP_CHARGE_INITIAL]<li>Resulted in a charge of [LKP_CHARGE_TYPE_VALUE][end-if][if:RP_JUDICIAL_OUTCOME] ([LKP_JUDICIAL_OUTCOME_VALUE][if:RP_OUTCOME_DATE] on [RP_OUTCOME_DATE][end-if][if:LKP_CHARGE_TYPE2_VALUE] as [LKP_CHARGE_TYPE2_VALUE][end-if])</li>[end-if]

                    <li>Event described in source #[RP_ID]</li>

                        [end-select]

                    [end-foreach][not-found][end-not-found]





                </ul>

        [end-select]

    </li>

[end-foreach][not-found]<li>Nothing recorded</li>[end-not-found]

</ul></p>



<br />

<p><b>People Connected to This Place:</b>



<ul>



[foreach MYLNK_INDIVIDUAL_TO_ADDRESS foreachindividual]

    <li>

        [select MYINDIVIDUAL getindividual]

            [IV_NAME_FIRSTNAME] [IV_NAME_SURNAME] ([LKP_INDIVIDUAL_TYPE_VALUE])

            <ul>

                <li>[if:IV_RACE][if-eq:IV_RACE:"4"]Unknown race, [else][LKP_RACE_VALUE] [end-if-eq][else]Unknown race, [end-if][if-eq:IV_GENDER:"3"]unknown M/F[else][LKP_GENDER_VALUE][end-if-eq][if:IV_BIRTHPLACE], from [LKP_BIRTHPLACE_VALUE][else], of unknown origin.[end-if]</li>

                [foreach MYLNK_REPORT_TO_INDIVIDUAL ageoccupationlink]

                    [if:LNK_RPIV_INDIVIDUAL_AGE]<li>[LNK_RPIV_INDIVIDUAL_AGE]&nbsp;years of age on&nbsp;[select MYREPORT getreport][RP_SOURCE_DATE][end-select]</li>[end-if]

                    [if:LNK_RPIV_INDIVIDUAL_OCCUPATION]<li>[LKP_OCCUPATION_VALUE]&nbsp;on&nbsp;[select MYREPORT getreport][RP_SOURCE_DATE][end-select][not-found][end-not-found]</li>[end-if]

                [end-foreach][not-found][end-not-found]



                [foreach MYLNK_INDIVIDUAL_TO_ADDRESS get_all_linked_addresses]

                    [select MYADDRESS address]

                    <li>[LKP_INDIVIDUAL_TYPE_VALUE] at <a href="addresspopup.php?AD_ID=[AD_ID]">[if:AD_STREET_NUMBER][AD_STREET_NUMBER] [end-if][LKP_STREET_NAME_VALUE]</a> on [LNK_IVAD_START_DATE] [if-eq:LNK_IVAD_END_DATE:LNK_IVAD_START_DATE][else] to [LNK_IVAD_END_DATE][end-if-eq]</li>

                    [end-select]

                [end-foreach][not-found][end-not-found]

            </ul>

        [end-select]

    </li>

[end-foreach][not-found]<li>Nobody recorded</li>[end-not-found]



</p></ul>







</div>


<?php
    exit();
    }
?>