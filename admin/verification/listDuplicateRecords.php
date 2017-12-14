<?php

    /**
    * listDuplicateRecords.php: Lists groups of potential duplicated and allows various actions -
    * ignoring groups in future, merging group members, editing records
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
    * @author      Tom Murtagh
    * @author      Kim Jackson
    * @author      Stephen White   
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.1.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

    if(isForAdminOnly("to perform this action")){
        return;
    }

    $fuzziness = intval($_REQUEST['fuzziness']);
    if (! $fuzziness) $fuzziness = 10;

    $bibs = array();
    $dupes = array();
    $dupekeys = array();
    $recsGivenNames = array();
    $dupeDifferences = array();

    mysql_connection_insert(DATABASE);


    $res = mysql_query('select snd_SimRecsList from recSimilarButNotDupes');
    while ($row = mysql_fetch_assoc($res)){
        array_push($dupeDifferences,$row['snd_SimRecsList']);
    }

    if (@$_REQUEST['dupeDiffHash']){
        foreach($_REQUEST['dupeDiffHash'] as $diffHash){
            if (! in_array($diffHash,$dupeDifferences)){
                array_push($dupeDifferences,$diffHash);
                $res = mysql_query('insert into recSimilarButNotDupes values("'.$diffHash.'")');
            }
        }
    }

    mysql_connection_select(DATABASE);
    //mysql_connection_select("`heuristdb-nyirti`");   //for debug
    //FIXME  allow user to select a single record type
    //$res = mysql_query('select rec_ID, rec_RecTypeID, rec_Title, dtl_Value from Records left join recDetails on dtl_RecID=rec_ID and      \
    //dtl_DetailTypeID=160 where rec_RecTypeID != 52 and rec_RecTypeID != 55 and not rec_FlagTemporary order by rec_RecTypeID desc');

    $crosstype = false;
    $personMatch = false;

    $relRT = (defined('RT_RELATION')?RT_RELATION:0);
    $perRT = (defined('RT_PERSON')?RT_PERSON:0);
    $surnameDT = (defined('DT_GIVEN_NAMES')?DT_GIVEN_NAMES:0);
    $titleDT = (defined('DT_NAME')?DT_NAME:0);

    if (@$_REQUEST['crosstype']){
        $crosstype = true;
    }
    if (@$_REQUEST['personmatch']){
        $personMatch = true;
        $res = mysql_query("select rec_ID, rec_RecTypeID, rec_Title, dtl_Value from Records left join recDetails on dtl_RecID=rec_ID and dtl_DetailTypeID=$surnameDT where rec_RecTypeID = $perRT and not rec_FlagTemporary order by rec_ID desc");    //Given Name
        while ($row = mysql_fetch_assoc($res)) {
            $recsGivenNames[$row['rec_ID']] = $row['dtl_Value'];
        }
        $res = mysql_query("select rec_ID, rec_RecTypeID, rec_Title, dtl_Value from Records left join recDetails on dtl_RecID=rec_ID and dtl_DetailTypeID=$titleDT where rec_RecTypeID = $perRT and not rec_FlagTemporary order by dtl_Value asc");    //Family Name

    } else{
        $res = mysql_query("select rec_ID, rec_RecTypeID, rec_Title, dtl_Value from Records left join recDetails on dtl_RecID=rec_ID and dtl_DetailTypeID=$titleDT where rec_RecTypeID != $relRT and not rec_FlagTemporary order by rec_RecTypeID desc");
    }

    $rectypes = mysql__select_assoc('defRecTypes', 'rty_ID', 'rty_Name', '1');

    while ($row = mysql_fetch_assoc($res)) {
        if ($personMatch){
            if($row['dtl_Value']) $val = $row['dtl_Value'] . ($recsGivenNames[$row['rec_ID']]? " ". $recsGivenNames[$row['rec_ID']]: "" );
        }else {
            if ($row['rec_Title']) $val = $row['rec_Title'];
            else $val = $row['dtl_Value'];
        }
        $mval = metaphone(preg_replace('/^(?:a|an|the|la|il|le|die|i|les|un|der|gli|das|zur|una|ein|eine|lo|une)\\s+|^l\'\\b/i', '', $val));

        if ($crosstype || $personMatch) { //for crosstype or person matching leave off the type ID
            $key = ''.substr($mval, 0, $fuzziness);
        } else {
            $key = $row['rec_RecTypeID'] . '.' . substr($mval, 0, $fuzziness);
        }

        $typekey = $rectypes[$row['rec_RecTypeID']];

        if (! array_key_exists($key, $bibs)) $bibs[$key] = array(); //if the key doesn't exist then make an entry for this metaphone
        else { // it's a dupe so process it
            if (! array_key_exists($typekey, $dupes)) $dupes[$typekey] = array();
            if (!array_key_exists($key,$dupekeys))  {
                $dupekeys[$key] =  1;
                array_push($dupes[$typekey],$key);
            }
        }
        // add the record to bibs
        $bibs[$key][$row['rec_ID']] = array('type' => $typekey, 'val' => $val);
    }

    ksort($dupes);
    foreach ($dupes as $typekey => $subarr) {
        array_multisort($dupes[$typekey],SORT_ASC,SORT_STRING);
    }

?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Find Duplicate Records</title>
        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
    </head>

    <body class="popup">
        <script type="text/javascript">
            function setAsNonDuplication(){
                var checkboxes = document.getElementsByName('dupeDiffHash[]');
                for (var i=0; i<checkboxes.length; i++) {
                    if (checkboxes[i].checked) {
                        document.forms[0].submit();
                        return;
                    }
                }

                alert('Check several groups and then click any of the "ignore in future" links to set this for multiple groups.');
            }
        </script>

        <div class="banner"><h2>Find Duplicate Records</h2></div>
        <div id="page-inner" style="overflow:auto;padding-left: 20px;">

            <form>
                Matching precision: <select name="fuzziness" id="fuzziness" onchange="form.submit();">
                    <option value=3>3</option>
                    <option value=4 <?= $fuzziness == 4  ? "selected" : "" ?>>4</option>
                    <option value=5 <?= $fuzziness == 5 ? "selected" : "" ?>>5</option>
                    <option value=6 <?= $fuzziness == 6 ? "selected" : "" ?>>6</option>
                    <option value=7 <?= $fuzziness == 7 ? "selected" : "" ?>>7</option>
                    <option value=8 <?= $fuzziness == 8 ? "selected" : "" ?>>8</option>
                    <option value=9 <?= $fuzziness == 9 ? "selected" : "" ?>>9</option>
                    <option value=10 <?= $fuzziness >= 10 && $fuzziness < 12 ? "selected" : "" ?>>10</option>
                    <option value=12 <?= $fuzziness >= 12 && $fuzziness < 15 ? "selected" : "" ?>>12</option>
                    <option value=15 <?= $fuzziness >= 15 && $fuzziness < 20 ? "selected" : "" ?>>15</option>
                    <option value=20 <?= $fuzziness >= 20 && $fuzziness < 25 ? "selected" : "" ?>>20</option>
                    <option value=25 <?= $fuzziness >= 25 && $fuzziness < 30 ? "selected" : "" ?>>25</option>
                    <option value=30 <?= $fuzziness >= 30 && $fuzziness < 40 ? "selected" : "" ?>>30</option>
                    <option value=40 <?= $fuzziness >= 40 && $fuzziness < 50 ? "selected" : "" ?>>40</option>
                    <option value=50 <?= $fuzziness >= 50 && $fuzziness < 60 ? "selected" : "" ?>>50</option>
                    <option value=60 <?= $fuzziness >= 60 && $fuzziness < 70 ? "selected" : "" ?>>60</option>
                    <option value=70 <?= $fuzziness >= 70 && $fuzziness < 80 ? "selected" : "" ?>>70</option>
                    <option value=80 <?= $fuzziness >= 80 ? "selected" : "" ?>>80</option>
                </select>
                <input type="hidden" name="db" id="db" value="<?=HEURIST_DBNAME?>">
                characters of metaphone must match (larger value = fewer matches)
                <br />
                <br />Cross type matching will attemp to match titles of different record types. This is potentially a long search
                <br />with many matching results. Increasing value above will reduce the number of matches.
                <br />
                <br />
                <input type="checkbox" name="crosstype" id="crosstype" value=1 <?= $crosstype ? "checked" : "" ?>  onclick="form.submit();"> Do record matching across record types<br />
                <input type="checkbox" name="personmatch" id="personmatch" value=1   onclick="form.submit();"> Do person matching by surname first <br />

                <?php
                    unset($_REQUEST['personmatch']);

                    $cnt = 0;
                    foreach ($dupes as $rectype => $subarr) {
                        foreach ($subarr as $index => $key) {
                            $diffHash = array_keys($bibs[$key]);
                            sort($diffHash,SORT_ASC);
                            $diffHash = join(',',$diffHash );
                            if (in_array($diffHash,$dupeDifferences)) continue;
                            $cnt ++;
                        }
                    }

                    print '<p><hr><div><p>There are <b>' . $cnt . '</b> potential groups of duplicates</div>';

                    print "<p><b>ignore in future</b> eliminates the group from future checks. To set this for several groups, ".
                    "check the boxes and then click any of the <b>ignore in future</b> links.".
                    "<br /><b>Merge this group</b> link will ask which members of the group to merge before any changes are made.".
                    "<br />Click on any of the listed records to edit the data for that record";

                    foreach ($dupes as $rectype => $subarr) {
                        foreach ($subarr as $index => $key) {
                            $diffHash = array_keys($bibs[$key]);
                            sort($diffHash,SORT_ASC);
                            $diffHash = join(',',$diffHash );
                            if (in_array($diffHash,$dupeDifferences)) continue;
                            print '<div>';
                            print '<input type="checkbox" name="dupeDiffHash[]" '.
                            'title="Check to idicate that all records in this set are unique." id="'.$key.
                            '" value="' . $diffHash . '">&nbsp;&nbsp;';
                            print '<label style="font-weight: bold;">'.$rectype .'</label>&nbsp;&nbsp;&nbsp;&nbsp;';
                            //print '<input type="submit" value="&nbsp;ignore in future&nbsp;">&nbsp;&nbsp;&nbsp;&nbsp;';

                            print '<a target="fix" href="combineDuplicateRecords.php?db='.HEURIST_DBNAME.
                            '&bib_ids=' . join(',', array_keys($bibs[$key])) . '">merge this group</a>&nbsp;&nbsp;&nbsp;&nbsp;';
                            print '<a target="_new" href="'.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.
                            '&w=all&q=ids:' . join(',', array_keys($bibs[$key])) . '">view as search</a>';

                            print '&nbsp;&nbsp;&nbsp;&nbsp;<a href="#"  onclick="setAsNonDuplication()">ignore in future</a>';

                            print '</div>';
                            print '<ul>';
                            foreach ($bibs[$key] as $rec_id => $vals) {
                                $res = mysql_query('select rec_URL from Records where rec_ID = ' . $rec_id);
                                $row = mysql_fetch_assoc($res);
                                print '<li>'.($crosstype ? $vals['type'].'&nbsp;&nbsp;' : '').
                                '<a target="_new" href="'.HEURIST_BASE_URL.'records/view/viewRecord.php?db='.HEURIST_DBNAME.
                                '&saneopen=1&recID='.$rec_id.'">'.$rec_id.': '.htmlspecialchars($vals['val']).'</a>';
                                if ($row['rec_URL'])
                                    print '&nbsp;&nbsp;&nbsp;<span style="font-size: 70%;">(<a target="_new" href="'.
                                    $row['rec_URL'].'">' . $row['rec_URL'] . '</a>)</span>';
                                print '</li>';
                            }
                            print '</ul>';
                        }
                    }
                ?>
            </form>
        </div>
    </body>
</html>
