<?php

    /**
    * listRectypeRelations.php  Lists all fields, either together or as separate relationships and simple fields listings
    * action=simple gives text, numeric, term lists, geos, dates and file fields etc.
    * action=relations gives pointers and relationship markers
    * action=all gives both
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

    define('LOGIN_REQUIRED',1);   
    define('PDIR','../../');  //need for proper path to js and css    
    
    require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
    require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_structure.php');

    if(@$_REQUEST['action']=='relations'){$showRelationships=1;} else {$showRelationships=0;};
    if(@$_REQUEST['action']=='simple'){$showSimpleFields=1;} else {$showSimpleFields=0;};
    if(@$_REQUEST['action']=='all'){$showRelationships=1;$showSimpleFields=1;};

    $mysqli = $system->get_mysqli();

    $rtStructs = dbs_GetRectypeStructures($system,null,2);
    $rtTerms = dbs_GetTerms($system);
    $rtTerms = $rtTerms['termsByDomainLookup']['relation'];

    $idx_dt_type = $rtStructs['typedefs']['dtFieldNamesToIndex']['dty_Type'];
    $idx_dt_pointers = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
    $idx_dt_name = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
    $idx_dt_req = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_RequirementType'];
    $idx_dt_max = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_MaxValues'];

    $resrt = array();

    foreach ($rtStructs['typedefs'] as $rt_id=>$rt) {

        if(!@$rt['dtFields']) continue;

        $details = array();
        $rt_cnt = 0;

        foreach ($rt['dtFields'] as $dt_id=>$dt) {
            $dt_type = $dt[$idx_dt_type];

            if (($showRelationships) && ($dt_type=="resource")) {  // record pointer field
                $constraints = $dt[$idx_dt_pointers]; //list of rectypes - constraints for pointer
                $constraints = explode(",", $constraints);
                $rels = array();
                foreach($constraints as $pt_rt_id){
                    if(is_numeric($pt_rt_id)){
                        $rels[$pt_rt_id] = array('y',0);
                    }
                }
                $isconstrainded = count($rels);

                $query = "select r2.rec_RecTypeID, count(recDetails.dtl_ID) from Records r1, recDetails, "
                ."Records r2 where r1.rec_RecTypeID=$rt_id and dtl_RecID=r1.rec_ID and "
                ."dtl_DetailTypeID=$dt_id and dtl_Value=r2.rec_ID group by r2.rec_RecTypeID";
                $cnt = 0;
                if(count($details)==0){
                    $rt_cnt = get_rt_usage($rt_id);
                }

                if($rt_cnt>0){
                    $res = $mysqli->query($query);
                    if ($res) {
                        while (($row = $res->fetch_row())) {
                            $rels[$row[0]] = array(@$rels[$row[0]]?'y':'n', $row[1]);
                            $cnt = $cnt+$row[1];
                        }
                        $res->close();
                    }
                }

                array_push($details, array('dt_id'=>$dt_id, 'dt_name'=>$dt[$idx_dt_name], 'req'=>$dt[$idx_dt_req],
                    'max'=>$dt[$idx_dt_max], 'type'=>$dt_type, 'isconstrained'=>$isconstrainded, 'count'=>$cnt, 'rels'=>$rels));

            } // record pointer

            else if (($showRelationships) && ($dt_type=="relmarker")) { //relationship marker

                $constraints = $dt[$idx_dt_pointers];
                $constraints = explode(",", $constraints);
                $rels = array();
                foreach($constraints as $pt_rt_id){
                    if(is_numeric($pt_rt_id)){
                        $rels[$pt_rt_id] = array('y', 0, array());
                    }
                }
                $isconstrainded = count($rels);

                // TODO: explain these hard-coded detail type IDs
                $query = "SELECT rec3.rec_RecTypeID, rd2.dtl_Value as reltype, count(rec1.rec_ID) FROM Records rec1
                , recDetails rd2
                , recDetails rd3
                , recDetails rd1, Records rec3
                where rec1.rec_RecTypeID=1
                and rec1.rec_ID = rd1.dtl_RecID and rd1.dtl_DetailTypeID=7
                and rd1.dtl_Value in (select rec2.rec_ID from Records rec2 where rec2.rec_RecTypeID=$rt_id)
                and rec1.rec_ID = rd2.dtl_RecID and rd2.dtl_DetailTypeID=6
                and rec1.rec_ID = rd3.dtl_RecID and rd3.dtl_DetailTypeID=5 and rec3.rec_ID=rd3.dtl_Value
                group by rec3.rec_RecTypeID, rd2.dtl_Value order by rec3.rec_RecTypeID";
                $cnt = 0;
                if(count($details)==0){
                    $rt_cnt = get_rt_usage($rt_id);
                }

                if($rt_cnt>0){
                    $res = $mysqli->query($query);
                    if ($res) {
                        while (($row = $res->fetch_row())) {
                            $pt_rt_id = $row[0];
                            if($isconstrainded<1 && !@$rels[$pt_rt_id]){
                                $rels[$pt_rt_id] = array('n', 0, array());
                            }
                            if(@$rels[$pt_rt_id]){
                                $rels[$pt_rt_id][1] = $rels[$pt_rt_id][1] + $row[2];
                                $rels[$pt_rt_id][2][$row[1]] = $row[2];
                                $cnt = $cnt + $row[2];
                            }
                        }
                        $res->close();
                    }
                }

                array_push($details, array('dt_id'=>$dt_id, 'dt_name'=>$dt[$idx_dt_name], 'req'=>$dt[$idx_dt_req],
                    'max'=>$dt[$idx_dt_max], 'type'=>$dt_type, 'isconstrained'=>$isconstrainded, 'count'=>$cnt, 'rels'=>$rels));
            } // relmarker

            else if ($showSimpleFields) { // everything esle: freetext, memo, date, geo, terms, file

                // output simple fields listing
                $query = "select count(recDetails.dtl_ID) from Records r1, recDetails "
                ."where r1.rec_RecTypeID=$rt_id and dtl_RecID=r1.rec_ID and dtl_DetailTypeID=$dt_id";
                $res = $mysqli->query($query);
                $cnt = 0;
                if ($res) {
                    while (($row = $res->fetch_row())) {
                        $cnt = $row[0];
                    }
                    $res->close();
                }
                if(count($details)==0){
                    $rt_cnt = get_rt_usage($rt_id);
                }

                array_push($details, array('dt_id'=>$dt_id, 'dt_name'=>$dt[$idx_dt_name], 'req'=>$dt[$idx_dt_req],
                    'max'=>intval($dt[$idx_dt_max]), 'type'=>$dt_type, 'isconstrained'=>1, 'count'=>$cnt, 'rels'=>array()));
            } // simplefields
        }

        if(count($details)>0){
            $resrt[$rt_id] = array('name'=>$rtStructs['names'][$rt_id], 'count'=>$rt_cnt, "details"=>$details);
        }
    }

    function get_rt_usage($rt_id){
        global $mysqli;
        return mysql__select_value($mysqli, 'select count(*) from Records where rec_RecTypeID='.$rt_id);
    }

?>

<html>

    <head>

        <title>Heurist record type schema (simple fields, pointers and relationship markers)</title>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <!-- CSS -->
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
        
        <link rel="icon" href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

        <style type="text/css">
            body {
                background-color: #FFFFFF;
                overflow:auto;
            }
            .lvl0{
                padding-left: 0px;
                font-weight: normal;
                font-size: 12px;
                padding-top: 5px;
                padding-bottom: 5px;
            }
            .lvl1{
                padding-left: 20px;
            }
            .lvl2{
                padding-left: 160px;
            }
            .lvl3{
                padding-left: 200px;
            }
            .cerror{
                color: red;
            }
        </style>

    </head>

    <body>


        <div style="padding: 10px;">


            <?php
                print '<div class="lvl0"><b>Record type schema / usage</b></p>This listing shows ';

                if ($showSimpleFields && $showRelationships) {
                    print 'simple field types (text, numeric, terms, dates, geospatial and files), pointer fields and '.
                    'relationship marker fields <br/> for each record type, along with internal codes and occurrence in the database.<br/>';
                }  else if ($showRelationships) {
                    print 'record pointers and relationship markers for each record type, along with internal codes '.
                    'and occurrence in the database.<br/>';
                }  else if ($showSimpleFields) {
                    print 'simple field types (text, numeric, terms,dates, geospatial and files) for each record type, '.
                    'along with internal codes and occurrence in the database.<br/>';
                }

                if ($showRelationships) {
                    print 'Pointer fields are indicated by <i>[pointer]</i>, relationship marker fields by <i>[relationship]</i>. '.
                    'Pointed-to record types are separated by ||<br/>'.
                    'For constrained pointers, all defined target record types are listed. For unconstrained pointers, '.
                    'target record types present are listed.<br/>'.
                    'Relationships not defined by a relationship marker are not included in the counts.';
                }

                if ($showSimpleFields) {
                    print '';
                }

                print '</div>';

                foreach ($resrt  as $rt_id=>$rt){
                    // Record type, including total count
                    print '<div class="lvl0"><b>'.$rt['name']."</b>  (id ".$rt_id.", n=".$rt['count'].')</div>';

                    // Loop through record details (fields) in record structure
                    foreach ($rt['details']  as $details){
                        $dt_id = $details['dt_id'];

                        print '<div class="lvl1"><u><b>'.$details['dt_name'].'</b></u>'.
                        (($details['type']=="resource")?" <i>[pointer ":" <i>[".$details['type']);
                        if(@$details['req']){
                            print ", ".substr($details['req'],0,3)." ";
                        }
                        $mv = intval(@$details['max']);
                        print ", ".($mv==1?"sng":($mv>1?"lim":"rpt"))." ";

                        print "]</i> ";

                        $uncontrained = ($details['isconstrained']<1);

                        if($uncontrained){
                            print " unconstrained ";
                        }
                        print " (id ".$dt_id.", n=".$details['count'].') ';

                        // Usage count for each pointed-to record type
                        foreach ($details['rels']  as $pt_rt_id=>$data){
                            if(!@$rtStructs['names'][$pt_rt_id]){
                                print '<div class="lvl1  cerror"> Encountered pointer to incorrect record type id =  '.$pt_rt_id.
                                " (please run Utilities > Verify Data Consistency to fix)</div>";
                            }else{
                                print '&nbsp; || &nbsp;'; // separator between record types poitned to by pointer field
                                print '<b>'.$rtStructs['names'][$pt_rt_id]."</b> (id ".$pt_rt_id.", n=".$data[1].") ";

                                if(@$data[2]){ //terms
                                    $notfirst = false;
                                    print ' [ ';
                                    foreach ($data[2]  as $term_id=>$cnt){

                                        if($notfirst) print ", ";
                                        $notfirst = true;

                                        print $rtTerms[$term_id][0]." (n=".$cnt.")";
                                    }
                                    print ' ]';
                                }
                            }
                        }
                        print '</div>';

                    } // end record details (fields) loop

                    print "<br />";
                }
            ?>

        </div>

    </body>

</html>