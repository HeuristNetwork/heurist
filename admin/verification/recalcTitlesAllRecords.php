<?php

    /**
    * recalcTitlesAllRecords.php
    * Rebuilds the constructed record titles listed in search results, for ALL records (see also recalcTitlesSpecifiedRectypes.php)
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
    * @author      Tom Murtagh
    * @author      Kim Jackson
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Stephen White   
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

    /*
    * TODO: Massive redundancy: This is pretty much identical code to recalcTitlesSopecifiedRectypes.php and should be 
    * combined into one file, or call the same functions to do the work
    */


    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

    if(isForAdminOnly("to rebuild titles")){
        return;
    }

    set_time_limit(0);
    
    mysql_connection_overwrite(DATABASE);

    require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php'); //?db='.HEURIST_DBNAME);

    $res = mysql_query('select rec_ID, rec_Title, rec_RecTypeID from Records where !rec_FlagTemporary order by rand()');
    $recs = array();
    while ($row = mysql_fetch_assoc($res)) {
        $recs[$row['rec_ID']] = $row;
    }


    $masks = mysql__select_assoc('defRecTypes', 'rty_ID', 'rty_TitleMask', '1');
    $updates = array();
    $blank_count = 0;
    $repair_count = 0;
    $processed_count = 0;

    ob_start();
?>

