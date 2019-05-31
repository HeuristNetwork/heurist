<?php

    /**
    * Recalculates the constructed titles for a specified set of record types, based on data values within the records
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

set_time_limit(0);

define('MANGER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../hsapi/utilities/titleMask.php');


    if(@$_REQUEST['recTypeIDs']) {
        $recTypeIds = $_REQUEST['recTypeIDs'];
    }else{
        header('Location: '.ERROR_REDIR.'&msg='
            .rawurlencode('You must specify a record type (?recTypeIDs=55) or a set of record types (?recTypeIDs=55,174,175) to use this page.'));
        exit();        
    }

$mysqli = $system->get_mysqli();
    
$res = $mysqli->query("select rec_ID, rec_Title, rec_RecTypeID from Records where ! rec_FlagTemporary and rec_RecTypeID in ($recTypeIds) order by rand()");
$recs = array();
if($res){
    while ($row = $res->fetch_assoc() ) {
            $recs[$row['rec_ID']] = $row;
    }
    $res->close();
}
    
$rt_names = mysql__select_assoc2($mysqli, 'select rty_ID, rty_Name from  defRecTypes WHERE rty_ID in ('.$recTypeIds.')');

$masks = mysql__select_assoc2($mysqli, 'select rty_ID, rty_TitleMask from  defRecTypes WHERE rty_ID in ('.$recTypeIds.')');


$updates = array();
$blank_count = 0;
$repair_count = 0;
$processed_count = 0;

?>

<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Recalculation of composite record titles</title>
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>

    <body class="popup">
        <p>
            Rebuilding record titles for <b><?=implode(',',$rt_names)?></b>
        </p>
        <p>This will take some time for large databases</p>

        <script type="text/javascript">
            function update_counts(processed, blank, repair, changed) {
                if(changed==undefined) {
                    changed = 0;
                }

                document.getElementById('processed_count').innerHTML = processed;
                var rec = parseInt("<?=count($recs)?>");
                if(rec>0){
                    document.getElementById('percent').innerHTML = Math.round((100 * processed) / rec);
                }

                document.getElementById('changed_count').innerHTML = changed;
                document.getElementById('same_count').innerHTML = processed - (changed + blank);
                document.getElementById('repair_count').innerHTML = repair;
                document.getElementById('blank_count').innerHTML = blank;
            }

            function update_counts2(processed, total) {
                document.getElementById('updated_count').innerHTML = processed;
                document.getElementById('percent2').innerHTML = Math.round(1000 * processed / total) / 10;
            }

        </script>

        <div><span id=total_count><?=count($recs)?></span> records in total</div>
        <div><span id=processed_count>0</span> processed</div>
        <div><span id=percent>0</span> %</div>

        <br />

        <div><span id=changed_count>0</span> to be updated</div>
        <div><span id=same_count>0</span> are unchanged</div>
        <div><span id=repair_count>0</span> marked for update</div>
        <div><span id=blank_count>0</span> will be left as-is (missing fields etc)</div>

        <?php

            $step_uiupdate = 10;
            if(count($recs)>1000){
                $step_uiupdate = ceil( count($recs) / 100 );
            }

            $blanks = array();
            $reparables = array();
            foreach ($recs as $rec_id => $rec) {
                if ($rec_id % $step_uiupdate == 0) {
                    print '<script type="text/javascript">update_counts('.$processed_count.','.$blank_count.','
                    .$repair_count.','.count($updates).')</script>'."\n";
                    @ob_flush();
                    @flush();
                }

                $mask = $masks[$rec['rec_RecTypeID']];
                $new_title = TitleMask::execute($mask, $rec['rec_RecTypeID'], 0, $rec_id, _ERR_REP_WARN);
                $new_title = trim($new_title);
                
                ++$processed_count;
                $rec_title = trim($rec['rec_Title']);
                if ($new_title && $rec_title && $new_title == $rec_title && strstr($new_title, $rec_title) )  continue;

                if (! preg_match('/^\\s*$/', $new_title)) {     // if new title is blank, leave the existing title
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

                print ' <a target=_blank href="'.HEURIST_BASE_URL.'?fmt=edit&recID='.$rec_id.'&db='.HEURIST_DBNAME.'">*</a> <br> <br>';

                if ($rec_id % $step_uiupdate == 0) {
                    @ob_flush();
                    @flush();
                }
            }

            print '<script type="text/javascript">update_counts('.$processed_count.','.$blank_count.','
            .$repair_count.','.count($updates).')</script>'."\n";
            print '<hr>';

            $titleDT = (defined('DT_NAME')?DT_NAME:0);

            if (count($updates) > 0) {

                $step_uiupdate = 10;
                if(count($updates)>1000){
                    $step_uiupdate = ceil( count($updates) / 100 );
                }

                print '<p>Updating records</p>';
                print '<div><span id=updated_count>0</span> of '.count($updates).' records updated (<span id=percent2>0</span>%)</div>';

                $i = 0;
                foreach ($updates as $rec_id => $new_title) {
                    $mysqli->query('update Records set rec_Modified=rec_Modified, rec_Title="'.
                            $mysqli->real_escape_string($new_title).'" where rec_ID='.$rec_id);
                    ++$i;
                    if ($rec_id % $step_uiupdate == 0) {
                        print '<script type="text/javascript">update_counts2('.$i.','.count($updates).')</script>'."\n";
                        @ob_flush();
                        @flush();
                    }
                }

                print '<script type="text/javascript">update_counts2('.$i.','.count($updates).')</script>'."\n";

                print '<hr><br/><b>DONE</b><br/><br/>';

                print '<a target=_blank href="'.HEURIST_BASE_URL.'?w=all&q=ids:'
                .implode(',', array_keys($updates)).'&db='.HEURIST_DBNAME.'">Click to view updated records</a><br/>&nbsp;<br/>';
            }

            if(count($blanks)>0){
                print '<a target=_blank href="'.HEURIST_BASE_URL.'?w=all&q=ids:'.implode(',', $blanks).'&db='.HEURIST_DBNAME.
                '">Click to view records for which the data would create a blank title</a>'.
                '<br/>This is generally due to a faulty title mask (verify with Check Title Masks)'.
                '<br/>or faulty data in individual records. These titles have not been changed.';
            }

            @ob_flush();
            @flush();

        ?>

        <div style="color: green;padding-top:10px;">
            If the titles of other record types depend on these titles,
            you should run Management > Rebuild record titles to rebuild all record titles in the database
        </div>
    </body>

</html>