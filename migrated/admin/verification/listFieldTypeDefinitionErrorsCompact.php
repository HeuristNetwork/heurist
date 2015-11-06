<?php

    /*
    * Copyright (C) 2005-2015 University of Sydney
    *
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
    * in compliance with the License. You may obtain a copy of the License at
    *
    * http://www.gnu.org/licenses/gpl-3.0.txt
    *
    * Unless required by applicable law or agreed to in writing, software distributed under the License
    * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
    * or implied. See the License for the specific language governing permissions and limitations under
    * the License.
    */

    /**
    * listFieldTypeDefinitionErrorsCompact.php - identifies invalid terms and record pointer constraints in field definitions
    * Compact version in popup for editRecord. Normal version is used by Designer View > Utilities
    * 
    * TODO: This largely duplicates similar funciton in listFieldTypeDefinitionErrors.php - the two shoudl be combined
    *       and fixing of the errors shoudl be automated, possibly with an email to development team so we are aware of problem
    *
    * @author      Tom Murtagh
    * @author      Kim Jackson
    * @author      Ian Johnson   <ian.johnson@sydney.edu.au>
    * @author      Stephen White   <stephen.white@sydney.edu.au>
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2015 University of Sydney
    * @link        http://Sydney.edu.au/Heurist
    * @version     3.1.0
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */

    require_once('getFieldTypeDefinitionErrors.php');

    if(@$_REQUEST['data']){
        $lists = json_decode($_REQUEST['data'], true);
    }else{
        $lists = getInvalidFieldTypes(@$_REQUEST['rt']);
        if(!@$_REQUEST['show']){
            if(count($lists["terms"])==0 && count($lists["terms_nonselectable"])==0 && count($lists["rt_contraints"])==0){
                $lists = array();   
            }
            print json_encode($lists);
            exit();
        }
    }

    $dtysWithInvalidTerms = $lists["terms"];
    $dtysWithInvalidNonSelectableTerms = $lists["terms_nonselectable"];
    $dtysWithInvalidRectypeConstraint = $lists["rt_contraints"];
?>    
<html>
    <head>
        <title>Invalid Field Type Definition check</title>
        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
        <script src="../../common/js/utilsUI.js"></script>
        <style type="text/css">
            h3, h3 span {
                display: inline-block;
                padding:0 0 10px 0;
            }
            .msgline {
                line-height:2em;
            }
        </style>
    </head>

    <body class="popup" style="width:95%;">

        <div id="page-inner" style="top:5px;">

            <script>
                function repairFieldTypes(){

                    function _callback(context){
                        if(top.HEURIST.util.isnull(context) || top.HEURIST.util.isnull(context['result'])){
                            top.HEURIST.util.showError(null);
                        }else{
                            alert(context['result']);
                        }
                    }

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

                    var str = JSON.stringify(dt);

                    var baseurl = top.HEURIST.baseURL_V3 + "admin/verification/repairFieldTypes.php";
                    var callback = _callback;
                    var params = "db=<?= HEURIST_DBNAME?>&data=" + encodeURIComponent(str);
                    top.HEURIST.util.getJsonData(baseurl, callback, params);
                }
            </script>


            <br/><h3>Warning: Field defintions with unknown codes for terms and/or record types</h3><br/>&nbsp;<br/>
            The following field definitions have inconsistent data. This is nothing to be concerned about, unless it reoccurs frequently, in which case please advise Heurist developers<br/><br/>
            To fix the inconsistencies, please click here: <button onclick="repairFieldTypes()">Auto Repair</button>  <br/>&nbsp;<br/>

            <hr/>
            <?php
                if (count($dtysWithInvalidTerms)>0){
                ?>
                <!--
                <div>
                <h3>Enumeration, Relationship type or Relationship marker field types with invalid terms definitions</h3>
                </div>
                -->            
                <?php
                    foreach ($dtysWithInvalidTerms as $row) {
                    ?>
                    <div class="msgline"><b><?= $row['dty_Name'] ?></b> field (code <?= $row['dty_ID'] ?>) has 
                        <?= count($row['invalidTermIDs'])?> invalid term ID<?=(count($row['invalidTermIDs'])>1?"s":"")?> 
                        (code: <?= join(",",$row['invalidTermIDs'])?>)
                    </div>
                    <?php
                    }//for
                }
                if (count($dtysWithInvalidNonSelectableTerms)>0){
                ?>
                <!--			<div>
                <h3>Enumeration, Relationship type or Relationship marker field types with invalid non-selectable terms definitions</h3>
                </div> -->
                <?php
                    foreach ($dtysWithInvalidNonSelectableTerms as $row) {
                    ?>
                    <div class="msgline"><b><?= $row['dty_Name'] ?></b> field (code <?= $row['dty_ID'] ?>) has 
                        <?= count($row['invalidNonSelectableTermIDs'])?> invalid non selectable term ID<?=(count($row['invalidNonSelectableTermIDs'])>1?"s":"")?> 
                        (code: <?= join(",",$row['invalidNonSelectableTermIDs'])?>)
                    </div>
                    <?php
                    }
                }
                if (count($dtysWithInvalidRectypeConstraint)>0){
                ?>
                <!--
                <div>
                <h3>Record Pointer or Relationship Marker field types with invalid record type(s) in constraint definitions</h3> 
                </div> -->
                <?php
                    foreach ($dtysWithInvalidRectypeConstraint as $row) {
                    ?>
                    <div class="msgline"><b><?= $row['dty_Name'] ?></b> field (code <?= $row['dty_ID'] ?>) has 
                        <?= count($row['invalidRectypeConstraint'])?> invalid record type constraint<?=(count($row['invalidRectypeConstraint'])>1?"s":"")?> 
                        (code: <?= join(",",$row['invalidRectypeConstraint'])?>)
                    </div>

                    <?php
                    }
                }
            ?>
        </div>
    </body>
</html>

