<?php
    /*
    * Copyright (C) 2005-2020 University of Sydney
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
    * Print out enum fields with vocabulary or individual terms selection
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2020 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */
define('OWNER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_structure.php');

$mysqli = $system->get_mysqli();

$is_csv = (@$_REQUEST['html']!=1);
    
    //1. find all database
    $query = 'show databases';

    $res = $mysqli->query($query);
    if (!$res) {  print $query.'  '.$mysqli->error;  return; }
    $databases = array();
    while (($row = $res->fetch_row())) {
        if( strpos($row[0], 'hdb_')===0 ){
            //if($row[0]>'hdb_Masterclass_Cookbook')
                $databases[] = $row[0];
        }
    }

    if(!$is_csv){
        
        print '<h4>Fields With Individual Term Selection</h4>';        
        print '<table border=1>';    
        print '<tr><td>Field</td><td>ID</td><td>Type</td><td>Vocab Cnt</td><td>Terms count</td><td>Exclusions Count</td><td>Records</td></tr>';
    }else{
        $fd = fopen('php://temp/maxmemory:1048576', 'w');  //less than 1MB in memory otherwise as temp file 
        if (false === $fd) {
            die('Failed to create temporary file');
        }        
        $record_row = array('Database','Field','ID','Type','Vocab Cnt','Terms count','Exclusions Count','Records');
        fputcsv($fd, $record_row, ',', '"');
        $record_row = array('Term-list fields which use individual term selection','','','','','','','');
        fputcsv($fd, $record_row, ',', '"');
    }
        
        $cnt1 = 0;
        $cnt2 = 0;
        $cnt3 = 0;
        $cnt4 = 0;
    
    foreach ($databases as $idx=>$db_name){
        
        $rec_types = array();
        $det_types = array();
        $terms = array();
        $is_found = false;

        $query = 'SELECT dty_Name,dty_JsonTermIDTree, dty_TermIDTreeNonSelectableIDs, dty_ID, dty_Type FROM '
            .$db_name.'.defDetailTypes WHERE  dty_Type="enum" or dty_Type="relmarker"';
        
        $res = $mysqli->query($query);
        if (!$res) {  print $query.'  '.$mysqli->error;  return; }
        
        if(!$is_csv){
            print '<tr><td colspan=7><i>'.substr($db_name,4).'</i></td></tr>';
        }

        while (($row = $res->fetch_row())) {
            
            /*
            $key = intval(@$row[1]);
            $is_vocab = ($key>0);
            if($is_vocab){
                $cnt1++;
            }else{
                $cnt2++;
            }
            if($is_vocab) continue;
            */
            
            //parse
            $terms = getTermsFromFormat(@$row[1]); //db_structure
            
            if ( count($terms) ==1 ) { //vocabulary
                    $cnt1++;
                    continue;
            }
            
            //find vocabularies for all allowed terms
            $vocabs = array();
            foreach($terms as $termId){
                $vocabid = getTermTopMostParent22($db_name, $mysqli, $termId);    
                if($vocabid!=null){
                    $vocabs[$vocabid] = 1;    
                }
            }
            /*if($row[3]==197){
                print '<tr><td colspan=5>'.print_r($vocabs,true).'</td></tr>';
            }*/
            
            $vocab_count = count(array_keys($vocabs));
            
            if($vocab_count>1){
                $query = 'SELECT count(distinct dtl_RecID) from '
                    .$db_name.'.recDetails WHERE  dtl_DetailTypeID='.$row[3];                
                $rec_usage = mysql__select_value($mysqli, $query);    
            }else{
                $rec_usage = 'not determined';
            }
            
            
            
            $nonTerms = getTermsFromFormat(@$row[2]); 
            $is_idis = (count($nonTerms)>0);
            if($is_idis){
                $cnt3++;
            }
            
            if(!$is_csv){
                print '<tr><td>'.$row[0].'</td><td>'.$row[3].'</td><td>'.$row[4].'</td>'
                    .'<td>'.$vocab_count.'</td><td>'.count($terms).'</td><td>'.($is_idis?count($nonTerms):'').'</td><td>'.$rec_usage.'</td></tr>';
            }else {
                //'",'.$row[3]. ($is_vocab?'1':'').','.($is_vocab?'':'1').
                //print $db_name.',"'.$row[0].'",'.$row[3].','.($is_idis?'1':'').PHP_EOL;
                
                $record_row = array($db_name, $row[0], $row[3], $row[4], $vocab_count, count($terms), ($is_idis?count($nonTerms):''), $rec_usage);
                fputcsv($fd, $record_row, ',', '"');
            }
        } 
        $cnt4++;
        //if($cnt4>20) break;
    }//while  databases
    if(!$is_csv){
        //  print '<tr><td colspan="2"></td><td>'.$cnt2.'</td><td>'.$cnt3.'</td></tr>';
        print '</table>';
        print '[end report]';
    }else{
        
        $filename = 'NonVocabFields.csv';
        
        rewind($fd);
        $out = stream_get_contents($fd);
        fclose($fd);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename='.$filename);
        header('Content-Length: ' . strlen($out));
        
        exit($out);
        
    }
    
    function getTermTopMostParent22($db_name, $mysqli, $termId, $terms=null){
        
        if(!$terms) $terms = array($termId); //to prevent recursion

        $query = "select trm_ParentTermID from $db_name.defTerms where trm_ID = ".$termId;

        $row = mysql__select_row($mysqli, $query);

        if($row!=null){
            $parentId = @$row[0];
        }else{
            return null;
        }
        
      /*
        $parentId = mysql__select_value($mysqli, $query);
        if($termId==3329){
print $query.' '.$termId.' parent '.$parentId.'<br>';
        }
      */  
        if($parentId>0){
            
            if(in_array($parentId, $terms)){ //avoid recursion
                return $termId;
            }else{
                array_push($terms, $parentId);
                return getTermTopMostParent22($db_name, $mysqli, $parentId, $terms);
            }
        }else{
            return $termId;
        }
    }
    
?>
