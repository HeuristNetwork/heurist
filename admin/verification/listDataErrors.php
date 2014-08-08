<?php

    /**
    * listRecordPointerErrors.php: Lists records for which pointer fields point to non-existent records or records of the wrong type
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Tom Murtagh
    * @author      Kim Jackson
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
    require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
    require_once('valueVerification.php');
?>

<html>
    <head>

        <script type=text/javascript>
            function open_selected(sname) {
                var cbs = document.getElementsByName(sname);
                if (!cbs  ||  ! cbs instanceof Array)
                    return false;
                var ids = '';
                for (var i = 0; i < cbs.length; i++) {
                    if (cbs[i].checked)
                        ids = ids + cbs[i].value + ',';
                }
                //var link = document.getElementById('selected_link');
                //if (link) return false;
                window.open('../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:' + ids, '_blank');
                return false;
            }
        </script>

        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
        <style type="text/css">
            h3, h3 span {
                display: inline-block;
                padding:0 0 10px 0;
            }
            Table tr td {
                line-height:2em;
            }
        </style>

    </head>


    <body class="popup">
        <?php

            mysql_connection_select(DATABASE);

            $res = mysql_query('select dtl_RecID, dty_Name, dty_PtrTargetRectypeIDs, rec_ID, rec_Title, rty_Name
                from defDetailTypes
                left join recDetails on dty_ID = dtl_DetailTypeID
                left join Records on rec_ID = dtl_Value
                left join defRecTypes on rty_ID = rec_RecTypeID
                where dty_Type = "resource"
                and dty_PtrTargetRectypeIDs > 0
                and (INSTR(concat(dty_PtrTargetRectypeIDs,','), concat(rec_RecTypeID,',')) = 0)');
            // it does not work and rec_RecTypeID not in (dty_PtrTargetRectypeIDs)');
            $bibs = array();
            while ($row = mysql_fetch_assoc($res))
                $bibs[$row['dtl_RecID']] = $row;
        ?>

        <div class="banner">
            <h2>Check for invalid data (invalid pointers, terms, missing required, excess values etc.)</h2>
        </div>

        <div id="page-inner">

            These checks look for errors in the data within the database. These are generally not serious, but are best eliminated.
            <br /> Click the hyperlinked record ID at the start of each row to open an edit form to change the data for that record.
            <br />Look for red warning texts or pointer fields in the record which do not display data or which display a warning.
            <p>

            <hr />


            <!-- ---- Record pointers which point to non-existant record ------------------------------------------ -->


            <?php

                $wasdeleted = 0;
                if(@$_REQUEST['fixpointers']=="1"){

                    $query = 'delete d from recDetails d
                    left join defDetailTypes dt on dt.dty_ID = d.dtl_DetailTypeID
                    left join Records b on b.rec_ID = d.dtl_Value
                    where dt.dty_Type = "resource"
                    and b.rec_ID is null';
                    $res = mysql_query( $query );
                    if(! $res )
                    {
                        print "<div class='error'>Cannot delete invalid pointers from Records.</div>";
                    }else{
                        $wasdeleted = mysql_affected_rows();
                    }
                }

                $res = mysql_query('select dtl_RecID, dty_Name, a.rec_Title
                    from recDetails
                    left join defDetailTypes on dty_ID = dtl_DetailTypeID
                    left join Records a on a.rec_ID = dtl_RecID
                    left join Records b on b.rec_ID = dtl_Value
                    where dty_Type = "resource"
                    and a.rec_ID is not null
                and b.rec_ID is null');
                $bibs = array();
                $ids = array();
                while ($row = mysql_fetch_assoc($res)) {
                    array_push($bibs, $row);
                    $ids[$row['dtl_RecID']] = 1;
                }

                if(count($bibs)==0){
                    print "<div><h3>All record pointers point to a valid record</h3></div>";
                    if($wasdeleted>1){
                        print "<div>$wasdeleted invalid pointer(s) were removed from database</div>";
                    }
                }
                else
                {
                ?>
                <div>
                    <h3>Records with record pointers to non-existent records</h3>
                    <span>
                        <a target=_new href='../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:<?= join(',', array_keys($ids)) ?>'>
                            (show results as search)</a>
                        <a target=_new href='#' id=selected_link onClick="return open_selected('recCB');">(show selected as search)</a>
                    </span>
                    <div>To fix the inconsistencies, please click here:
                        <button onclick="window.open('listRecordPointerErrors.php?db=<?= HEURIST_DBNAME?>&fixpointers=1','_self')">
                            Delete ALL faulty pointers</button>
                    </div>
                </div>
                <table>
                    <?php
                        foreach ($bibs as $row) {
                        ?>
                        <tr>
                            <td><input type=checkbox name="recCB" value=<?= $row['dtl_RecID'] ?>></td>
                            <td><a target=_new
                                    href='../../records/edit/editRecord.html?db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                                    <?= $row['dtl_RecID'] ?>
                                </a></td>
                            <td><?= $row['rec_Title'] ?></td>
                            <td><?= $row['dty_Name'] ?></td>
                        </tr>
                        <?php
                        }
                        print "</table>\n";
                    ?>
                </table>
                [end of list]
                <?php
                }
            ?>

            <hr/>


            <!-- ---- Record pointers which point to the wrong type of record ------------------------------------------ -->

            <div>
                <?php
                    if (count($bibs == 0)) {
                        print "<h3>All record pointers point to the correct record type</h3>";
                    }
                    else
                    {
                    ?>
                    <h3>Records with record pointers to the wrong record type</h3>
                    <span><a target=_new href='../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:<?= join(',', array_keys($bibs)) ?>'>
                        (show results as search)</a></span>
                    <table>
                        <?php
                            foreach ($bibs as $row) {
                            ?>
                            <tr>
                                <td><a target=_new
                                        href='../../records/edit/editRecord.html?db=<?= HEURIST_DBNAME?>&recID=
                                        <?= $row['dtl_RecID'] ?>'><?= $row['dtl_RecID'] ?>
                                    </a></td>
                                <td><?= $row['dty_Name'] ?></td>
                                <td>points to</td>
                                <td><?= $row['rec_ID'] ?> (<?= $row['rty_Name'] ?>) - <?= substr($row['rec_Title'], 0, 50) ?></td>
                            </tr>
                            <?php
                            }
                        ?>
                    </table>
                    <?php
                    }
                ?>
            </div>


            <hr />


            <!-- ----- Records with term field values which do not exist in the database ------------------- -->

            <?php
                $wasdeleted = 0;

                //remove wrong term IDs
                if(@$_REQUEST['fixterms']=="1"){
                    $query = 'delete d from recDetails d
                    left join defDetailTypes dt on dt.dty_ID = d.dtl_DetailTypeID
                    left join defTerms b on b.trm_ID = d.dtl_Value
                    where dt.dty_Type = "enum" or  dt.dty_Type = "relationtype"
                    and b.trm_ID is null';
                    $res = mysql_query( $query );
                    if(! $res )
                    {
                        print "<div class='error'>Can not delete invalid term values from Records. SQL error: ".mysql_error()."</div>";
                    }else{
                        $wasdeleted = mysql_affected_rows();
                    }
                }

                //find non existing term values
                $res = mysql_query('select dtl_ID, dtl_RecID, dty_Name, a.rec_Title
                    from recDetails
                    left join defDetailTypes on dty_ID = dtl_DetailTypeID
                    left join Records a on a.rec_ID = dtl_RecID
                    left join defTerms b on b.trm_ID = dtl_Value
                    where (dty_Type = "enum" or dty_Type = "relationtype") and dtl_Value is not null
                    and a.rec_ID is not null
                and b.trm_ID is null');
                $bibs = array();
                $ids  = array();
                $dtl_ids = array();
                while ($row = mysql_fetch_assoc($res)){
                    array_push($bibs, $row);
                    $ids[$row['dtl_RecID']] = 1;
                    array_push($dtl_ids, $row['dtl_ID']);
                }

                if(count($bibs)==0){
                    print "<div><h3>All records have recognisable term values</h3></div>";
                    if($wasdeleted>1){
                        print "<div>$wasdeleted invalid term value(s) were removed from database</div>";
                    }
                }
                else
                {
                ?>

                <div>
                    <h3>Records with non-existent term values</h3>
                    <span>
                        <a target=_new href='../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:<?= join(',', array_keys($ids)) ?>'>
                            (show results as search)</a>
                        <a target=_new href='#' id=selected_link onClick="return open_selected('recCB1');">(show selected as search)</a>
                    </span>
                    <div>To fix the inconsistencies, please click here:
                        <button onclick="window.open('listRecordPointerErrors.php?db=<?= HEURIST_DBNAME?>&fixterms=1','_self')">
                            Delete ALL faulty term values</button>
                    </div>
                </div>

                <table>
                    <?php
                        foreach ($bibs as $row) {
                        ?>
                        <tr>
                            <td><input type=checkbox name="recCB1" value=<?= $row['dtl_RecID'] ?>></td>
                            <td><a target=_new
                                    href='../../records/edit/editRecord.html?db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                                    <?= $row['dtl_RecID'] ?>
                                </a></td>
                            <td><?= substr($row['rec_Title'],0,50) ?></td>
                            <td><?= $row['dty_Name'] ?></td>
                        </tr>
                        <?php
                        }
                        print "</table>\n";
                    ?>
                </table>
                <?php
                }
            ?>

            <hr/>


            <!-- ---- Records containing fields with terms not in the list of terms specified for the field  --------------- -->


            <?php

                $res = mysql_query('select dtl_ID, dtl_RecID, dty_Name, dtl_Value, dty_ID, dty_JsonTermIDTree, dty_TermIDTreeNonSelectableIDs, rec_Title
                    from Records, recDetails, defDetailTypes
                    where rec_ID = dtl_RecID and dty_ID = dtl_DetailTypeID and (dty_Type = "enum" or  dty_Type = "relationtype")
                    and dtl_Value is not null
                order by dtl_DetailTypeID');
                /*
                'select dtl_RecID, dty_Name, dty_JsonTermIDTree, dty_TermIDTreeNonSelectableIDs, rec_Title, dtl_Value, dty_ID
                from defDetailTypes
                left join recDetails on dty_ID = dtl_DetailTypeID
                left join Records on rec_ID = dtl_RecID
                where dty_Type = "enum" or  dty_Type = "relationtype"
                order by dtl_DetailTypeID'*/
                $bibs = array();
                $ids = array();
                while ($row = mysql_fetch_assoc($res)){
                    //verify value
                    if(  !in_array($row['dtl_ID'], $dtl_ids) &&
                        trim($row['dtl_Value'])!="" &&
                        isInvalidTerm($row['dty_JsonTermIDTree'], $row['dty_TermIDTreeNonSelectableIDs'], $row['dtl_Value'], $row['dty_ID'] ))
                    {
                        array_push($bibs, $row);
                        $ids[$row['dtl_RecID']] = 1;
                    }

                }
            ?>

            <div>

                <?php
                    if (count($bibs == 0)) {
                        print "<h3>All records have valid terms (terms are as specified for each field)</h3>";
                    }
                    else
                    {
                    ?>
                    <h3>Records with terms not in the list of terms specified for the field</h3>
                    <span><a target=_new href='../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:<?= join(',', array_keys($ids)) ?>'>
                        (show results as search)</a></span>

                    <table>
                        <tr>
                            <th style="width: 30px;">Record</th>
                            <th style="width: 60px;">Field</th>
                            <th style="width: 60px;">Terms</th>
                            <th>Record title</th>
                        </tr>

                        <?php
                            foreach ($bibs as $row) {
                            ?>
                            <tr>
                                <td style="width:50px; padding-left: 25px;">
                                    <a target=_new
                                        href='../../records/edit/editRecord.html?db=<?= HEURIST_DBNAME?>&recID=
                                        <?= $row['dtl_RecID'] ?>'><?= $row['dtl_RecID'] ?>
                                    </a>
                                </td>
                                <td style="width: 60px;padding-left: 25px;"><?= $row['dty_Name'] ?></td>
                                <!-- >Artem TODO: Need to render the value as the term label, not the numeric value -->
                                <td style="width: 60px;padding-left: 25px;"><?= $row['dtl_Value'] ?></td>
                                <td style="padding-left: 25px;"><?= substr($row['rec_Title'], 0, 500) ?></td>
                            </tr>
                            <?php
                            }
                        ?>
                    </table>
                    <?php
                    }
                ?>
            </div>



            <hr />


            <!-- ----- single value fields containing excess values ------------------------------------------------------------- -->


            <?php

                $res = mysql_query('select dtl_RecID, rec_RecTypeID, dtl_DetailTypeID, rst_DisplayName, rec_Title, count(*)
                    from recDetails, Records, defRecStructure
                    where rec_ID = dtl_RecID and rst_RecTypeID = rec_RecTypeID and rst_DetailTypeID = dtl_DetailTypeID
                    and rst_MaxValues=1
                    GROUP BY dtl_RecID, rec_RecTypeID, dtl_DetailTypeID, rst_DisplayName, rec_Title
                HAVING COUNT(*) > 1');

                $bibs = array();
                $ids = array();
                while ($row = mysql_fetch_assoc($res)){
                    array_push($bibs, $row);
                    $ids[$row['dtl_RecID']] = 1;
                }

                if(count($bibs)==0){
                    print "<h3>No single value fields exceed 1 value</h3>";
                }
                else
                {
                ?>

                <div>
                    <h3>Single value fields with multiple values</h3>
                    <span>
                        <a target=_new href='../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:<?= join(',', array_keys($ids)) ?>'>
                            (show results as search)</a>
                        <a target=_new href='#' id=selected_link2 onClick="return open_selected('recCB2');">(show selected as search)</a>
                    </span>
                </div>

                <table>
                    <?php
                        $rec_id = null;
                        foreach ($bibs as $row) {
                            if($rec_id!=$row['dtl_RecID']) {
                            ?>
                            <tr>
                                <td>
                                    <input type=checkbox name="recCB2" value=<?= $row['dtl_RecID'] ?>>
                                </td>
                                <td>
                                    <a target=_blank href='../../records/edit/editRecord.html?db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                                    <?= $row['dtl_RecID'] ?></a>
                                </td>
                                <td><?= $row['rec_Title'] ?>
                                </td>
                                <?php
                                    $rec_id = $row['dtl_RecID'];
                                }else{
                                    print '<tr><td colspan="3"></td>';
                                }
                            ?>
                            <td><?= $row['rst_DisplayName'] ?>
                            </td>
                        </tr>
                        <?php
                        }
                        print "</table>";
                    ?>
                </table>

                <?php
                }
            ?>

            <hr/>


            <!-- ------ records with missing required values ------------------------------------------------------------------ -->


            <?php

                $res = mysql_query("select rec_ID, rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, dtl_Value, rec_Title
                    from Records
                    left join defRecStructure on rst_RecTypeID = rec_RecTypeID
                    left join recDetails on rec_ID = dtl_RecID and rst_DetailTypeID = dtl_DetailTypeID
                    where rst_RequirementType='required' and (dtl_Value is null or dtl_Value='')
                    and dtl_UploadedFileID is null and dtl_Geo is null
                order by rec_ID");

                $bibs = array();
                $ids = array();
                while ($row = mysql_fetch_assoc($res)){
                    array_push($bibs, $row);
                    $ids[$row['rec_ID']] = $row;
                }

                if(count($bibs)==0){
                    print "<div><h3>No required fields with missing or empty values</h3></div>";
                }
                else
                {
                ?>

                <div>
                    <h3>Records with missing or empty required values</h3>
                    <span>
                        <a target=_new href='../../search/search.html?db=<?= HEURIST_DBNAME?>&amp;w=all&amp;q=ids:<?= join(',', array_keys($ids)) ?>'>
                            (show results as search)</a>
                        <a target=_new href='#' id=selected_link3 onClick="return open_selected('recCB3');">(show selected as search)</a>
                    </span>
                </div>

                <table>
                    <?php
                        $rec_id = null;
                        foreach ($bibs as $row) {
                            if($rec_id!=$row['rec_ID']) {
                            ?>
                            <tr>
                                <td>
                                    <input type=checkbox name="recCB3" value=<?= $row['rec_ID'] ?>>
                                </td>
                                <td>
                                    <a target=_new
                                        href='../../records/edit/editRecord.html?db=<?= HEURIST_DBNAME?>&recID=<?= $row['rec_ID'] ?>'>
                                        <?= $row['rec_ID'] ?>
                                    </a>
                                </td>
                                <td>
                                <?= $row['rec_Title'] ?></td>
                                <?php
                                    $rec_id = $row['rec_ID'];
                                }else{
                                    print '<tr><td colspan="3"></td>';
                                }
                            ?>
                            <td><?= $row['rst_DisplayName'] ?></td>
                        </tr>
                        <?php
                        }
                        print "</table>";
                    ?>
                </table>
                <?php
                }
            ?>


            <hr/>


            <!-- ------ Recordds with non-standard fields (not listed in recstructure) -------------------------------------------- -->


            <?php

                $res = mysql_query("select rec_ID, rec_RecTypeID, dty_ID, dty_Name, dtl_Value, rec_Title
                    from Records
                    left join recDetails on rec_ID = dtl_RecID
                    left join defDetailTypes on dty_ID = dtl_DetailTypeID
                    left join defRecStructure on rst_RecTypeID = rec_RecTypeID and rst_DetailTypeID = dtl_DetailTypeID
                    where rst_ID is null
                ");

                $bibs = array();
                $ids = array();
                while ($row = mysql_fetch_assoc($res)){
                    array_push($bibs, $row);
                    $ids[$row['rec_ID']] = $row;
                }

            ?>

            <div>
                <?php
                    if (count($bibs == 0)) {
                        print "<h3>No extraneous fields (fields not defined in the list for the record type)</h3>";
                    }
                    else
                    {
                    ?>
                    <h3>Records with extraneous fields (not defined in the list of fields for the record type)</h3>
                    <span>
                        <a target=_new href='../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:<?= join(',', array_keys($ids)) ?>'>
                            (show results as search)</a>
                        <a target=_new href='#' id=selected_link4 onClick="return open_selected('recCB4');">(show selected as search)</a>
                    </span>
                    <table>
                        <?php
                            $rec_id = null;
                            foreach ($bibs as $row) {
                                if($rec_id==null || $rec_id!=$row['rec_ID']) {
                                ?>
                                <tr>
                                    <td><input type=checkbox name="recCB4" value=<?= $row['rec_ID'] ?>>
                                    </td>
                                    <td>
                                        <a target=_new
                                            href='../../records/edit/editRecord.html?db=<?= HEURIST_DBNAME?>&recID=<?= $row['rec_ID'] ?>'>
                                            <?= $row['rec_ID'] ?>
                                        </a>
                                    </td>
                                    <!-- td><?= $row['rec_RecTypeID'] ?></td -->
                                    <td width="400px"><?= substr($row['rec_Title'],0,100)?>
                                    </td>
                                    <?php
                                        $rec_id = $row['rec_ID'];
                                    }else{
                                        print '<tr><td colspan="3"></td>';
                                    }
                                ?>

                                <td><?= $row['dty_ID'] ?></td>
                                <td><?= $row['dty_Name'] ?></td>
                                <td><?= $row['dtl_Value'] ?></td>
                            </tr>
                            <?php
                            }
                            print "</table>";
                        ?>
                    </table>
                    <?php
                    }
                ?>
            </div>

            <hr/>

        </div>
    </body>
</html>
