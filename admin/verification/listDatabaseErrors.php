<?php

/**
* listDatabaseErrors.php: Lists structural errors and records with errors:
* invalid term codes, field codes, record types in pointers
* pointer fields point to non-existent records or records of the wrong type
*   single value fields with multiple values
*   required fields with no value
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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
ini_set('max_execution_time', 0);

define('MANAGER_REQUIRED',1);
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_structure.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_records.php');
require_once(dirname(__FILE__).'/../../hsapi/structure/dbsTerms.php');

$mysqli = $system->get_mysqli();

require_once(dirname(__FILE__).'/../../common/php/Temporal.php');
require_once('verifyValue.php');
require_once('verifyFieldTypes.php');


$system->defineConstant('DT_RELATION_TYPE');


if(@$_REQUEST['data']){
    $lists = json_decode($_REQUEST['data'], true);
    
    $lists2 = $lists;
    
}else{
    $lists = getInvalidFieldTypes($mysqli, @$_REQUEST['rt']); //in getFieldTypeDefinitionErrors.php
    if(!@$_REQUEST['show']){
        if(count($lists["terms"])==0 && count($lists["terms_nonselectable"])==0
        && count($lists["rt_contraints"])==0  && count($lists["rt_defvalues"])==0){
            $lists = array();
        }
    }
    
    $lists2 = getTermsWithIssues($mysqli);
}

//remove Records those which have been forwarded and still exist with no values
$query = 'select rec_ID FROM Records left join recDetails on rec_ID=dtl_RecID, recForwarding '
.' WHERE (dtl_RecID is NULL) AND (rec_ID=rfw_OldRecID)';
$recids = mysql__select_list2($mysqli, $query);
if(count($recids)>0){
    recordDelete($system, $recids); 
}

//see getInvalidFieldTypes in verifyFieldTypes.php
$dtysWithInvalidTerms = @$lists["terms"];
$dtysWithInvalidNonSelectableTerms = @$lists["terms_nonselectable"];
$dtysWithInvalidRectypeConstraint = @$lists["rt_contraints"];

$rtysWithInvalidDefaultValues = @$lists["rt_defvalues"];

$trmWithWrongParents = @$lists2["trm_missed_parents"];
$trmWithWrongInverse = @$lists2["trm_missed_inverse"];
$trmDuplicates = @$lists2["trm_dupes"];

?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

        <!-- CSS -->
        <?php include dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php'; ?>

        <script type=text/javascript>

            function get_selected_by_name(sname) {
                var cbs = document.getElementsByName(sname);
                if (!cbs  ||  ! cbs instanceof Array)
                    return false;
                var ids = '';
                for (var i = 0; i < cbs.length; i++) {
                    if (cbs[i].checked)
                        ids = ids + cbs[i].value + ',';
                }
                return ids;
            }

            function mark_all_by_name(ele,  sname){
                var cbs = document.getElementsByName(sname);
                if (!cbs  ||  ! cbs instanceof Array)
                    return false;

                var is_checked = $(ele).is(':checked');

                for (var i = 0; i < cbs.length; i++) {
                    cbs[i].checked = is_checked;
                }
            }

            function open_selected_by_name(sname) {
                var ids = get_selected_by_name( sname );
                //var link = document.getElementById('selected_link');
                //if (link) return false;
                if(ids){
                    window.open('<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:' + ids, '_blank');
                }
                return false;
            }

            function open_selected() {
                var cbs = document.getElementsByName('bib_cb');
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
                link.href = '<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:' + ids;
                return true;
            }

            function onEditFieldType(dty_ID){

                var sURL = "<?=HEURIST_BASE_URL?>admin/structure/fields/editDetailType.html?db=<?= HEURIST_DBNAME?>";
                if(dty_ID>0){
                    sURL = sURL + "&detailTypeID="+dty_ID; //existing
                }else{
                    return;
                }

                var body = $(window.hWin.document).find('body');
                var dim = {h:body.innerHeight(), w:body.innerWidth()};

                window.hWin.HEURIST4.msg.showDialog(sURL, {
                    "close-on-blur": false,
                    "no-resize": false,
                    height: dim.h*0.9,
                    width: 860,
                    callback: function(context) {
                    }
                });
            }

            function onEditRtStructure(rty_ID){

                window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                    {new_record_params:{RecTypeID: rty_ID}, edit_structure:true});
                /*
                var url = window.hWin.HAPI4.baseURL + 'admin/structure/fields/editRecStructure.html?db='
                + window.hWin.HAPI4.database
                + '&rty_ID='+rty_ID;

                var body = $(window.hWin.document).find('body');

                window.hWin.HEURIST4.msg.showDialog(url, {
                height: body.innerHeight()*0.9,
                width: 860,
                padding: '0px',
                title: window.hWin.HR('Edit record structure'),
                callback: function(context){
                }
                });        
                */
            }



            $(document).ready(function() {
                $('button').button();
                $('#linkbar').tabs();
            });
        </script>

        <style type="text/css">
            h3, h3 span {
                display: inline-block;
                padding:0 0 10px 0;
            }
            Table tr td {
                line-height:2em;
            }
            A:link {
                color: #6A7C99;    
            }
            h3.res-valid{
                color: #6aa84f !important;        
            }
        </style>

    </head>


    <body class="popup" style="overflow:auto">

        <div class="banner">
            <h2>Check for invalid definitions and data (invalid pointers, terms, missing required, excess values etc.)</h2>
        </div>

        <div style="padding-top:20px">
            These checks look for errors in the structure of the database and errors in the data within the database. These are generally not serious, but are best eliminated.
            <br /> Click the hyperlinked record ID at the start of each row to open an edit form to change the data for that record.
            <br />Look for red warning texts or pointer fields in the record which do not display data or which display a warning.
        </div>

        <hr style="margin-top:15px">
        <div id="linkbar">
            <ul>
                <li class="owner_ref"><a href="#owner_ref" style="white-space:nowrap;padding-right:10px;color:black;">Record Owner/Creator</a></li>
                <li class="dup_terms"><a href="#dup_terms" style="white-space:nowrap;padding-right:10px;color:black;">Invalid/Duplicate Terms</a></li>
                <li class="field_type"><a href="#field_type" style="white-space: nowrap;padding-right:10px;color:black;">Field Types</a></li>
                <li class="default_values"><a href="#default_values" style="white-space: nowrap;padding-right:10px;color:black;">Default Values</a></li>
                <li class="pointer_targets"><a href="#pointer_targets" style="white-space: nowrap;padding-right:10px;color:black;">Pointer Targets</a></li>
                <li class="target_types"><a href="#target_types" style="white-space: nowrap;padding-right:10px;color:black;">Target Types</a></li>
                <li class="target_parent"><a href="#target_parent" style="white-space: nowrap;padding-right:10px;color:black;">Invalid Parents</a></li>
                <li class="empty_fields"><a href="#empty_fields" style="white-space: nowrap;padding-right:10px;color:black;">Empty Fields</a></li>
                <li class="date_values"><a href="#date_values" style="white-space: nowrap;padding-right:10px;color:black;">Date Values</a></li>
                <li class="term_values"><a href="#term_values" style="white-space: nowrap;padding-right:10px;color:black;">Term Values</a></li>
                <li class="expected_terms"><a href="#expected_terms" style="white-space: nowrap;padding-right:10px;color:black;">Expected Terms</a></li>
                <li class="single_value"><a href="#single_value" style="white-space: nowrap;padding-right:10px;color:black;">Single Value Fields</a></li>
                <li class="required_fields"><a href="#required_fields" style="white-space: nowrap;padding-right:10px;color:black;">Required Fields</a></li>
                <li class="nonstandard_fields"><a href="#nonstandard_fields" style="white-space: nowrap;padding-right:10px;color:black;">Non-Standard Fields</a></li>
                <!-- <a href="#origin_differences" style="white-space: nowrap;padding-right:10px">Differences with Core Definitions</a> -->
                <li class="invalid_chars"><a href="#invalid_chars" style="white-space: nowrap;padding-right:10px;color:black;">Invalid Characters</a></li>
                <li class="title_mask"><a href="#title_mask" style="white-space: nowrap;padding-right:10px;color:black;">Title Masks</a></li>
            </ul>
			
            <!-- Records with by non-existent users -->
            <div id="owner_ref" style="top:110px">  <!-- End of Owner References -->
            
            <?php
            //            flush_buffers();
			
            $wasassigned1 = 0;
            $wasassigned2 = 0;
            if(@$_REQUEST['fixusers']=="1"){
                $mysqli->query('SET SQL_SAFE_UPDATES=0');

                $query = 'UPDATE Records left join sysUGrps on rec_AddedByUGrpID=ugr_ID '
                .' SET rec_AddedByUGrpID=2 WHERE ugr_ID is null';
                $res = $mysqli->query( $query );
                if(! $res )
                {
                    print "<div class='error'>Cannot delete invalid pointers from Records.</div>";
                }else{
                    $wasassigned1 = $mysqli->affected_rows;
                }

                $query = 'UPDATE Records left join sysUGrps on rec_OwnerUGrpID=ugr_ID '
                .' SET rec_AddedByUGrpID=2 WHERE ugr_ID is null';
                $res = $mysqli->query( $query );
                if(! $res )
                {
                    print "<div class='error'>Cannot delete invalid pointers from Records.</div>";
                }else{
                    $wasassigned2 = $mysqli->affected_rows;
                }

                $mysqli->query('SET SQL_SAFE_UPDATES=1');
            }

            $wrongUser_Add = 0;
            $wrongUser_Owner = 0;

            $res = $mysqli->query('SELECT count(distinct rec_ID) FROM Records left join sysUGrps on rec_AddedByUGrpID=ugr_ID where ugr_ID is null');
            $row = $res->fetch_row();
            if($row && $row[0]>0){
                $wrongUser_Add = $row[0];
            }
            $res = $mysqli->query('SELECT count(distinct rec_ID) FROM Records left join sysUGrps on rec_OwnerUGrpID=ugr_ID where ugr_ID is null');
            $row = $res->fetch_row();
            if($row && $row[0]>0){
                $wrongUser_Owner = $row[0];
            }

            if($wrongUser_Add==0 && $wrongUser_Owner==0){
                print '<div><h3 class="res-valid">OK: All record have valid Owner and Added by User references</h3></div>';
                echo '<script>$(".owner_ref").css("background-color", "#6AA84F");</script>';
                if($wasassigned1>0){
                    print "<div>$wasassigned1 records 'Added by' value were set to user # 2 Database Manager</div>";
                }
                if($wasassigned2>0){
                    print "<div>$wasassigned2 records were attributed to owner # 2 Database Manager</div>";
                }
            }
            else
            {
                echo '<script>$(".owner_ref").css("background-color", "#E60000");</script>';
                print '<div>';
                if($wrongUser_Add>0){
                    print '<h3>'.$wrongUser_Add.' records are owned by non-existent users</h3>';
                }
                if($wrongUser_Owner>0){
                    print '<h3>'.$wrongUser_Owner.' records are owned by non-existent users</h3>';
                }
                ?>
                <button onclick="window.open('listDatabaseErrors.php?db=<?= HEURIST_DBNAME?>&fixusers=1','_self')">
                    Attribute them to owner # 2 Database Manager</button>
                </div>
            <?php
            }
            ?>
                <br />
            </div>  <!-- End of Owner References -->

        <!-- WRONG OR possible DUPLICATED TERMS -->
        <div id="dup_terms" style="top:110px">  <!-- Start of Duplicate Terms -->
        
        <?php

            $wasassigned1 = 0;
            $wasassigned2 = 0;
            if(@$_REQUEST['fixterms']=="1"){
                $mysqli->query('SET SQL_SAFE_UPDATES=0');
                
                if(count($trmWithWrongParents)>0){
                
                    $trash_group_id = mysql__select_value($mysqli, 'select vcg_ID from defVocabularyGroups where vcg_Name="Trash"');

                    $query = 'UPDATE defTerms set trm_ParentTermID=NULL, trm_VocabularyGroupID='.$trash_group_id
                    .' WHERE trm_ID in ('.implode(',',$trmWithWrongParents).')';
                    $res = $mysqli->query( $query );
                    if(! $res )
                    {
                        print "<div class='error'>Cannot delete invalid pointers to parent terms.</div>";
                    }else{
                        $wasassigned1 = $mysqli->affected_rows;
                        $trmWithWrongParents = array();
                    }
                }

                if(count($trmWithWrongInverse)>0){
                    $query = 'UPDATE defTerms set trm_InverseTermId=NULL '
                    .' WHERE trm_ID in ('.implode(',',$trmWithWrongInverse).')';
                    $res = $mysqli->query( $query );
                    if(! $res )
                    {
                        print "<div class='error'>Cannot clear missed inverse terms ids.</div>";
                    }else{
                        $wasassigned2 = $mysqli->affected_rows;
                        $trmWithWrongInverse = array();
                    }
                }

                $mysqli->query('SET SQL_SAFE_UPDATES=1');
            }

            if(count($trmWithWrongParents)==0 && count($trmWithWrongInverse)==0){
                print '<div><h3 class="res-valid">OK: All terms have valid inverse and parent term references</h3></div>';
                echo '<script>$(".dup_terms").css("background-color", "#6AA84F");</script>';
                if($wasassigned1>0){
                    print "<div>$wasassigned1 terms with wrong parent terms moved to 'Trash' group as vocabularies</div>";
                }
                if($wasassigned2>0){
                    print "<div>$wasassigned2 wrong inverse term references are cleared</div>";
                }
            }
            else
            {
                echo '<script>$(".dup_terms").css("background-color", "#E60000");</script>';
                print '<div>';
                if(count($trmWithWrongParents)>0){
                    
                    print '<h3>'.count($trmWithWrongParents).' terms have wrong parent term references</h3>';
                    $cnt = 0;
                    foreach ($trmWithWrongParents as $trm_ID) {
                        print '<br>'.$trm_ID.'  '.$TL[$trm_ID]['trm_Label'];
                        if($cnt>30){
                          print '<br>'.count($trmWithWrongParents)-$cnt.' more...';      
                          break;  
                        } 
                        $cnt++;
                    }
                }
                if(count($trmWithWrongInverse)>0){
                    
                    print '<h3>'.count($trmWithWrongInverse).' terms have wrong inverse term references</h3>';
                    $cnt = 0;
                    foreach ($trmWithWrongInverse as $trm_ID) {
                        print '<br>'.$trm_ID.'  '.$TL[$trm_ID]['trm_Label'];
                        if($cnt>30){
                          print '<br>'.count($trmWithWrongInverse)-$cnt.' more...';      
                          break;  
                        } 
                        $cnt++;
                    }
                }
                
                ?>
                <br><br>
                <button onclick="window.open('listDatabaseErrors.php?db=<?= HEURIST_DBNAME?>&fixterms=1','_self')">
                    Correct wrong parent and inverse term references</button>
                </div>
            <?php
        }
        
        if(count($trmDuplicates)>0){                                                 
            echo '<script>$(".dup_terms").css("background-color", "#E60000");</script>';

            print '<h3>Terms are duplicated or ending in a number: these may be the result of automatic duplicate-avoidance. '
            .'If so, we suggest deleting the numbered term or using Design > Vocabularies to merge it with the un-numbered version.</h3>';
            foreach ($trmDuplicates as $parent_ID=>$dupes) {
                print '<div style="padding-top:10px;font-style:italic">parent '.$parent_ID.'  '.$TL[$parent_ID]['trm_Label'].'</div>';
                foreach ($dupes as $trm_ID) {
                    print '<div style="padding-left:60px">'.$trm_ID.'  '.$TL[$trm_ID]['trm_Label'].'</div>';
                }
            }
        }
        
        ?>  
            <br />      
        </div>      <!-- End of Duplicated Terms -->

        <!-- CHECK FOR FIELD TYPE ERRORS -->

        <div id="field_type" style="top:110px"> <!-- Start of Field Types -->
        <?php

        if ( ($dtysWithInvalidTerms && is_array($dtysWithInvalidTerms) && count($dtysWithInvalidTerms)>0) || 
        ($dtysWithInvalidNonSelectableTerms && is_array($dtysWithInvalidNonSelectableTerms) && count($dtysWithInvalidNonSelectableTerms)>0) || 
        ($dtysWithInvalidRectypeConstraint && is_array($dtysWithInvalidRectypeConstraint) && count($dtysWithInvalidRectypeConstraint)>0)){
            ?>
            <script>
                function repairFieldTypes(){

                    var dt = [
                        <?php
                        $isfirst = true;
                        foreach ($dtysWithInvalidTerms as $row) {
                            print ($isfirst?"":",")."[".$row['dty_ID'].", 0, '".$row['validTermsString']."']";
                            $isfirst = false;
                        }
                        foreach ($dtysWithInvalidNonSelectableTerms as $row) {
                            print ($isfirst?"":",")."[".$row['dty_ID'].", 1, '".$row['validNonSelTermsString']."']";
                            $isfirst = false;
                        }
                        foreach ($dtysWithInvalidRectypeConstraint as $row) {
                            print ($isfirst?"":",")."[".$row['dty_ID'].", 2, '".$row['validRectypeConstraint']."']";
                            $isfirst = false;
                        }
                    ?>];

                    //var str = JSON.stringify(dt);

                    var baseurl = '<?=HEURIST_BASE_URL?>admin/verification/repairFieldTypes.php';
                    var request = {db:'<?=HEURIST_DBNAME?>', data:dt};

                    window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, function(response){
                        //console.log('result '+context);
                        if(response.status == window.hWin.ResponseStatus.OK)
                        {
                            window.hWin.HEURIST4.msg.showMsgDlg(response['result'], null, 'Auto repair');
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                }
            </script>


            <br/><p><br/></p><h3>Warning: Inconsistent field definitions</h3><br/>&nbsp;<br/>

            The following field definitions have inconsistent data (unknown codes for terms and/or record types). This is nothing to be concerned about, unless it reoccurs, in which case please <?php echo CONTACT_HEURIST_TEAM;?><br/><br/>
            To fix the inconsistencies, please click here: <button onclick="repairFieldTypes()">Auto Repair</button>  <br/>&nbsp;<br/>
            You can also look at the individual field definitions by clicking on the name in the list below<br />&nbsp;<br/>
            <hr/>
            <?php 
            foreach ($dtysWithInvalidTerms as $row) {
                ?>
                <div class="msgline"><b><a href="#" onclick='{ onEditFieldType(<?= $row['dty_ID'] ?>); return false}'><?= $row['dty_Name'] ?></a></b> field (code <?= $row['dty_ID'] ?>) has
                    <?= count($row['invalidTermIDs'])?> invalid term ID<?=(count($row['invalidTermIDs'])>1?"s":"")?>
                    (code: <?= implode(",",$row['invalidTermIDs'])?>)
                </div>
                <?php
            }//for
            foreach ($dtysWithInvalidNonSelectableTerms as $row) {
                ?>
                <div class="msgline"><b><a href="#" onclick='{ onEditFieldType(<?= $row['dty_ID'] ?>); return false}'><?= $row['dty_Name'] ?></a></b> field (code <?= $row['dty_ID'] ?>) has
                    <?= count($row['invalidNonSelectableTermIDs'])?> invalid non selectable term ID<?=(count($row['invalidNonSelectableTermIDs'])>1?"s":"")?>
                    (code: <?= implode(",",$row['invalidNonSelectableTermIDs'])?>)
                </div>
                <?php
            }
            foreach ($dtysWithInvalidRectypeConstraint as $row) {
                ?>
                <div class="msgline"><b><a href="#" onclick='{ onEditFieldType(<?= $row['dty_ID'] ?>); return false}'><?= $row['dty_Name'] ?></a></b> field (code <?= $row['dty_ID'] ?>) has
                    <?= count($row['invalidRectypeConstraint'])?> invalid record type constraint<?=(count($row['invalidRectypeConstraint'])>1?"s":"")?>
                    (code: <?= implode(",",$row['invalidRectypeConstraint'])?>)
                </div>
                <?php
            }
            echo '<script>$(".field_type").css("background-color", "#E60000");</script>';
			
        }else{
            print '<h3 class="res-valid">OK: All field type definitions are valid</h3>';
            echo '<script>$(".field_type").css("background-color", "#6AA84f");</script>';
        }
        print '<br /><br /></div>';   // End of Field Types

        print '<div id="default_values" style="top:110px">';  // Start of Default Values
        if($rtysWithInvalidDefaultValues && 
        is_array($rtysWithInvalidDefaultValues) && 
        count($rtysWithInvalidDefaultValues)>0){
            ?>
            <br/><p><br/></p><h3>Warning: Wrong field default values for record type structures</h3><br/>&nbsp;<br/>

            The following fields use unknown terms as default values. <u>These default values have been removed</u>.
            <br>You can define new default value by clicking on the name in the list below and editing the field definition.<br />&nbsp;<br/>

            <?php 
            foreach ($rtysWithInvalidDefaultValues as $row) {
                ?>
                <div class="msgline"><b><a href="#" onclick='{ onEditRtStructure(<?= $row['rst_RecTypeID'] ?>); return false}'><?= $row['rst_DisplayName'] ?></a></b> field (code <?= $row['dty_ID'] ?>) in record type <?= $row['rty_Name'] ?>  has invalid default value (<?= ($row['dty_Type']=='resource'?'record ID ':'term ID ').$row['rst_DefaultValue'] ?>)
                    <span style="font-style:italic"><?=$row['reason'] ?></span>
                </div>
                <?php
            }//for
            echo '<script>$(".default_values").css("background-color", "#E60000");</script>';

        }else{
            print '<h3 class="res-valid">OK: All default values in record type structures are valid</h3>';
            echo '<script>$(".default_values").css("background-color", "#6AA84F");</script>';
        }
        print '<br /><br /></div>';   // End of Default Vlaues
        ?>
        
        <!-- CHECK DATA CONSISTENCY -->

        <!-- Record pointers which point to non-existant records -->

        <?php
        print '<div id="pointer_targets" style="top:110px">';   // Start of Pointer Targets

        $wasdeleted = 0;
        if(@$_REQUEST['fixpointers']=="1"){

            $query = 'delete d from recDetails d
            left join defDetailTypes dt on dt.dty_ID = d.dtl_DetailTypeID
            left join Records b on b.rec_ID = d.dtl_Value and b.rec_FlagTemporary!=1
            where dt.dty_Type = "resource"
            and b.rec_ID is null';
            $res = $mysqli->query( $query );
            if(! $res )
            {
                //$mysqli->error
                print "<div class='error'>Cannot delete invalid pointers from Records.</div>";
            }else{
                $wasdeleted = $mysqli->affected_rows;
            }
        }

        $res = $mysqli->query('select dtl_RecID, dty_Name, a.rec_Title, a.rec_RecTypeID, dtl_Value
            from recDetails
            left join defDetailTypes on dty_ID = dtl_DetailTypeID
            left join Records a on a.rec_ID = dtl_RecID and a.rec_FlagTemporary!=1
            left join Records b on b.rec_ID = dtl_Value and b.rec_FlagTemporary!=1
            where dty_Type = "resource"
            and a.rec_ID is not null
        and b.rec_ID is null');
        $bibs = array();
        $ids = array();
        while ($row = $res->fetch_assoc()) {
            array_push($bibs, $row);
            $ids[$row['dtl_RecID']] = 1;
        }

        if(count($bibs)==0){
            print '<div><h3 class="res-valid">OK: All record pointers point to a valid record</h3></div>';
            echo '<script>$(".pointer_targets").css("background-color", "#6AA84F");</script>';
            
            if($wasdeleted>1){
                print "<div>$wasdeleted invalid pointer(s) were removed from database</div>";
            }
        }
        else
        {
            echo '<script>$(".pointer_targets").css("background-color", "#E60000");</script>';
            ?>

            <div>
                <h3>Records with record pointers to non-existent records</h3>
                <span>
                    <a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:<?= implode(',', array_keys($ids)) ?>'>
                        (show results as search)</a>
                    <a target=_new href='#' id=selected_link onClick="return open_selected_by_name('recCB');">(show selected as search)</a>
                </span>
                <div>To fix the inconsistencies, please click here:
                    <button onclick="window.open('listDatabaseErrors.php?db=<?= HEURIST_DBNAME?>&fixpointers=1','_self')">
                        Delete ALL faulty pointers</button>
                </div>
            </div>
            <table>
                <tr>
                    <td colspan="6">
                        <label><input type=checkbox onclick="{mark_all_by_name(event.target, 'recCB');}">Mark all</label>
                    </td>
                </tr>
                <?php
                foreach ($bibs as $row) {
                    ?>
                    <tr>
                        <td><input type=checkbox name="recCB" value=<?= $row['dtl_RecID'] ?>></td>
                        <td><img class="rft" style="background-image:url(<?php echo HEURIST_ICON_URL.$row['rec_RecTypeID']?>.png)" src="<?php echo HEURIST_BASE_URL.'common/images/16x16.gif'?>"></td>
                        <td style="white-space: nowrap;"><a target=_new
                                href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                                <?= $row['dtl_RecID'] ?>
                                <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                            </a></td>
                        <td class="truncate" style="max-width:400px"><?=strip_tags($row['rec_Title']) ?></td>
                        <td><?= $row['dtl_Value'] ?></td>
                        <td><?= $row['dty_Name'] ?></td>
                    </tr>
                    <?php
                }
                print "</table>\n";
                ?>
            </table>

            <?php
        }
        print '<br /></div>';   // End of Pointer Targets

        //Record pointers which point to the wrong type of record
        print '<div id="target_types" style="top:110px">';  // Start of Target Types
        
        $res = $mysqli->query('select dtl_RecID, dty_Name, dty_PtrTargetRectypeIDs, rec_ID, rec_Title, rty_Name, rec_RecTypeID
            from defDetailTypes
            left join recDetails on dty_ID = dtl_DetailTypeID
            left join Records on rec_ID = dtl_Value and rec_FlagTemporary!=1
            left join defRecTypes on rty_ID = rec_RecTypeID
            where dty_Type = "resource"
            and dty_PtrTargetRectypeIDs > 0
        and (INSTR(concat(dty_PtrTargetRectypeIDs,\',\'), concat(rec_RecTypeID,\',\')) = 0)');

        $bibs = array();
        while ($row = $res->fetch_assoc()){
            $bibs[$row['dtl_RecID']] = $row;
        }

            if (count($bibs) == 0) {
                print '<h3 class="res-valid">OK: All record pointers point to the correct record type</h3>';
                echo '<script>$(".target_types").css("background-color", "#6AA84F");</script>';
            }
            else
            {
                echo '<script>$(".target_types").css("background-color", "#E60000");</script>';
                ?>
                <h3>Records with record pointers to the wrong record type</h3>
                <span><a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:<?= implode(',', array_keys($bibs)) ?>'>
                    (show results as search)</a></span>
                <table>
                    <?php
                    foreach ($bibs as $row) {
                        ?>
                        <tr>
                            <td><img class="rft" style="background-image:url(<?php echo HEURIST_ICON_URL.$row['rec_RecTypeID']?>.png)" src="<?php echo HEURIST_BASE_URL.'common/images/16x16.gif'?>"></td>
                            <td style="white-space: nowrap;"><a target=_new
                                    href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'><?= $row['dtl_RecID'] ?>
                                    <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                                </a></td>
                            <td><?= $row['dty_Name'] ?></td>
                            <td>points to</td>
                            <td><?= $row['rec_ID'] ?> (<?= $row['rty_Name'] ?>) - <?=(substr(strip_tags($row['rec_Title']), 0, 50)) ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </table>
                <?php
            }
            
            print '<br /><br /></div>';   // End of Target Types

            //Record pointers which point to the wrong type of record
            print '<div id="target_parent" style="top:110px">'; // Start of Target Parents

            if($system->defineConstant('DT_PARENT_ENTITY')){}

            $wasadded1 = 0;

            $wasdeleted1 = 0;
            if(false && @$_REQUEST['fixparents']=="1"){ 

                //remove pointer field in parent records that does not have reverse in children
                $query = 'DELETE parent FROM Records parentrec, defRecStructure, recDetails parent '
                .'LEFT JOIN recDetails child ON child.dtl_DetailTypeID='.DT_PARENT_ENTITY.' AND child.dtl_Value=parent.dtl_RecID '
                .'LEFT JOIN Records childrec ON parent.dtl_Value=childrec.rec_ID '
                .'WHERE '
                .'parentrec.rec_ID=parent.dtl_RecID AND rst_CreateChildIfRecPtr=1 '
                .'AND rst_RecTypeID=parentrec.rec_RecTypeID AND rst_DetailTypeID=parent.dtl_DetailTypeID '
                .'AND child.dtl_RecID is NULL'; 

                $res = $mysqli->query( $query );
                if(! $res )
                {
                    print "<div class='error'>Cannot delete invalid pointers from Records.</div>";
                    $wasdeleted1 = 0;
                }else{
                    $wasdeleted1 = $mysqli->affected_rows;
                }
            }

            //---------------------------------------                
            //find parents with pointer field rst_CreateChildIfRecPtr=1) without reverse pointer in child record               
            $query1 = 'SELECT parentrec.rec_ID, parentrec.rec_RecTypeID, parentrec.rec_Title as p_title, '  //'parent.dtl_DetailTypeID, rst_DisplayName, '
            .'parent.dtl_Value, childrec.rec_Title as c_title, child.dtl_RecID as f247 '
            .'FROM Records parentrec, defRecStructure, recDetails parent '
            .'LEFT JOIN recDetails child ON child.dtl_DetailTypeID='.DT_PARENT_ENTITY.' AND child.dtl_Value=parent.dtl_RecID '
            .'LEFT JOIN Records childrec ON parent.dtl_Value=childrec.rec_ID '
            .'WHERE '
            .'parentrec.rec_ID=parent.dtl_RecID AND rst_CreateChildIfRecPtr=1 AND parentrec.rec_FlagTemporary!=1 '
            .'AND rst_RecTypeID=parentrec.rec_RecTypeID AND rst_DetailTypeID=parent.dtl_DetailTypeID '
            .'AND child.dtl_RecID is NULL ORDER BY parentrec.rec_ID'; 


            $res = $mysqli->query( $query1 );

            $bibs1 = array();
            $prec_ids1 = array();
            while ($row = $res->fetch_assoc()){

                $child_ID = $row['dtl_Value'];

                if(@$_REQUEST['fixparents']=="1"){ 
                    //new way: add missed reverse link to alleged children
                    $query2 = 'insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) VALUES(' 
                    .$child_ID . ','. DT_PARENT_ENTITY  .', '.$row['rec_ID'] . ')';
                    $mysqli->query($query2);
                    $wasadded1++;
                }else{
                    $bibs1[] = $row;
                    $prec_ids1[] = $row['rec_ID'];
                }

            }

            //print $query1; 
            //print '<br>'.count($bibs1);

            //find children without reverse pointer in parent record               
            $query2 = 'SELECT child.dtl_ID as child_d_id, child.dtl_RecID as child_id, childrec.rec_Title as c_title, child.dtl_Value, '
            .'parentrec.rec_Title as p_title, parent.dtl_ID as parent_d_id, parent.dtl_Value rev, dty_ID, rst_CreateChildIfRecPtr, '
            .'childrec.rec_FlagTemporary '
            .' FROM recDetails child '
            .'LEFT JOIN Records parentrec ON child.dtl_Value=parentrec.rec_ID '
            .'LEFT JOIN Records childrec ON childrec.rec_ID=child.dtl_RecID  '
            .'LEFT JOIN recDetails parent ON parent.dtl_RecID=parentrec.rec_ID AND parent.dtl_Value=childrec.rec_ID '
            .'LEFT JOIN defDetailTypes ON parent.dtl_DetailTypeID=dty_ID AND dty_Type="resource" ' //'AND dty_PtrTargetRectypeIDs=childrec.rec_RecTypeID '
            .'LEFT JOIN defRecStructure ON rst_RecTypeID=parentrec.rec_RecTypeID AND rst_DetailTypeID=dty_ID AND rst_CreateChildIfRecPtr=1 '
            .'WHERE child.dtl_DetailTypeID='.DT_PARENT_ENTITY .' AND parent.dtl_DetailTypeID!='.DT_PARENT_ENTITY
            .' AND (rst_CreateChildIfRecPtr IS NULL OR rst_CreateChildIfRecPtr!=1) ORDER BY child.dtl_RecID';

            $res = $mysqli->query( $query2 );

            $bibs2 = array();
            $prec_ids2 = array();
            $det_ids = array();
            while ($row = $res->fetch_assoc()){
                if($row['rec_FlagTemporary']==1) continue;
                if(in_array( $row['child_d_id'], $det_ids)) continue;
                $bibs2[] = $row;
                $prec_ids2[] = $row['dtl_Value'];  //remove DT_PARENT_ENTITY from orphaned children
                array_push($det_ids, $row['child_d_id']); 
                /*
                if($row['parent_d_id']>0){
                // keep dtl_ID of pointer field in parent record 
                // to remove this 'fake' pointer in parent - that's wrong - need to remove DT_PARENT_ENTITY in child
                $det_ids[] = $row['parent_d_id']; 
                }
                */
            }//while

            //print $query2; 
            //print '<br>'.count($bibs2);


            $wasdeleted2 = 0;
            if(@$_REQUEST['fixparents']=="2"){

                $query = 'DELETE FROM recDetails WHERE dtl_ID in ('.implode(',',$det_ids).')';
                $res = $mysqli->query( $query );
                if(! $res )
                {
                    print "<div class='error'>Cannot delete invalid pointers from Records.</div>";
                }else{
                    $wasdeleted2 = $mysqli->affected_rows;
                    $bibs2 = array();
                }
            }


            if (count($bibs1) == 0) {
                print '<h3 class="res-valid">OK: All parent records are correctly referenced by their child records</h3><br>';
                if($wasdeleted1>1){
                    print "<div>$wasdeleted1 invalid pointer(s) were removed from database</div>";
                }
                if($wasadded1>0){
                    print "<div>$wasadded1 reverse pointers were added t0 child records</div>";
                }
            }
            else
            {
                ?>
                <br><h3>Parent records which are not correctly referenced by their child records (missing pointer to parent)</h3>
                <span><a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:<?= implode(',', $prec_ids1) ?>'>
                    (show results as search)</a></span>

                <div>To fix the inconsistencies, please click here:
                    <button onclick="window.open('listDatabaseErrors.php?db=<?= HEURIST_DBNAME?>&fixparents=1','_self')">
                        Add missing parent record pointers to child records</button>
                </div>

                <table>
                    <?php
                    foreach ($bibs1 as $row) {
                        ?>
                        <tr>
                            <td><img class="rft" style="background-image:url(<?php echo HEURIST_ICON_URL.$row['rec_RecTypeID']?>.png)" src="<?php echo HEURIST_BASE_URL.'common/images/16x16.gif'?>"></td>
                            <td style="white-space: nowrap;"><a target=_new
                                    href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['rec_ID'] ?>'><?= $row['rec_ID'] ?>
                                    <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                                </a></td>
                            <td><?= $row['p_title'] ?></td>
                            <td>points to</td>
                            <td style="white-space: nowrap;"><a target=_new2
                                    href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_Value'] ?>'><?= $row['dtl_Value'] ?>
                                    <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                                </a></td>
                            <td><?= substr($row['c_title'], 0, 50) ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </table>
                <?php
            }


            if (count($bibs2) == 0) {
                print '<br><h3 class="res-valid">OK: All parent records correctly reference records which believe they are their children</h3><br>';
                
                if (count($bibs1) == 0) {
                    echo '<script>$(".target_parent").css("background-color", "#6AA84F");</script>';
                }
                
                if($wasdeleted2>1){
                    print "<div>$wasdeleted2 invalid pointer(s) were removed from database</div>";
                }
            }
            else
            {
                echo '<script>$(".target_types").css("background-color", "#E60000");</script>';
                ?>
                <br><h3>Child records indicate a parent which does not identify them as their child. </h3>
                <span><a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:<?= implode(',', $prec_ids2) ?>'>
                    (show results as search)</a></span>

                <!--  IJ: For the moment I suggest you omit a FIX button for this case

                <div>To fix the inconsistencies, please click here:
                <button onclick="window.open('listDatabaseErrors.php?db=<?= HEURIST_DBNAME?>&fixparents=2','_self')">
                Delete broken parent-child fields in alleged children records</button>
                </div>
                -->                    
                <table>
                    <?php
                    foreach ($bibs2 as $row) {
                        ?>
                        <tr>
                            <td style="white-space: nowrap;"><a target=_new
                                    href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_Value'] ?>'><?= $row['dtl_Value'] ?>
                                    <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                                </a></td>
                            <td><?= $row['p_title'] ?></td>
                            <td>has reverse 'pointer to parent' in </td>
                            <td style="white-space: nowrap;"><a target=_new2
                                    href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['child_id'] ?>'><?= $row['child_id'] ?>
                                    <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                                </a></td>
                            <td><?= substr($row['c_title'], 0, 50) ?></td>
                        </tr>
                        <?php

                    }
                    ?>
                </table>
                <?php
            }
            print '<br /></div>';   // End of Target Parents

        // ----- Fields with EMPTY OR NULL values -------------------

        print '<div id="empty_fields" style="top:110px">';  // Start of Empty Fields

        if(@$_REQUEST['fixempty']=="1"){
            $mysqli->query('SET SQL_SAFE_UPDATES=0');
            $mysqli->query('delete d.* from recDetails d, defDetailTypes, Records a '
                .'where (dtl_ID>0) and (a.rec_ID = dtl_RecID) and (dty_ID = dtl_DetailTypeID) and (a.rec_FlagTemporary!=1)
            and (dty_Type!=\'file\') and ((dtl_Value=\'\') or (dtl_Value is null))');                

            $wascorrected = $mysqli->affected_rows;     
            $mysqli->query('SET SQL_SAFE_UPDATES=1');
        }else{
            $wascorrected = 0;
        }

        $total_count_rows = 0;

        //find all fields with faulty dates
        $res = $mysqli->query('select dtl_ID, dtl_RecID, a.rec_RecTypeID, a.rec_Title, dty_Name, dty_Type
            from recDetails, defDetailTypes, Records a
            where (a.rec_ID = dtl_RecID) and (dty_ID = dtl_DetailTypeID) and (a.rec_FlagTemporary!=1)
        and (dty_Type!=\'file\') and ((dtl_Value=\'\') or (dtl_Value is null))');


        $total_count_rows = mysql__select_value($mysqli, 'select found_rows()');

        if($total_count_rows<1){
            print '<div><h3 class="res-valid">OK: All records don\'t have empty fields</h3></div>';
            echo '<script>$(".empty_fields").css("background-color", "#6AA84F");</script>';
        }
        if($wascorrected>1){
            print "<div>$wascorrected empty fields were deleted</div>";
        }

        if($total_count_rows>0){
            echo '<script>$(".empty_fields").css("background-color", "#E60000");</script>';
            ?>

            <div>
                <h3>Records with empty fields</h3>
                <span>
                    <a target=_new href="javascript:void(0)" onclick="{document.getElementById('link_empty_values').click(); return false;}">(show results as search)</a>
                    <a target=_new href='#' id=selected_link onClick="return open_selected_by_name('recCB6');">(show selected as search)</a>
                </span>

                <div>To REMOVE empty fields, please click here:
                    <button
                        onclick="{document.getElementById('page-inner').style.display = 'none';window.open('listDatabaseErrors.php?db=<?= HEURIST_DBNAME?>&fixempty=1','_self')}">
                        Remove all null values</button>
                </div>
            </div>

            <table>
            <tr>
                <td colspan="5">
                    <label><input type=checkbox onclick="{mark_all_by_name(event.target, 'recCB6');}">Mark all</label>
                </td>
            </tr>
            <?php


            $ids = array();

            while ($row = $res->fetch_assoc()){
                ?>
                <tr>
                    <td><input type=checkbox name="recCB6" value=<?= $row['dtl_RecID'] ?>></td>
                    <td><img class="rft" style="background-image:url(<?php echo HEURIST_ICON_URL.$row['rec_RecTypeID']?>.png)" src="<?php echo HEURIST_BASE_URL.'common/images/16x16.gif'?>"></td>
                    <td style="white-space: nowrap;"><a target=_new
                            href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                            <?= $row['dtl_RecID'] ?>
                            <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                        </a></td>
                    <td class="truncate" style="max-width:400px"><?=strip_tags($row['rec_Title']) ?></td>                            
                    <td><?= $row['dty_Name'] ?></td>
                </tr>
                <?php
                $ids[$row['dtl_RecID']] = 1;

            }
            print '</table><br>';

            echo '<span><a target=_new id="link_empty_values" href='.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME
            .'&w=all&q=ids:'.implode(',', array_keys($ids)).'>(show results as search)</a></span>';

        }
        print '<br /></div>';   // End of Empty Fields
		
        // ----- Fields of type "Date" with  wrong values -------------------
        //find all fields with faulty dates

        print '<div id="date_values" style="top:110px">';   // Start of Date Values
            
        $res = $mysqli->query('select dtl_ID, dtl_RecID, dtl_Value, a.rec_RecTypeID, a.rec_Title, a.rec_Added
            from recDetails, defDetailTypes, Records a
            where (a.rec_ID = dtl_RecID) and (dty_ID = dtl_DetailTypeID) and (a.rec_FlagTemporary!=1)
        and (dty_Type = "date") and (dtl_Value is not null)');

        $wassuggested = 0;
        $wascorrected = 0;
        $ambigious = 0;
        $bibs = array();
        $ids  = array();
        $dtl_ids = array();
        $recids = @$_REQUEST['recids'];
        if($recids!=null){
            $recids = explode(',', $recids);
        }
        
        $decade_regex = '/^\d{2,4}s$/'; //words like 80s 1990s
        $year_range_regex = '/^\d{2,4}\-\d{2,4}$/'; //2-4 year ranges

        $fix_as_suggested = false;
 
        while ($row = $res->fetch_assoc()){
            
            $row['is_ambig'] = false;
            $autofix = false;

            if(!($row['dtl_Value']==null || trim($row['dtl_Value'])=='')){ //empty dates are not allowed

                //ignore decade dates
                $row['new_value'] = null;
                $row['dtl_Value'] = trim($row['dtl_Value']);
                $row['is_ambig'] = true;
                
                if(  (strlen($row['dtl_Value'])==3 || strlen($row['dtl_Value'])==5) 
                    && preg_match( $decade_regex, $row['dtl_Value'] )){
                        
                    //this is decades    
                    $row['is_ambig'] = 'we suggest using a date range';
                    $wassuggested++;
                    
                }else if(preg_match( $year_range_regex, $row['dtl_Value'])){
                    
                    list($y1, $y2) = explode('-',$row['dtl_Value']);
                    if($y1>31 && $y2>12){
                        //this is year range
                        $row['is_ambig'] = 'we suggest using a date range';
                        $wassuggested++;
                    }
                }
                
                if($row['is_ambig']===true){
                    //parse and validate value   order 2 (mm/dd), don't add day  if it is not defined
                    $row['new_value'] = validateAndConvertToISO($row['dtl_Value'], $row['rec_Added'], 2, false);
                    if($row['new_value']=='Temporal'){
                        continue;
                    }else if($row['new_value']==$row['dtl_Value']){ //nothing to correct - result is the same
                        continue;
                    }
                    if($row['new_value']!=null && $row['new_value']!=''){
                        $row['is_ambig'] = correctDMYorder($row['dtl_Value'], true);
                        $autofix = ($row['is_ambig']===false);
                    }
                }
                
            }else{
                $row['new_value'] = 'remove';
            }

            $was_fixed = false;
            //correct wrong dates and remove empty values
            if($autofix || (@$_REQUEST['fixdates']=="1" && count($recids)>0 && in_array($row['dtl_RecID'], $recids)) ){

                if($row['new_value']!=null && $row['new_value']!=''){
//print ' u-'.$row['dtl_RecID'];                    
                    $mysqli->query('update recDetails set dtl_Value="'.$row['new_value'].'" where dtl_ID='.$row['dtl_ID']);
                }else{ // if($row['new_value']=='remove')
//print ' r-'.$row['dtl_RecID'];                    
                    $mysqli->query('delete from recDetails where dtl_ID='.$row['dtl_ID']);
                }
                $was_fixed = true;
            }

            if($autofix || !$was_fixed){
                if($row['new_value']!=null && $row['new_value']!=''){
                        if(@$row['is_ambig']){
                            $ambigious++;    
                            //$ids[$row['dtl_RecID']] = 1;
                        }else{
                            $wascorrected++;    
                        }
                }
                $ids[$row['dtl_RecID']] = 1;  //all record ids - to show as search result
                
                //autocorrection }else{
                array_push($bibs, $row);
                array_push($dtl_ids, $row['dtl_ID']); //not used
            }
            
            $fix_as_suggested = $fix_as_suggested || 
                (is_bool($row['is_ambig']) && ($row['new_value']==null || $row['is_ambig']===true));
        }//while

        if(count($bibs)==0){
            print '<div><h3 class="res-valid">OK: All records have recognisable Date values</h3></div>';
            echo '<script>$(".date_values").css("background-color", "#6AA84F");</script>';
        }
        else
        {
            echo '<script>$(".date_values").css("background-color", "#E60000");</script>';
            ?>

            <div>
                <h3>Records with incorrect Date fields</h3>
                <?php
                if($wascorrected>0){
                    print '<div style="margin-bottom:10px"><b>Auto-corrected dates</b> '
                            ."The following dates have been corrected as shown ($wascorrected)</div><table>";
                    
                    foreach ($bibs as $row) 
                        if($row['new_value']!=null && $row['new_value']!='' && @$row['is_ambig']===false)
                    {
                    ?>
                    <tr>
                        <td></td>
                        <td><img class="rft" style="background-image:url(<?php echo HEURIST_ICON_URL.$row['rec_RecTypeID']?>.png)" src="<?php echo HEURIST_BASE_URL.'common/images/16x16.gif'?>"></td>
                        <td style="white-space: nowrap;"><a target=_new
                                href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                                <?= $row['dtl_RecID'] ?>
                                <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                            </a></td>
                        <td class="truncate" style="max-width:400px"><?=strip_tags($row['rec_Title']) ?></td>
                        <td><?= @$row['dtl_Value']?$row['dtl_Value']:'empty' ?></td>
                        <td><?= ($row['new_value']=='remove'?'=>&nbsp;removed':('=>&nbsp;&nbsp;'.$row['new_value'])) ?></td>
                    </tr>
                    <?php
                    }
                    print '</table>';                    
                }
                
                
                if($wassuggested>0){
                    print '<div style="margin-bottom:10px"><b>Decades and year ranges</b> '
                            ."we suggest using a date ranges for dates below</div><table>";
                    
                    foreach ($bibs as $row) 
                    if(!is_bool($row['is_ambig']))
                    {
                    ?>
                    <tr>
                        <td></td>
                        <td><img class="rft" style="background-image:url(<?php echo HEURIST_ICON_URL.$row['rec_RecTypeID']?>.png)" src="<?php echo HEURIST_BASE_URL.'common/images/16x16.gif'?>"></td>
                        <td style="white-space: nowrap;"><a target=_new
                                href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                                <?= $row['dtl_RecID'] ?>
                                <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                            </a></td>
                        <td class="truncate" style="max-width:400px;min-width:200px"><?=strip_tags($row['rec_Title']) ?></td>
                        <td><?= @$row['dtl_Value']?$row['dtl_Value']:'empty' ?></td>
                    </tr>
                    <?php
                    }
                    print '</table>';                    
                }                

                if($ambigious>0){
                    print "<div style='margin:10px 0'><b>$ambigious Suggestions</b> for date field corrections (gray color in the list)</div>";
                }
                ?>
                <span>
                    <a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:<?= implode(',', array_keys($ids)) ?>'>
                        (show results as search)</a>
                    <a target=_new href='#' id=selected_link style="display:<?php echo ($fix_as_suggested?'inline-block':'none');?>;"
                                onClick="return open_selected_by_name('recCB5');">(show selected as search)</a>
                </span>

                <?php 
                    if($fix_as_suggested){
                ?>
                <div>To fix faulty date values as suggested, mark desired records and please click here:
                    <button
                        onclick="{var ids=get_selected_by_name('recCB5'); if(ids){document.getElementById('page-inner').style.display = 'none';window.open('listDatabaseErrors.php?db=<?= HEURIST_DBNAME?>&fixdates=1&recids='+ids,'_self')}else{ window.hWin.HEURIST4.msg.showMsgDlg('Mark at least one record to correct'); }}">
                        Correct</button>
                </div>
                <?php 
                    }
                ?>

            </div>

            <table>
            <tr style="display:<?php echo ($fix_as_suggested?'block':'none');?>;">
                <td colspan="6">
                    <label><input type=checkbox 
                                onclick="{mark_all_by_name(event.target, 'recCB5');}">Mark all</label>
                </td>
            </tr>
            <?php
            foreach ($bibs as $row) 
            if(is_bool($row['is_ambig']) && ($row['new_value']==null || $row['is_ambig']===true))
            {
                ?>
                <tr<?php echo (($row['new_value'])?' style="color:gray"':''); //if null - no auto fix   ?>>
                    <td><?php if(true || $row['new_value']) 
                            print '<input type=checkbox name="recCB5" value='.$row['dtl_RecID'].'>';
                        ?>
                    </td>
                    <td><img class="rft" style="background-image:url(<?php echo HEURIST_ICON_URL.$row['rec_RecTypeID']?>.png)" 
                                    src="<?php echo HEURIST_BASE_URL.'common/images/16x16.gif'?>"></td>
                    <td style="white-space: nowrap;"><a target=_new
                            href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                            <?= $row['dtl_RecID'] ?>
                            <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                        </a></td>
                    <td class="truncate" style="max-width:400px"><?=strip_tags($row['rec_Title']) ?></td>
                    <td><?= @$row['dtl_Value']?$row['dtl_Value']:'empty' ?></td>
                    <td><?php 
                        echo ($row['new_value']!=null && $row['new_value']!=''
                            ?('=>&nbsp;&nbsp;'.$row['new_value'])
                            :'&lt;No auto-fix available, will be deleted if checked&gt;'); ?></td>
                </tr>
                <?php
            }
            print '</table>';
        }
        print '<br /></div>';   // End of Date Values
        
        //  Records with term field values which do not exist in the database--------------------
        
        print '<div id="term_values" style="top:110px">';   // Start of Term Values
        
        $wasdeleted = 0;

        //remove wrong term IDs
        if(@$_REQUEST['fixterms']=="1"){
            $query = 'delete d from recDetails d
            left join defDetailTypes dt on dt.dty_ID = d.dtl_DetailTypeID
            left join defTerms b on b.trm_ID = d.dtl_Value
            where dt.dty_Type = "enum" or  dt.dty_Type = "relmarker"
            and b.trm_ID is null';
            $res = $mysqli->query( $query );
            if(! $res )
            {
                print "<div class='error'>Cannot delete invalid term values from Records. SQL error: ".$mysqli->error."</div>";
            }else{
                $wasdeleted = $mysqli->affected_rows;
            }
        }

        //find non existing term values
        $res = $mysqli->query('select dtl_ID, dtl_RecID, dty_Name, a.rec_RecTypeID, a.rec_Title
            from recDetails
            left join defDetailTypes on dty_ID = dtl_DetailTypeID
            left join Records a on a.rec_ID = dtl_RecID
            left join defTerms b on b.trm_ID = dtl_Value
            where (dty_Type = "enum" or dty_Type = "relmarker") and dtl_Value is not null
            and a.rec_ID is not null
        and b.trm_ID is null');
        $bibs = array();
        $ids  = array();
        $dtl_ids = array();
        while ($row = $res->fetch_assoc()){
            array_push($bibs, $row);
            $ids[$row['dtl_RecID']] = 1;
            array_push($dtl_ids, $row['dtl_ID']);
        }

        if(count($bibs)==0){
            print '<div><h3 class="res-valid">OK: All records have recognisable term values</h3></div>';
            echo '<script>$(".term_values").css("background-color", "#6AA84F");</script>';
            if($wasdeleted>1){
                print "<div>$wasdeleted invalid term value(s) were removed from database</div>";
            }
        }
        else
        {
            echo '<script>$(".term_values").css("background-color", "#E60000");</script>';
            ?>

            <div>
                <h3>Records with non-existent term values</h3>
                <span>
                    <a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:<?= implode(',', array_keys($ids)) ?>'>
                        (show results as search)</a>
                    <a target=_new href='#' id=selected_link onClick="return open_selected_by_name('recCB1');">(show selected as search)</a>
                </span>
                <div>To fix the inconsistencies, please click here:
                    <button onclick="window.open('listDatabaseErrors.php?db=<?= HEURIST_DBNAME?>&fixterms=1','_self')">
                        Delete ALL faulty term values</button>
                </div>
            </div>

            <table>
            <tr>
                <td colspan="5">
                    <label><input type=checkbox onclick="{mark_all_by_name(event.target, 'recCB1');}">Mark all</label>
                </td>
            </tr>

            <?php
            foreach ($bibs as $row) {
                ?>
                <tr>
                    <td><input type=checkbox name="recCB1" value=<?= $row['dtl_RecID'] ?>></td>
                    <td><img class="rft" style="background-image:url(<?php echo HEURIST_ICON_URL.$row['rec_RecTypeID']?>.png)" src="<?php echo HEURIST_BASE_URL.'common/images/16x16.gif'?>"></td>
                    <td style="white-space: nowrap;"><a target=_new
                            href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                            <?= $row['dtl_RecID'] ?>
                            <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                        </a></td>
                    <td class="truncate" style="max-width:400px"><?=strip_tags($row['rec_Title']) ?></td>                        
                    <td><?= $row['dty_Name'] ?></td>
                </tr>
                <?php
            }
            print '</table>';
        }
        print '<br /></div>';       // End of Term Values
        ?>

            <!--  Records containing fields with terms not in the list of terms specified for the field   -->

            <div id="expected_terms" style="top:110px"> <!-- Start of Expected Terms -->
            <?php

            $res = $mysqli->query('select dtl_ID, dtl_RecID, dty_Name, dtl_Value, dty_ID, dty_JsonTermIDTree, dty_TermIDTreeNonSelectableIDs, rec_Title, rec_RecTypeID, rty_Name, trm_Label
                from Records, recDetails left join defTerms on dtl_Value=trm_ID, defDetailTypes, defRecTypes
                where rec_ID = dtl_RecID and dty_ID = dtl_DetailTypeID and (dty_Type = "enum" or  dty_Type = "relmarker")
                and dtl_Value is not null and rec_RecTypeID=rty_ID and rec_FlagTemporary!=1
            order by dtl_DetailTypeID');
            /*
            'select dtl_RecID, dty_Name, dty_JsonTermIDTree, dty_TermIDTreeNonSelectableIDs, rec_Title, dtl_Value, dty_ID
            from defDetailTypes
            left join recDetails on dty_ID = dtl_DetailTypeID
            left join Records on rec_ID = dtl_RecID
            where dty_Type = "enum" or  dty_Type = "relmarker"
            order by dtl_DetailTypeID'*/
            $bibs = array();
            $ids = array();
            $ids2 = array();
            $same_name_suggestions = array();
            $is_first = true;
            while ($row = $res->fetch_assoc()){ 
                //verify value
                /*DEBUG                
                $res = VerifyValue::getAllowedTerms($row['dty_JsonTermIDTree'], null, $row['dty_ID']);
                print ("<br> >>>>".$row['dtl_Value']);                
                print ('<br>'.implode(',',$res).'<br>');                
                print ('<br>'.in_array($row['dtl_Value'], $res));                
                */
                if(  !in_array($row['dtl_ID'], $dtl_ids) &&  //already non existant
                trim($row['dtl_Value'])!="" &&
                !VerifyValue::isValidTerm($row['dty_JsonTermIDTree'], $row['dty_TermIDTreeNonSelectableIDs'], $row['dtl_Value'], $row['dty_ID'] ))
                {
                    //ok - term does not belong to required vocabullary
                    //check that this vocabulary already has term with the same label
                    $existing_term_id = VerifyValue::hasVocabGivenLabel($row['dty_JsonTermIDTree'], $row['trm_Label']);
                    if($existing_term_id>0){
                        $code = $row['dty_ID'].'.'.$row['dtl_Value'];
                        if(@$same_name_suggestions[$code]==null){
                            $same_name_suggestions[$code] = array($existing_term_id, $row['trm_Label'], $row['dty_Name']);            
                        }
                        $ids2[$row['dtl_RecID']] = 1;
                        continue;
                    }
                    
                    
                    if($is_first){
                        $is_first = false;
                        ?>
                        <h3 style="padding-left:2px">Records with terms not in the list of terms specified for the field</h3>
                        <span><a target=_new href="javascript:void(0)" onclick="{document.getElementById('link_wrongterms').click(); return false;}">(show results as search)</a></span>
                        <table>
                        <tr>
                            <th style="width: 30px;text-align:left">Record</th>
                            <th style="width: 45ex;text-align:left;">Field</th>
                            <th style="width: 25ex;">Term</th>
                            <th>Record title</th>
                        </tr>

                        <?php
                    }
                    ?>
                    <tr>
                        <td style="width:50px;">
                            <a target=_new  title='Click to edit record'
                                href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                                <img class="rft" style="background-image:url(<?php echo HEURIST_ICON_URL.$row['rec_RecTypeID']?>.png)" 
                                    title="<?php echo $row['rty_Name']?>" 
                                    src="<?php echo HEURIST_BASE_URL.'common/images/16x16.gif'?>">&nbsp;<?= $row['dtl_RecID'] ?>
                            </a>
                        </td>
                        <td style="width:55ex;padding-left:5px;"><?= $row['dty_Name'] ?></td>
                        <!-- >Artem TODO: Need to render the value as the term label, not the numeric value -->
                        <td style="width: 23ex;padding-left: 5px;"><?= $row['dtl_Value'].'&nbsp;'.$row['trm_Label'] ?></td>
                        <td class="truncate" style="padding-left:25px;max-width:400px"><?=strip_tags($row['rec_Title']) ?></td>
                    </tr>
                    <?php
                    //array_push($bibs, $row);    // MEMORY EXHAUSTION happens here
                    $ids[$row['dtl_RecID']] = 1;  
                }

            }//while 
            if (count($ids) == 0) {
                print '<h3 class="res-valid">OK: All records have valid terms (terms are as specified for each field)</h3>';
                echo '<script>$(".expected_terms").css("background-color", "#6AA84F");</script>';
            }else{
                echo '<script>$(".expected_terms").css("background-color", "#E60000");</script>';
                echo '</table><br>';   
                echo '<span style="font-size:0.9em;"><a target=_new id="link_wrongterms" href='.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME
                .'&w=all&q=ids:'.implode(',', array_keys($ids)).'>(show results as search)</a></span>';
            }
            
            if(count($same_name_suggestions)>0){
                
                if(@$_REQUEST['fix_samename_terms']=="1"){
                    
                    $keep_autocommit = mysql__begin_transaction($mysqli);    

                    $isOk = true;
                    foreach($same_name_suggestions as $code => $row){
                        
                        list($dty_ID,$trm_id) = explode('.',$code);
                        //$existing_term_id, $row['trm_Label'], $row['dty_Name']
                    
                        $query = 'UPDATE recDetails set dtl_Value='.$row[0].' where dtl_DetailTypeID='.$dty_ID.' and dtl_Value='.$trm_id;
                        $res = $mysqli->query( $query );
                        if(! $res )
                        {
                            $isOk = false;
                            print '<div class="error" style="color:red">Cannot replace terms in record details. SQL error: '.$mysqli->error.'</div>';
                            $mysqli->rollback();
                            break;
                        }
                    }//for
                    if($isOK){
                        $mysqli->commit();  
                    }
                    if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                }//correct
                else{
?>             
<hr/>
<h3>Terms referenced in incorrect vocabulary (n = <?php echo count($same_name_suggestions);?> )</h3><br>
<span style="font-size:0.9em;">Terms are referenced in a different vocabulary than that specified for the corresponding field, 
<br>however the same term label exists in the vocabulary specified for the field.
<br><button onclick="window.open('listDatabaseErrors.php?db=<?= HEURIST_DBNAME?>&fix_samename_terms=1','_self')">Click here to change these terms</button> to the ones in the vocabularies specified for each field,<br>otherwise they can be fixed for each term individually in record editing.</span><br><br>
                        <table>
                        <tr>
                            <th style="width: 50px;text-align: left;">Field ID</th>
                            <th style="width: 45ex;text-align:left;">Field</th>
                            <th style="width: 25ex;">Term</th>
                        </tr>

<?php                   
                foreach($same_name_suggestions as $code => $row){
                    list($dty_ID,$trm_id) = explode('.',$code);
?>                    
                        <tr>
                            <td style="width: 50px;"><?php echo $dty_ID;?></td>
                            <td style="width: 55ex;"><?php echo $row[2];?></td>
                            <td style="width: 25ex;"><?php echo $row[1];?></td>
                        </tr>
<?php                    
                }
                echo '</table><br>'; 
                echo '<span style="font-size:0.9em;"><a target=_new id="link_wrongterms2" href='.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME
                .'&w=all&q=ids:'.implode(',', array_keys($ids2)).'>(show results as search)</a></span>';
                    
                }
            }//same name suggestions

            ?>
                <br />
                <br />
            </div>  <!-- End of Expected Terms -->


        <!--  single value fields containing excess values  -->
        <div id="single_value" style="top:110px">   <!-- Start of Single Value Fields -->
        <?php

        $res = $mysqli->query('select dtl_RecID, rec_RecTypeID, dtl_DetailTypeID, rst_DisplayName, rec_Title, count(*)
            from recDetails, Records, defRecStructure
            where rec_ID = dtl_RecID  and rec_FlagTemporary!=1 
            and rst_RecTypeID = rec_RecTypeID and rst_DetailTypeID = dtl_DetailTypeID
            and rst_MaxValues=1
            GROUP BY dtl_RecID, rec_RecTypeID, dtl_DetailTypeID, rst_DisplayName, rec_Title
        HAVING COUNT(*) > 1');

        $bibs = array();
        $ids = array();
        while ($row = $res->fetch_assoc()){
            array_push($bibs, $row);
            $ids[$row['dtl_RecID']] = 1;
        }

        if(count($bibs)==0){
            print '<h3 class="res-valid">OK: No single value fields exceed 1 value</h3>';
            echo '<script>$(".single_value").css("background-color", "#6AA84F");</script>';
        }
        else
        {
            echo '<script>$(".single_value").css("background-color", "#E60000");</script>';
            ?>

            <div>
                <h3>Single value fields with multiple values</h3>
                <span>
                    <a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:<?= implode(',', array_keys($ids)) ?>'>
                        (show results as search)</a>
                    <a target=_new href='#' id=selected_link2 onClick="return open_selected_by_name('recCB2');">(show selected as search)</a>
                </span>
            </div>

            <table>

                <tr>
                    <td colspan="5">
                        <label><input type=checkbox onclick="{mark_all_by_name(event.target, 'recCB2');}">Mark all</label>
                    </td>
                </tr>

                <?php
                $rec_id = null;
                foreach ($bibs as $row) {
                    if($rec_id!=$row['dtl_RecID']) {
                        ?>
                        <tr>
                            <td>
                                <input type=checkbox name="recCB2" value=<?= $row['dtl_RecID'] ?>>
                            </td>
                            <td><img class="rft" style="background-image:url(<?php echo HEURIST_ICON_URL.$row['rec_RecTypeID']?>.png)" src="<?php echo HEURIST_BASE_URL.'common/images/16x16.gif'?>"></td>

                            <td style="white-space: nowrap;">
                                <a target=_blank href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                                <?= $row['dtl_RecID'] ?></a>
                                <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                            </td>
                            <td class="truncate" style="max-width:400px"><?=strip_tags($row['rec_Title']) ?></td>
                            </td>
                            <?php
                            $rec_id = $row['dtl_RecID'];
                        }else{
                            print '<tr><td colspan="4"></td>';
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
            <br />
        </div>  <!-- End of Single Value Fields -->


        <!--  records with missing required values  -->
        <div id="required_fields" style="top:110px">    <!-- Start of Required Fields -->
        <?php

        $res = $mysqli->query(
            "select rec_ID, rec_RecTypeID, rst_DetailTypeID, rst_DisplayName, dtl_Value, rec_Title, dty_Type, rty_Name
            from Records
            left join defRecStructure on rst_RecTypeID = rec_RecTypeID
            left join recDetails on rec_ID = dtl_RecID and rst_DetailTypeID = dtl_DetailTypeID
            left join defDetailTypes on dty_ID = rst_DetailTypeID
            left join defRecTypes on rty_ID = rec_RecTypeID
            where rec_FlagTemporary!=1 and rst_RequirementType='required' and (dtl_Value is null or dtl_Value='')
            and dtl_UploadedFileID is null and dtl_Geo is null and dty_Type!='separator' and dty_Type!='relmarker'
        order by rec_ID");

        $bibs = array();
        $ids = array();
        while ($row = $res->fetch_assoc()){
            array_push($bibs, $row);
            $ids[$row['rec_ID']] = $row;
        }

        if(count($bibs)==0){
            print '<div><h3 class="res-valid">OK: No required fields with missing or empty values</h3></div>';
            echo '<script>$(".required_fields").css("background-color", "#6AA84F");</script>';
        }
        else
        {
            echo '<script>$(".required_fields").css("background-color", "#E60000");</script>';
            ?>

            <div>
                <h3>Records with missing or empty required values</h3>
                <span>
                    <a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&amp;w=all&amp;q=ids:<?= implode(',', array_keys($ids)) ?>'>
                        (show results as search) <img src='../../common/images/external_link_16x16.gif'></a>
                    <a target=_new href='#' id=selected_link3 onClick="return open_selected_by_name('recCB3');">(show selected as search) <img src='../../common/images/external_link_16x16.gif'></a>
                </span>
            </div>

            <table>
                <tr>
                    <td colspan="4">
                        <label><input type=checkbox onclick="{mark_all_by_name(event.target, 'recCB3');}">Mark all</label>
                    </td>
                </tr>

                <?php
                $rec_id = null;
                foreach ($bibs as $row) {
                    if($rec_id!=$row['rec_ID']) {
                        ?>
                        <tr>
                            <td>
                                <input type=checkbox name="recCB3" value=<?= $row['rec_ID'] ?>>
                            </td>
                            <td style="white-space: nowrap;">
                                <a target=_new
                                    href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['rec_ID'] ?>'>
                                    <?= $row['rec_ID'] ?>
                                    <img class="rft" style="background-image:url(<?php echo HEURIST_ICON_URL.$row['rec_RecTypeID']?>.png)" title="<?php echo $row['rty_Name']?>" 
                                        src="<?php echo HEURIST_BASE_URL.'common/images/16x16.gif'?>">&nbsp;
                                    <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                                </a>
                            </td>
                            <td class="truncate" style="max-width:400px"><?=strip_tags($row['rec_Title']) ?></td>
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
            <br />
        </div>  <!-- End of Required Fields -->
            
            
        <!--  Records with non-standard fields (not listed in recstructure)  -->
        <div id="nonstandard_fields" style="top:110px"> <!-- Start of Non-Standard Fields -->
        <?php

        $query = "select rec_ID, rec_RecTypeID, dty_ID, dty_Name, dtl_Value, rec_Title, rty_Name
        from Records
        left join recDetails on rec_ID = dtl_RecID
        left join defDetailTypes on dty_ID = dtl_DetailTypeID
        left join defRecStructure on rst_RecTypeID = rec_RecTypeID and rst_DetailTypeID = dtl_DetailTypeID
        left join defRecTypes on rty_ID = rec_RecTypeID
        where rec_FlagTemporary!=1 AND rst_ID is null";            

        if(defined('DT_PARENT_ENTITY') && DT_PARENT_ENTITY>0){
            $query = $query." AND dty_ID != ".DT_PARENT_ENTITY;
        }    

        $res = $mysqli->query( $query );

        $bibs = array();
        $ids = array();
        while ($row = $res->fetch_assoc()){
            array_push($bibs, $row);
            $ids[$row['rec_ID']] = $row;
        }

            if (count($bibs) == 0) {
                print '<h3 class="res-valid">OK: No extraneous fields (fields not defined in the list for the record type)</h3>';
                echo '<script>$(".nonstandard_fields").css("background-color", "#6AA84F");</script>';
            }
            else
            {
                echo '<script>$(".nonstandard_fields").css("background-color", "#E60000");</script>';
                ?>
                <h3>Records with extraneous fields (not defined in the list of fields for the record type)</h3>
                <span>
                    <a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:<?= implode(',', array_keys($ids)) ?>'>
                        (show results as search) <img src='../../common/images/external_link_16x16.gif'></a>
                    <a target=_new href='#' id=selected_link4 onClick="return open_selected_by_name('recCB4');">(show selected as search) <img src='../../common/images/external_link_16x16.gif'></a>
                </span>
                <table>

                    <tr>
                        <td colspan="6">
                            <label><input type=checkbox onclick="{mark_all_by_name(event.target, 'recCB4');}">Mark all</label>
                        </td>
                    </tr>


                    <?php
                    $rec_id = null;
                    foreach ($bibs as $row) {
                        if($rec_id==null || $rec_id!=$row['rec_ID']) {
                            ?>
                            <tr>
                                <td>
                                    <input type=checkbox name="recCB4" value=<?= $row['rec_ID'] ?>>
                                </td>
                                <td style="white-space: nowrap;">
                                    <a target=_new
                                        href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['rec_ID'] ?>'>
                                        <?= $row['rec_ID'] ?>
                                        <img class="rft" style="background-image:url(<?php echo HEURIST_ICON_URL.$row['rec_RecTypeID']?>.png)" title="<?php echo $row['rty_Name']?>" 
                                            src="<?php echo HEURIST_BASE_URL.'common/images/16x16.gif'?>">&nbsp;
                                        <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                                    </a>
                                </td>

                                <td class="truncate" style="max-width:400px"><?=strip_tags($row['rec_Title']) ?></td>                      
                                <?php
                                $rec_id = $row['rec_ID'];
                            }else{
                                print '<tr><td colspan="3"></td>';
                            }
                            ?>

                            <td><?= $row['dty_ID'] ?></td>
                            <td width="100px" style="max-width:100px" class="truncate"><?= $row['dty_Name'] ?></td>
                            <td><?= strip_tags($row['dtl_Value']) ?></td>
                        </tr>
                        <?php
                    }
                    print "</table>";
                    ?>
                </table>
                <?php
            }
            ?>
            <br />
            <br />
        </div> <!-- End of Non-Standard Fields -->

        <!--
        <div id="origin_differences">
        <div>
        <h3>The database structure is cross-checked against the core and bibliographic definitions curated by the Heurist team</h3>
        <?php
        // $_REQUEST['verbose'] = 1;
        // $_REQUEST['filter_exact']  = HEURIST_DBNAME_FULL;
        //remove this remark along with html remarks include(dirname(__FILE__).'/verifyForOrigin.php');
        ?>
        </div>
        </div>
        -->

        <?php
        print '<div id="invalid_chars" style="top:110px">';     /* Start of Invalid Char Section */
        include(dirname(__FILE__).'/cleanInvalidChars.php');
        print '<br /></div>';     /* End of Invalid Char Section */

        print '<div id="title_mask" style="top:110px">';        /* Start of Title Mask Section */
        include(dirname(__FILE__).'/checkRectypeTitleMask.php');
        print '<br /><br /></div>';     /* End of Title Mask Section */
        ?>

        </div>
        <hr/>            

        <div>
            <br>
            <a href="longOperationInit.php?type=files&db=<?php echo HEURIST_DBNAME;?>" 
                target="_blank">Find duplicate and unused uploaded files (slow)</a>
        </div>


        </div>
        <script>
            /*
            var parent = $(window.parent.document);
            parent.find('#verification_output').css({width:'100%',height:'100%'}).show(); 
            parent.find('#in_porgress').hide();
            */    
        </script>        
    </body>
</html>