<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <script type="text/javascript">
            function update_counts(processed, blank, repair, changed) {
                if(changed==null || changed==undefined){
                    changed = 0;
                }
                document.getElementById('processed_count').innerHTML = processed;
                document.getElementById('changed_count').innerHTML = changed;
                document.getElementById('same_count').innerHTML = processed - (changed + blank);
                document.getElementById('repair_count').innerHTML = repair;
                document.getElementById('blank_count').innerHTML = blank;
                <!-- TODO: fix error in line below -->
                document.getElementById('percent').innerHTML = Math.round(1000 * processed / <?= count($recs) ?>) / 10;
            }

            function update_counts2(processed, total) {
                document.getElementById('updated_count').innerHTML = processed;
                document.getElementById('percent2').innerHTML = Math.round(1000 * processed / total) / 10;
            }
        </script>

        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
    </head>
    
    <body class="popup">
        <div class="banner"><h2>Rebuild Constructed Record Titles</h2></div>
        <div id="page-inner" style="overflow:auto;padding: 20px;">

            <div style="max-width: 800px;">
                This function recalculates all the constructed (composite) record titles, compares
                them with the existing title and updates the title where the title has
                changed (generally due to changes in the title mask for the record type). 
                At the end of the process it will display a list of records
                for which the titles were changed and a list of records for which the
                new title would be blank (an error condition).
            </div>
            <p>This will take some time for large databases</p>
            <!-- <p>The scanning step does not write to the database and can be cancelled safely at any time</p> -->

            <div><span id=total_count><?=count($recs)?></span> records in total</div>
            <div><span id=processed_count>0</span> processed so far</div>
            <div><span id=percent>0</span> %</div>
            <br />
            <div><span id=changed_count>0</span> to be updated</div>
            <div><span id=same_count>0</span> are unchanged</div>
            <div><span id=repair_count>0</span> marked for update</div>
            <div><span id=blank_count>0</span> will be left as-is (missing fields etc)</div>

            <?php
                flush_buffers();

                $blanks = array();
                $reparables = array();

                $step_uiupdate = 10;
                if(count($recs)>1000){
                    $step_uiupdate = ceil( count($recs) / 100 );
                }

                
                foreach ($recs as $rec_id => $rec) {
                    if ($rec_id % $step_uiupdate == 0) {
                        
                        print '<script type="text/javascript">update_counts('.$processed_count.','.$blank_count.','.$repair_count.
                        ','.count($updates).')</script>'."\n";
                        flush_buffers();
                    }

                    $mask = $masks[$rec['rec_RecTypeID']];
                    $new_title = trim(fill_title_mask($mask, $rec_id, $rec['rec_RecTypeID']));
                    ++$processed_count;
                    $rec_title = trim($rec['rec_Title']);
                    if ($new_title && $rec_title && $new_title == $rec_title && strstr($new_title, $rec_title) )  continue;

                    if (! preg_match('/^\\s*$/', $new_title)) {	// if new title is blank, leave the existing title
                        $updates[$rec_id] = $new_title;
                    }else {
                        if ( $rec['rec_RecTypeID'] == 1 && $rec['rec_Title']) {
                            array_push($reparables, $rec_id);
                            ++$repair_count;
                        }else{
                            array_push($blanks, $rec_id);
                            ++$blank_count;
                        }
                    }
                    continue;

                    if ($new_title == preg_replace('/\\s+/', ' ', $rec['rec_Title']))
                        print '<li class=same>' . htmlspecialchars($new_title) . '<br>'  . htmlspecialchars($rec['rec_Title']) . '';
                    else
                        print '<li>' . htmlspecialchars($new_title) . '<br>'  . htmlspecialchars($rec['rec_Title']) . '';

                    print ' <a target=_blank href="'.HEURIST_BASE_URL.'?fmt=edit&db='.HEURIST_DBNAME.
                        '&recID='.$rec_id.'">*</a> <br> <br>';

                    if ($rec_id % $step_uiupdate == 0) {
                        flush_buffers();
                    }
                }//for

                print '<script type="text/javascript">update_counts('.$processed_count.','.$blank_count.','.
                    $repair_count.','.count($updates).')</script>'."\n";
                print '<hr>';
                $titleDT = (defined('DT_NAME')?DT_NAME:0);

                if (count($updates) > 0) {

                    print '<p>Updating records</p>';
                    print '<div><span id=updated_count>0</span> of '.count($updates).' records updated (<span id=percent2>0</span>%)</div>';

                    $step_uiupdate = 10;
                    if(count($updates)>1000){
                        $step_uiupdate = ceil( count($updates) / 100 );
                    }

                    
                    $i = 0;
                    foreach ($updates as $rec_id => $new_title) {
                        mysql_query('update Records set rec_Modified=rec_Modified, rec_Title="'.mysql_real_escape_string($new_title).'" where rec_ID='.$rec_id);
                        ++$i;
                        if ($rec_id % $step_uiupdate == 0) {
                            print '<script type="text/javascript">update_counts2('.$i.','.count($updates).')</script>'."\n";
                            flush_buffers();
                        }
                    }
                    foreach ($reparables as $rec_id) {
                        $rec = $recs[$rec_id];
                        if ( $rec['rec_RecTypeID'] == 1 && $rec['rec_Title']) {
                            $has_detail_160 = (mysql_num_rows(mysql_query(
                                "select dtl_ID from recDetails where dtl_DetailTypeID = $titleDT and dtl_RecID =". $rec_id)) > 0);
                            //touch the record so we can update it  (required by the heuristdb triggers)
                            mysql_query('update Records set rec_RecTypeID=1 where rec_ID='.$rec_id);
                            if ($has_detail_160) {
                                mysql_query('update recDetails set dtl_Value="' .
                                    $rec['rec_Title'] . "\" where dtl_DetailTypeID = $titleDT and dtl_RecID=".$rec_id);
                            }else{
                                mysql_query('insert into recDetails (dtl_RecID, dtl_Value) VALUES(' .$rec_id . ','.$rec['rec_Title'] . ')');
                            }
                        }
                    }
                    print '<script type="text/javascript">update_counts2('.$i.','.count($updates).')</script>'."\n";

                    print '<hr>';

                    print '<br/>&nbsp;<br/><a target=_blank href="'.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.
                    '&w=all&q=ids:'.join(',', array_keys($updates)).'">Click to view updated records</a><br/>&nbsp;<br/>';
                }
                if(count($blanks)>0){
                    print '<br/>&nbsp;<br/><a target=_blank href="'.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.
                        '&w=all&q=ids:'.join(',', $blanks).
                    '">Click to view records for which the data would create a blank title</a>'.
                    '<br/>This is generally due to faulty title mask (verify with Check Title Masks)<br/>'.
                    'or faulty data in individual records. These titles have not been changed.';
                }

                flush_buffers(false);
            ?>
        </div>
    </body>
</html>
