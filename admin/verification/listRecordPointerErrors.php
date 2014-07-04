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
?>

<html>

    <head>
        <script type=text/javascript>
            function open_selected() {
                var cbs = document.getElementsByName('recCB');
                if (!cbs  ||  ! cbs instanceof Array)
                    return false;
                var ids = '';
                for (var i = 0; i < cbs.length; i++) {
                    if (cbs[i].checked)
                        ids = ids + cbs[i].value + ',';
                }
                var link = document.getElementById('selected_link');
                if (!link)
                    return false;
                link.href = '../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:' + ids;
                return true;
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
            and rec_RecTypeID not in (dty_PtrTargetRectypeIDs)');
            $bibs = array();
            while ($row = mysql_fetch_assoc($res))
                $bibs[$row['dtl_RecID']] = $row;

        ?>
        <div class="banner">
            <h2>Check for invalid record pointers</h2>
        </div>
        <div id="page-inner">

            These checks look for invalid record pointers within the database. These are generally not serious, but are best eliminated.
            <p> Click the hyperlinked number at the start of each row to open an edit form to change the data for that record. 
                Look for pointer fields in the record which do not display data or which display a warning. </p>

            <hr />

            <div>
                <h3>Records with record pointers to the wrong record type</h3>
                <span><a target=_new href='../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:<?= join(',', array_keys($bibs)) ?>'>
                    (show results as search)</a></span>
            </div>
            <table>
                <?php
                    foreach ($bibs as $row) {
                    ?>
                    <tr>
                        <td><a target=_new 
                                href='../../records/edit/editRecord.html?db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'><?= $row['dtl_RecID'] ?>
                            </a></td>
                        <td><?= $row['dty_Name'] ?></td>
                        <td>points to</td>
                        <td><?= $row['rec_ID'] ?> (<?= $row['rty_Name'] ?>) - <?= substr($row['rec_Title'], 0, 50) ?></td>
                    </tr>
                    <?php
                    }
                ?>
            </table>
            [end of list]
            <p />

            <hr />

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
                        print "<div class='error'>Can not delete invalid pointers from Records.</div>";
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
                while ($row = mysql_fetch_assoc($res))
                    $bibs[$row['dtl_RecID']] = $row;

                if(count($bibs)==0){
                    print "<div><h3>All records have valid pointers</h3></div>";
                    if($wasdeleted>1){
                        print "<div>$wasdeleted invalid pointers were removed from database</div>";    
                    }else if($wasdeleted>0){
                        print "<div>$wasdeleted invalid pointer was removed from database</div>";    
                    }
                }else{
                ?>
                <div>
                    <h3>Records with record pointers to non-existent records</h3>
                    <span>
                        <a target=_new href='../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:<?= join(',', array_keys($bibs)) ?>'>
                            (show results as search)</a>
                        <a target=_new href='#' id=selected_link onClick="return open_selected();">(show selected as search)</a>
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
            <br/><hr/>

        </div>
    </body>
</html>

