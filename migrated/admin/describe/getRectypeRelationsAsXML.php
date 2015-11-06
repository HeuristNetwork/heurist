<?php

    /**
    * getRectypeRelationsAsXML.php: Lists the relations between records as XML. Non-vidual version of listRecTypeRelations.php
    *                               For use in visualisation of entities and relationships
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @author      Jan Jaap de Groot    <jjedegroot@gmail.com>
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

    require_once (dirname(__FILE__) . '/../../common/connect/applyCredentials.php');
    require_once (dirname(__FILE__) . '/../../common/php/getRecordInfoLibrary.php');

    if (!is_logged_in()) {
        header("HTTP/1.1 401 Unauthorized");
        exit;
    }

    // We are going to represent XML. Must be on top.
    header("Content-Type: application/xml");
    print '<?xml version="1.0" encoding="UTF-8"?>';

    // Code below is to grab data; copied from listRectypeRelations.php
    // and removed unneccessary parts to just generate XML for entities and relationships
    mysql_connection_select(DATABASE);
    $rtStructs = getAllRectypeStructures(true);
    $rtTerms = getTerms(true);
    $rtTerms = $rtTerms['termsByDomainLookup']['relation'];

    $image_base_url = HEURIST_SERVER_URL . "/HEURIST_FILESTORE/" . HEURIST_DBNAME . "/rectype-icons/";
    $idx_dt_type = $rtStructs['typedefs']['dtFieldNamesToIndex']['dty_Type'];
    $idx_dt_pointers = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
    $idx_dt_name = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
    $idx_dt_req = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_RequirementType'];
    $idx_dt_max = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_MaxValues'];

    $resrt = array();

    foreach ($rtStructs['typedefs'] as $rt_id=>$rt) {

        if(!is_numeric($rt_id)) continue;   // ??? what does this do ???

        $details = array();
        $rt_cnt = 0;

        foreach ($rt['dtFields'] as $dt_id=>$dt) {
            $dt_type = $dt[$idx_dt_type];

            if ($dt_type=="resource") {  // pointer field
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
                    $res = mysql_query($query);
                    if ($res) {
                        while (($row = mysql_fetch_array($res))) {
                            $rels[$row[0]] = array(@$rels[$row[0]]?'y':'n', $row[1]);
                            $cnt = $cnt+$row[1];
                        }
                    }
                }

                array_push($details, array('dt_id'=>$dt_id, 'dt_name'=>$dt[$idx_dt_name], 'req'=>$dt[$idx_dt_req],
                    'max'=>$dt[$idx_dt_max], 'type'=>$dt_type, 'isconstrained'=>$isconstrainded, 'count'=>$cnt, 'rels'=>$rels));

            } // pointer

            else if ($dt_type=="relmarker") {

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

                array_push($details, array('dt_id'=>$dt_id, 'dt_name'=>$dt[$idx_dt_name], 'req'=>$dt[$idx_dt_req],
                    'max'=>$dt[$idx_dt_max], 'type'=>$dt_type, 'isconstrained'=>$isconstrainded, 'count'=>$cnt, 'rels'=>$rels));
            } // relmarker
        }

        if(count($details)>0){
            $resrt[$rt_id] = array('name'=>$rtStructs['names'][$rt_id], 'count'=>$rt_cnt, "details"=>$details);
        }
    }

    function get_rt_usage($rt_id){
        $res = mysql__select_array("Records","count(*)","rec_RecTypeID=".$rt_id);
        return $res[0];
    }
    // File headers to explain what the listing represents and for version checking
    print "\n<Relationships>";
    print "\n\n<!--Heurist Definitions Exchange File, generated: ".date("d M Y @ H:i")."-->";
    print "\n<HeuristBaseURL>" . HEURIST_BASE_URL_V3. "</HeuristBaseURL>";
    print "\n<HeuristDBName>" . HEURIST_DBNAME . "</HeuristDBName>";
    print "\n<HeuristProgVersion>".HEURIST_VERSION."</HeuristProgVersion>";
    print "\n<HeuristDBVersion>".HEURIST_DBVERSION."</HeuristDBVersion>";

    // Retrieve relations
    print "\n\n<RecordTypes>";
    print "\n<!--Records with relations between them-->";
    foreach ($resrt  as $rt_id=>$rt){
        // Record overview
        print "\n\n<Record xmlns='rootrecord'>";
        print "\n<rec_Name>" .$rt['name']. "</rec_Name>";
        print "\n<rec_ID>" .$rt_id. "</rec_ID>";
        print "\n<rec_Count>" .$rt['count']. "</rec_Count>";
        print "\n<rec_Image>" .$image_base_url.$rt_id. ".png</rec_Image>";

        // Loop through record details (fields) in record structure
        print "\n<rec_Relations>";
        foreach ($rt['details']  as $details){
            // Record overview
            $dt_id = $details['dt_id'];
            print "\n<Record xmlns='relationrecord'>";
            print "\n<rec_Name>" .$details['dt_name']. "</rec_Name>";
            print "\n<rec_ID>" .$dt_id. "</rec_ID>";
            print "\n<rec_Count>" .$details['count']. "</rec_Count>";
            print "\n<rec_Image>" .$image_base_url.$dt_id. ".png</rec_Image>";
            
            // Unconstrained check
            print "\n<rel_Unconstrained>";
            if($details['isconstrained'] < 1) {
                print "true";    
            }else{
                print "false";
            }
            print "</rel_Unconstrained>";

            // Relation types
            print "\n<RelationTypes>";

            // Type check
            print "\n<rel_Name>";
            if($details['type']=="resource") {
                print "pointer";
            }else{
                print $details['type'];
            }
            print "</rel_Name>";

            // Req check
            if(@$details[req]){
                print "\n<rel_Name>" .substr($details[req],0,3). "</rel_Name>";
            }

            // Max check
            print "\n<rel_Name>";
            $mv = intval(@$details['max']);
            if($mv == 1) {
                print "sng";
            }else if($mv > 1) {
                print "lim";
            }else{
                print "rpt";
            }
            print "</rel_Name>";
            print "\n</RelationTypes>";

            // Usage count for each pointed-to record type
            print "\n<Usages>";
            foreach ($details['rels']  as $pt_rt_id=>$data){
                print "\n<Record xmlns='usagerecord'>";
                if(!@$rtStructs['names'][$pt_rt_id]){
                    print "<Error>Pointer to incorrect record type; id= " .$pt_rt_idl;
                }else{
                    // Record overview
                    print "\n<rec_Name>" .$rtStructs['names'][$pt_rt_id]. "</rec_Name>";
                    print "\n<rec_ID>" .$pt_rt_id. "</rec_ID>";
                    print "\n<rec_Count>" .$data[1]. "</rec_Count>";
                    print "\n<rec_Image>" .$image_base_url.$pt_rt_id. ".png</rec_Image>";

                    // Terms
                    if(@$data[2]){ //terms
                        print "\n<Terms>";
                        foreach ($data[2] as $term_id=>$cnt){
                            print "<term_Name>" .$rtTerms[$term_id][0]. "</term_Name>";
                            print "<term_Count>" .$cnt. "</term_Count>";
                        }
                        print "\n</Terms>";
                    }
                }
                print "\n</Record>";
            }
            print "\n</Usages>";
            print "\n</Record>";
        } // end record details (fields) loop

        print "\n</rec_Relations>";
        print "\n</Record>";
    }
    
    // RECORDS WITH ZERO COUNTS
    $query = "SELECT * FROM defRecTypes WHERE rty_ID NOT IN (SELECT DISTINCT rec_recTypeID FROM Records) ORDER BY rty_Name ASC;";
    $res = mysql_query($query);
    print "\n<!--Records without relations between them-->";
    while ($row = mysql_fetch_row($res)) { // each loop is a complete table row
        $rt_id = $row[0];
        $rt_name = $row[1];
        
        print "\n\n<Record xmlns='rootrecord'>";
        print "\n<rec_Name>" .$rt_name. "</rec_Name>";
        print "\n<rec_ID>" .$rt_id. "</rec_ID>";
        print "\n<rec_Count>0</rec_Count>";
        print "\n<rec_Image>" .$image_base_url.$rt_id. ".png</rec_Image>";
        print "\n</Record>";
    }
    

    print "\n\n</RecordTypes>";
    print "\n</Relationships>";
?>
