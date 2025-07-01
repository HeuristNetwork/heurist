<?php
/**
* fieldsWithIndividualTermSelection.php - Reports on enumeration fields using individual term selection versus vocabulary-based selection.
*
* @fileOverview This script iterates through all databases on the server and examines
*               all detail types that are of type 'enum' or 'relmarker'. For each such field,
*               it analyzes its term selection configuration (`dty_JsonTermIDTree` and
*               `dty_TermIDTreeNonSelectableIDs`). It reports whether the field uses
*               a single vocabulary, multiple vocabularies, or individual terms for selection.
*               The output can be an HTML table or a CSV file, detailing the field name, ID, type,
*               vocabulary count, term count, exclusion count, and record usage.
*               Requires owner-level access.
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.1
*/
define('OWNER_REQUIRED',1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/structure/search/dbsData.php';

$mysqli = $system->getMysqli();

$is_csv = (@$_REQUEST['html']!=1);

    //1. find all database
    $databases = mysql__getdatabases4($mysqli, true);

    if(!$is_csv){

        print '<h4>Fields With Individual Term Selection</h4>';
        print '<table border=1>';
        print '<tr><td>Field</td><td>ID</td><td>Type</td><td>Vocab Cnt</td><td>Terms count</td><td>Exclusions Count</td><td>Records</td></tr>';
    }else{
        $fd = fopen(TEMP_MEMORY, 'w');//less than 1MB in memory otherwise as temp file
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

        $db_name = preg_replace(REGEX_ALPHANUM, "", $db_name);//for snyk

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

        while ($row = $res->fetch_row()) {

            //parse
            $terms = getTermsFromFormat(@$row[1]);//see dbsData.php

            if (is_array($terms) && count($terms) ==1 ) { //vocabulary
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

            $vocab_count = count(array_keys($vocabs));

            if($vocab_count>1){
                $query = 'SELECT count(distinct dtl_RecID) from '
                    .$db_name.'.recDetails WHERE  dtl_DetailTypeID='.$row[3];
                $rec_usage = mysql__select_value($mysqli, $query);
            }else{
                $rec_usage = 'not determined';
            }



            $nonTerms = getTermsFromFormat(@$row[2]);
            $is_idis = (!isEmptyArray($nonTerms));
            if($is_idis){
                $cnt3++;
            }

            if(!$is_csv){
                print TR_S.htmlspecialchars($row[0]).TD.htmlspecialchars($row[3]).TD.htmlspecialchars($row[4]).'</td>'
                    .'<td>'.$vocab_count.TD.count($terms).TD.($is_idis?count($nonTerms):'').TD.intval($rec_usage).TR_E;
            }else {
                //'",'.$row[3]. ($is_vocab?'1':'').','.($is_vocab?'':'1').


                $record_row = array($db_name, $row[0], $row[3], $row[4], $vocab_count, count($terms), ($is_idis?count($nonTerms):''), $rec_usage);
                fputcsv($fd, $record_row, ',', '"');
            }
        }
        $cnt4++;
    }//while  databases
    if(!$is_csv){
        print '</table>';
        print '[end report]';
    }else{

        $filename = 'NonVocabFields.csv';

        rewind($fd);
        $out = stream_get_contents($fd);
        fclose($fd);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename='.$filename);
        header(CONTENT_LENGTH . strlen($out));

        echo $out;
    }

    /**
     * Recursively finds the top-most parent (vocabulary root) of a given term.
     *
     * @param string          $db_name The full name of the database (e.g., 'hdb_mydb').
     * @param \mysqli         $mysqli  The mysqli database connection object.
     * @param int             $termId  The ID of the term for which to find the top-most parent.
     * @param array<int>|null $terms   Optional. Used for internal recursion tracking to prevent infinite loops.
     *                                 Should be null or an empty array on initial call.
     * @return int|null The ID of the top-most parent term, or null if not found or an error occurs.
     */
    function getTermTopMostParent22($db_name, $mysqli, $termId, $terms=null){

        if(!$terms) {$terms = array($termId);}//to prevent recursion

        $query = "select trm_ParentTermID from $db_name.defTerms where trm_ID = ".$termId;

        $row = mysql__select_row($mysqli, $query);

        if($row!=null){
            $parentId = @$row[0];
        }else{
            return null;
        }

        if($parentId>0 && !in_array($parentId, $terms)){ //avoid recursion
            array_push($terms, $parentId);
            $termId = getTermTopMostParent22($db_name, $mysqli, $parentId, $terms);
        }
        return $termId;
    }
