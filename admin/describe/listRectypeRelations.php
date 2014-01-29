<?php
/*
* Copyright (C) 2005-2013 University of Sydney
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
* listRectypeRelations.php
* 
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
  
require_once (dirname(__FILE__) . '/../../common/connect/applyCredentials.php');
require_once (dirname(__FILE__) . '/../../common/php/getRecordInfoLibrary.php');

if (!is_logged_in()) {
    header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db=' . HEURIST_DBNAME);
    return;
}

mysql_connection_select(DATABASE);
$rtStructs = getAllRectypeStructures(true);
$rtTerms = getTerms(true);
$rtTerms = $rtTerms['termsByDomainLookup']['relation'];

$idx_dt_type = $rtStructs['typedefs']['dtFieldNamesToIndex']['dty_Type'];
$idx_dt_pointers = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
$idx_dt_name = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
$idx_dt_req = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_RequirementType'];

$resrt = array();

foreach ($rtStructs['typedefs'] as $rt_id=>$rt) {
    
    if(!is_numeric($rt_id)) continue;
    
    $details = array();
    $rt_cnt = 0;
    
    foreach ($rt['dtFields'] as $dt_id=>$dt) {
        
        $dt_type = $dt[$idx_dt_type];
        if($dt_type=="resource"){
            $constraints = $dt[$idx_dt_pointers];
            $constraints = explode(",", $constraints);
            $rels = array();
            foreach($constraints as $pt_rt_id){
                if(is_numeric($pt_rt_id)){
                    $rels[$pt_rt_id] = array('y',0);
                }
            }
            $isconstrainded = count($rels);
            
            $query = "select r2.rec_RecTypeID, count(recDetails.dtl_ID) from Records r1, recDetails, Records r2 where r1.rec_RecTypeID=$rt_id and dtl_RecID=r1.rec_ID and "
            ."dtl_DetailTypeID=$dt_id and dtl_Value=r2.rec_ID group by r2.rec_RecTypeID";
            $cnt = 0;
            if(count($details)==0){
                  $rt_cnt = get_rt_usage($rt_id);
            }
            
            if($rt_cnt>0){
                    $res = mysql_query($query);
                    if ($res) {
                        while (($row = mysql_fetch_array($res))) {
                            $rels[$row[0]] = array(@$rels[$row[0]]?'y':'n', $row[1]);
                            $cnt = $cnt+$row[1];
                        }
                    }
            }
   
             array_push($details, array('dt_id'=>$dt_id, 'dt_name'=>$dt[$idx_dt_name], 'req'=>$dt[$idx_dt_req], 'type'=>$dt_type,
                    'isconstrained'=>$isconstrainded, 'count'=>$cnt, 'rels'=>$rels));
            
        }else if($dt_type=="relmarker"){

            $constraints = $dt[$idx_dt_pointers];
            $constraints = explode(",", $constraints);
            $rels = array();
            foreach($constraints as $pt_rt_id){
                if(is_numeric($pt_rt_id)){
                    $rels[$pt_rt_id] = array('y', 0, array());
                }
            }
            $isconstrainded = count($rels);
                     
            $query = "SELECT rec3.rec_RecTypeID, rd2.dtl_Value as reltype, count(rec1.rec_ID) FROM Records rec1
, recDetails rd2  
, recDetails rd3  
, recDetails rd1, Records rec3  
where rec1.rec_RecTypeID=1 
and rec1.rec_ID = rd1.dtl_RecID and rd1.dtl_DetailTypeID=7 and rd1.dtl_Value in (select rec2.rec_ID from Records rec2 where rec2.rec_RecTypeID=$rt_id)
and rec1.rec_ID = rd2.dtl_RecID and rd2.dtl_DetailTypeID=6 
and rec1.rec_ID = rd3.dtl_RecID and rd3.dtl_DetailTypeID=5 and rec3.rec_ID=rd3.dtl_Value
group by rec3.rec_RecTypeID, rd2.dtl_Value order by rec3.rec_RecTypeID";
            $cnt = 0;
            if(count($details)==0){
                  $rt_cnt = get_rt_usage($rt_id);
            }
            
            if($rt_cnt>0){
                    $res = mysql_query($query);
                    if ($res) {
                        while (($row = mysql_fetch_array($res))) {
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
                    }
            }
   
             array_push($details, array('dt_id'=>$dt_id, 'dt_name'=>$dt[$idx_dt_name], 'req'=>$dt[$idx_dt_req], 'type'=>$dt_type,
                    'isconstrained'=>$isconstrainded, 'count'=>$cnt, 'rels'=>$rels));
        }
    }
    
    if(count($details)>0){
        $resrt[$rt_id] = array('name'=>$rtStructs['names'][$rt_id], 'count'=>$rt_cnt, "details"=>$details);
    }
}

function get_rt_usage($rt_id){
    $res = mysql__select_array("Records","count(*)","rec_RecTypeID=".$rt_id);
    return $res[0];
}

?>
<html>
 <head>
  <title>Heurist record type relations and pointers</title>

  <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
  <link rel="icon" href="../../favicon.ico" type="image/x-icon">
  <link rel="shortcut icon" href="../../favicon.ico" type="image/x-icon">

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

print '<div class="lvl0">This listing shows the record pointers and relationship markers for each record type, along with internal codes and occurrence in the database.<br/>'.
    'Pointer fields are indicated by <i>[pointer]</i>, relationship marker fields by <i>[relationship]</i>. Pointed-to record types are separated by ||<br/>'.
    'For constrained pointers, all defined target record types are listed. For unconstrained pointers, target record types present are listed.<br/>'.
    'Relationships not defined by a relationship marker are not included in the counts.&nbsp;</div>';

foreach ($resrt  as $rt_id=>$rt){
    
    // Record type, including total count
    print '<div class="lvl0"><b>'.$rt['name']."</b>  (id ".$rt_id.", n=".$rt['count'].')</div>';
    
    // Loop through record details (fields) in record structure
    foreach ($rt['details']  as $details){
        
            $dt_id = $details['dt_id'];
        
            print '<div class="lvl1"><u><b>'.$details['dt_name'].'</b></u>'.(($details['type']=="resource")?" <i>[pointer]</i> ":" <i>[relationship]</i> ");
            
            $uncontrained = ($details['isconstrained']<1);
                        
            if($uncontrained){
                print " unconstrained ";
            }
            print " (id ".$dt_id.", n=".$details['count'].') ';
            
            // Usage count for each pointed-to record type
            foreach ($details['rels']  as $pt_rt_id=>$data){
                if(!@$rtStructs['names'][$pt_rt_id]){
                    print '<div class="lvl2  cerror">'.$pt_rt_id.": INCORRECT RECORD TYPE</div>";
                }else{
                    print '&nbsp; || &nbsp;'; // separator between record types poitned to by pointer field
                    // TODO: what is function of first part of thist print statement
                    print (($uncontrained || $data[0]=="y")?"":" cerror").'<b>'.$rtStructs['names'][$pt_rt_id]."</b> (id ".$pt_rt_id.", n=".$data[1].") ";
                    
                    
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

 </body> 
</html>