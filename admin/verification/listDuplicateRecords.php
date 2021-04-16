<?php

    /**
    * listDuplicateRecords.php: Lists groups of potential duplicated and allows various actions -
    * ignoring groups in future, merging group members, editing records
    * 
    * see admin menu
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
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

    define('MANAGER_REQUIRED',1);   
    define('PDIR','../../');  //need for proper path to js and css    

    require_once(dirname(__FILE__).'/../../hclient/framecontent/initPage.php');

    $fuzziness = intval($_REQUEST['fuzziness']);
    if (! $fuzziness) $fuzziness = 10;

    $bibs = array();
    $dupes = array();
    $dupekeys = array();
    $recsGivenNames = array();
    $dupeDifferences = array(); //Similar But Not Dupes

    $mysqli = $system->get_mysqli();
    
    $dupeDifferences = mysql__select_list2($mysqli, 'select snd_SimRecsList from recSimilarButNotDupes');

    if (@$_REQUEST['dupeDiffHash']){ //add new non duplications
        foreach($_REQUEST['dupeDiffHash'] as $diffHash){
            if (! in_array($diffHash,$dupeDifferences)){
                array_push($dupeDifferences,$diffHash);
                
                $res = $mysqli->query('insert into recSimilarButNotDupes values("'.$diffHash.'")');
            }
        }
    }
    
/*    
    parameters:
    
    fuzziness - number of characters
    
    personmatch - if true it checks only RT_PERSON by DT_GIVEN_NAMES and DT_GIVEN_NAMES
                  otherwise by rec_Title  
                
    crosstype  - if false check only records of the same type

    
    1. Retrieve all non temp records 
    2. It creates metaphone key (exclude european articles) - the same key for 
       similar sounding words based on rules of English pronunciation. 
       The size of key (number of chars) is defined by “fuzziness” parameter
    
    3. Fills $bibs[metaphone key] = array(rectype, val) 
    
    4. If such metaphone key already exists adds it to $dupes[$typekey] and $dupekeys
    
*/    

    $crosstype = false;
    $personMatch = false;

    $relRT = ($system->defineConstant('RT_RELATION')?RT_RELATION:0);
    $perRT = ($system->defineConstant('RT_PERSON')?RT_PERSON:0);
    $surnameDT = ($system->defineConstant('DT_GIVEN_NAMES')?DT_GIVEN_NAMES:0);
    $titleDT = ($system->defineConstant('DT_NAME')?DT_NAME:0);

    if (@$_REQUEST['crosstype']){
        $crosstype = true;
    }
    if (@$_REQUEST['personmatch']){
        $personMatch = true;
        
        $recsGivenNames = mysql__select_assoc2($mysqli, "select rec_ID, dtl_Value from Records left join recDetails on dtl_RecID=rec_ID and dtl_DetailTypeID=$surnameDT where rec_RecTypeID = $perRT and not rec_FlagTemporary order by rec_ID desc");

        $res = $mysqli->query("select rec_ID, rec_RecTypeID, rec_Title, dtl_Value from Records left join recDetails on dtl_RecID=rec_ID and dtl_DetailTypeID=$titleDT where rec_RecTypeID = $perRT and not rec_FlagTemporary order by dtl_Value asc");    //Family Name

    } else{
        //$res = $mysqli->query("select rec_ID, rec_RecTypeID, rec_Title, dtl_Value from Records left join recDetails on dtl_RecID=rec_ID ///and dtl_DetailTypeID=$titleDT where rec_RecTypeID != $relRT and not rec_FlagTemporary order by rec_RecTypeID desc");
        
        $res = $mysqli->query("select rec_ID, rec_RecTypeID, rec_Title from Records "
            ." where rec_RecTypeID != $relRT and not rec_FlagTemporary order by rec_RecTypeID desc");
        
    }

    //rectype names
    $rectypes = mysql__select_assoc2($mysqli, 'select rty_ID, rty_Name from defRecTypes'); 

    if($res){
        while ($row = $res->fetch_assoc()) {
            if ($personMatch){
                if($row['dtl_Value']) $val = $row['dtl_Value'] 
                                . ($recsGivenNames[$row['rec_ID']]? " "
                                . $recsGivenNames[$row['rec_ID']]: "" );
            }else {
                if ($row['rec_Title']) $val = $row['rec_Title'];
                else continue; //$val = $row['dtl_Value'];
            }
            
            //strip out european articles
            $val2 = preg_replace('/^(?:a|an|the|la|il|le|die|i|les|un|der|gli|das|zur|una|ein|eine|lo|une)\\s+|^l\'\\b/i', '', $val);
            
            //creates metaphone key (exclude european articles)
            if(true || $mode=='metaphone'){

                $mval = metaphone($val2);
                $key = mb_substr($mval, 0, $fuzziness);
           
            }
            
            

            if ($crosstype || $personMatch) { //for crosstype or person matching leave off the type ID
                $key = ''.$key;
            } else {
                $key = $row['rec_RecTypeID'] . '.' . $key;
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
        $res->close();
    }

    ksort($dupes); //Sorts by record types
    foreach ($dupes as $typekey => $subarr) {
        //sorts methapone keys
        array_multisort($dupes[$typekey],SORT_ASC,SORT_STRING);
    }

?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Find Duplicate Records</title>
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />  <!-- base css -->
        <style>
            A:link, A:visited {color: #6A7C99;}
        </style>
    </head>

    <body class="popup" style="overflow:auto;">
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
            
            
            function fixDuplicatesPopup(sRecIds, sGroupID){

                var url = window.hWin.HAPI4.baseURL
                        + 'admin/verification/combineDuplicateRecords.php?bib_ids='
                        + sRecIds
                        + '&db=' + window.hWin.HAPI4.database;
                
                window.hWin.HEURIST4.msg.showDialog(url, {
                    width:700, height:600,
                    default_palette_class:'ui-heurist-explore',
                    title: window.hWin.HR('Combine duplicate records'),
                    callback: function(context) {
                            if(context=='commited'){
                                $('.group_'+sGroupID).hide();
                            }
                    }
                });
                
                return false;
            }
            
        </script>

        <div class="banner"><h2 style="padding:10px">Find Duplicate Records</h2></div>
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
                <input type="checkbox" name="crosstype" id="crosstype" value=1 <?= $crosstype ? "checked" : "" ?>  onclick="form.submit();"> Do record matching across record types<br /><br />
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

                    print '<p><br /><hr><br /><div><p>There are <b>' . $cnt . '</b> potential groups of duplicates</div>';

                    print "<p><b>ignore in future</b> eliminates the group from future checks. To set this for several groups, ".
                    "check the boxes and then click any of the <b>ignore in future</b> links.".
                    "<br /><b>Merge this group</b> link will ask which members of the group to merge before any changes are made.".
                    "<br />Click on any of the listed records to edit the data for that record";

                    $unique_group_id = 1;
                    
                    foreach ($dupes as $rectype => $subarr) {
                        foreach ($subarr as $index => $key) {
                            $diffHash = array_keys($bibs[$key]);
                            sort($diffHash,SORT_ASC);
                            $diffHash = join(',',$diffHash );
                            if (in_array($diffHash,$dupeDifferences)) continue; //similar but not dupes
                            print '<div style="padding: 10px 20px;" class="group_'.$unique_group_id.'">';
                            print '<input type="checkbox" name="dupeDiffHash[]" '.
                            'title="Check to indicate that all records in this set are unique." id="'.$key.
                            '" value="' . $diffHash . '">&nbsp;&nbsp;';
                            print '<label style="font-weight: bold;">'.$rectype .'</label>&nbsp;&nbsp;&nbsp;&nbsp;';
                            //print '<input type="submit" value="&nbsp;ignore in future&nbsp;">&nbsp;&nbsp;&nbsp;&nbsp;';

                            print '<a href="#" onclick="{return fixDuplicatesPopup(\''
                                .implode(',',array_keys($bibs[$key]))
                                .'\', '.$unique_group_id.');}">merge this group</a>&nbsp;&nbsp;&nbsp;&nbsp;';
                            
                            print '<a target="_new" href="'.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.
                            '&w=all&q=ids:' . join(',', array_keys($bibs[$key])) . '">view as search</a>';

                            print '&nbsp;&nbsp;&nbsp;&nbsp;<a href="#"  onclick="setAsNonDuplication()">ignore in future</a>';

                            print '&nbsp;&nbsp;metaphone key:'.$key;
                            
                            print '</div>';
                            print '<ul style="padding: 10px 30px;" class="group_'.$unique_group_id.'">';
                            foreach ($bibs[$key] as $rec_id => $vals) {
                                $recURL = mysql__select_value($mysqli, 'select rec_URL from Records where rec_ID = ' . $rec_id);
                                
                                print '<li>'.($crosstype ? $vals['type'].'&nbsp;&nbsp;' : '').
                                '<a target="_new" href="'.HEURIST_BASE_URL.'viewers/record/viewRecord.php?db='.HEURIST_DBNAME.
                                '&saneopen=1&recID='.$rec_id.'">'.$rec_id.': '.htmlspecialchars($vals['val']).'</a>';
                                if ($recURL)
                                    print '&nbsp;&nbsp;&nbsp;<span style="font-size: 70%;">(<a target="_new" href="'.
                                    $recURL.'">' . $recURL . '</a>)</span>';
                                print '</li>';
                            }
                            print '</ul>';
                            
                            $unique_group_id++;
                        }
                    }
                ?>
            </form>
        </div>
    </body>
</html>
