<?php
/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* checkRecURL.php - checks all record URLs to see if they are valid
*
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

$is_included = (defined('PDIR'));
$has_broken_url = false;
$passed_rec_ids = array();

if(isset($func_return) && $func_return == 1){
    return;
}

set_time_limit(0);

global $glb_curl_error;

if($is_included){
    $verbose = false;
    print '<div style="padding:10px"><h3 id="records_url_msg">Check Records URL</h3><br>';

}else{
    define('PDIR','../../');

    require_once dirname(__FILE__).'/../../hserv/System.php';

    $verbose = (@$_REQUEST['verbose']==1);

    $system = new System();
    if( ! $system->init(@$_REQUEST['db']) ){
        //get error and response
        print $system->getError()['message'];
        return;
    }
    if(!$system->is_admin()){ //  $system->is_dbowner()
        print '<span>You must be logged in as Database Administrator to perform this operation</span>';
    }
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
        <title>Check Records URL</title>
    </head>
    <body class="popup">
        <div class="banner">
            <h3>Check Records URL</h3>
        </div>
        <div id="page-inner">
<?php
}

$has_broken_url = checkURLs($system, false, $verbose);

if(!$is_included){
    print '</div></body></html>';
}else{

    if($has_broken_url){
        echo '<script>$(".records_url").css("background-color", "#E60000");</script>';
    }else{
        echo '<script>$(".records_url").css("background-color", "#6AA84F");</script>';
    }
    print '<br></div>';
}

function __updateRecords_lastverified($mysqli){
    global $passed_rec_ids;
    $date = date(DATE_8601);
    $query = 'UPDATE Records set rec_URLLastVerified="'.$date.'", rec_URLErrorMessage=null WHERE rec_ID in ('
            .implode(',',$passed_rec_ids) .')';
    $mysqli->query($query);

    $passed_rec_ids = array();//reset
}

//
// Check various sources for URLs and validate
// $return_output {boolean} => true = return array of result, false = return true or false for whether broken URLs were found
//
function checkURLs($system, $return_output, $verbose=false){

    global $is_included, $passed_rec_ids, $glb_curl_error;

    $is_heursit_reference_index = (strcasecmp(HEURIST_DBNAME,'Heurist_Reference_Index')==0);

    $mysqli = $system->get_mysqli();

    // Check rec_URL values
    $passed_cnt = 0;
    $broken_cnt = 0;
    $cnt_total = 0;

    $results = false;
    if($return_output){
        // [0] => Broken rec_URL, [1] => Broken Freetext/Blocktext URLs, [2] => Broken External File URLs in use
        $results = array(0 => array(0 => array(), 'count' => 0), 1 => array(), 2 => array());
    }

    $query = 'SELECT rec_ID, rec_URL, rec_RecTypeID FROM Records WHERE (rec_URL!="") AND (rec_URL IS NOT NULL)';

    $res = $mysqli->query($query);
    if ($res){

        while ($row = $res->fetch_row()){

            $rec_id = $row[0];
            $rec_url = $row[1];
            $rty_ID = $row[2];

            if($return_output){
                $results[0]['count'] ++;
            }

            $is_check_heurist_instance = $is_heursit_reference_index && ($rty_ID==101 || $rty_ID==103);
            if($is_check_heurist_instance){
                 $rec_url = $rec_url.'&isalive=1';
            }

/*
 https://int-heuristweb-prod.intersect.org.au/heurist/
 https://heuristref.net/heurist/
 https://int-heuristweb-prod.intersect.org.au/HEURIST/heurist/
 https://heurist.huma-num.fr:443/heurist/
 https://heurist.sfb1288.uni-bielefeld.de/
 https://heurisko.io/heurist/
 https://heurist.eie.gr/heurist/
 https://ship.lub.lu.se/heurist/
 https://heurist.fdm.uni-hamburg.de:443/html/heurist/
 http://fedora.gwin.gwiss.uni-hamburg.de/heurist/
 https://pfcmati.bnf.fr/heurist/
 https://heurist.researchsoftware.unimelb.edu.au/heurist/
 https://heurist.unige.ch/heurist
 https://dcsrs-test-ssp.ad.unil.ch/heurist/
*/

            //timeout 10 seconds (default 30)
            $data = loadRemoteURLContentWithRange($rec_url, "0-1000", true, 10);

            if ($data){

                $failed = false;

                if($is_check_heurist_instance && (!$return_output || $verbose) && strpos($data, 'error: ')===0){

                    $rec_url = htmlspecialchars($rec_url);
                    print intval($rec_id).' : <a href="'. $rec_url .'" target="_blank" rel="noopener">'.$rec_url . '</a>';

                    $data = strpos($data, 'timeout') !== false ? 'Timeout occurred' : $data;
                    $data = strpos($data, 'does not exist') !== false ? 'Database does not exist' : $data;

                    print ' <span style="color:red">'.htmlspecialchars($data).'</span><br>';

                    $failed = true;
                }

                if(!$failed){
                    $passed_cnt++;
                    $passed_rec_ids[] = intval($rec_id);
                    if(count($passed_rec_ids)>1000){
                        __updateRecords_lastverified($mysqli);
                    }
                }
            }else{
                $broken_cnt++;
                    //$glb_curl_error
                $upd_query = 'UPDATE Records set rec_URLLastVerified=?, rec_URLErrorMessage=? WHERE rec_ID='.$rec_id;
                $cnt = mysql__exec_param_query($mysqli, $upd_query,
                        array('ss', date(DATE_8601),
                        (isset($glb_curl_error)?substr($glb_curl_error,0,255):'')), true);

                if($verbose){
                    print '  error:'.(isset($glb_curl_error)?$glb_curl_error:'').'<br>';
                }elseif(!$return_output){

                    $rec_url = htmlspecialchars($rec_url);

                    print '<div>'.intval($rec_id).' : <a href="'.$rec_url.'" target="_blank" rel="noopener">'.$rec_url.'</a> '
                        .(isset($glb_curl_error)?$glb_curl_error:'').'</div>';
                }
                if($return_output){
                    $results[0][0][] = $rec_id;
                }
            }
            $cnt_total++;
            if($cnt_total % 10 == 0){
                ob_flush();
                flush();
            }
            //if($is_check_heurist_instance && $cnt_total>10){
            //    break;
            //}
        }
        $res->close();

        if(count($passed_rec_ids)>0){
            __updateRecords_lastverified($mysqli);
        }

        if(!$return_output || $verbose){

            echo '<p>Processed: '.$passed_cnt.' records</p>';

            if($broken_cnt>0){

                print '<div style="padding-top:20px;color:red">There are <b>'.$broken_cnt
                .'</b> records with broken url. Search "_BROKEN_" for details</div>';
            }else{
                print '<div><h3 class="res-valid">OK: All records have valid URL</h3></div>';
            }
        }
        if(!$return_output && $broken_cnt>0){
            $results = true;
        }

    }else{
        if(!$return_output){
            print '<div><h3 class="error"> Cannot execute search query to retrieve records URL '.$mysqli->error.'</h3></div>';
        }else{
            $results[0] = 'Cannot execute search query to retrieve records URL '.$mysqli->error;
        }
    }


    // Check freetext and blocktext fields for urls and validate
    $passed_cnt = 0;
    $rec_cnt = 0;
    $broken_cnt = 0;
    $broken_field_urls = array();

    if(!$return_output){
        if($is_included){
            print '<div style="padding:10px 10px 10px 0px"><h3 id="records_url_msg">Check Record Field URLs</h3><br>';
        }else{
            print '<div class="banner" style="padding-left: 0px;"><h3>Check Record Field URLs</h3></div>';
        }
    }

    $query = 'SELECT dtl_RecID, dtl_Value, dtl_DetailTypeID '
        . 'FROM recDetails '
        . 'INNER JOIN defDetailTypes ON dty_ID = dtl_DetailTypeID '
        . 'INNER JOIN Records ON rec_ID = dtl_RecID '
        . 'WHERE (dty_Type = "freetext" OR dty_Type = "blocktext") AND dtl_Value REGEXP "https?://"';

    $res = $mysqli->query($query);
    if($res){

        while($row = $res->fetch_row()){

            $rec_id = $row[0];
            $value = $row[1];
            $dty_id = $row[2];

            $urls = array();

            preg_match_all("/https?:\/\/[^\s\"'>()\\\\]*/", $value, $urls);

            if(is_array($urls) && count($urls[0]) > 0){

                $rec_cnt ++;

                foreach ($urls[0] as $url) {

                    /*if($return_output){
                        $results[1]['count'] ++;
                    }*/

                    if($url[-1] == '.'){
                        $url = substr($url, 0, -1);
                    }

                    $data = loadRemoteURLContentWithRange($url, "0-1000", true, 10);

                    if($data){
                        $passed_cnt ++;
                    }else{

                        if(!array_key_exists($rec_id, $broken_field_urls)){
                            $broken_field_urls[$rec_id] = array();
                        }
                        if(!array_key_exists($dty_id, $broken_field_urls[$rec_id])){
                            $broken_field_urls[$rec_id][$dty_id] = '';
                        }

                        $broken_cnt ++;
                        $broken_field_urls[$rec_id][$dty_id] .= '<div><a href="'.$url.'" target="_blank" rel="noopener">'.$url.'</a> '.$glb_curl_error.'</div>';

                        if($return_output){
                            if(!array_key_exists($rec_id, $results[1])){
                                $results[1][$rec_id] = array();
                            }
                            if(!array_key_exists($dty_id, $results[1][$rec_id])){
                                $results[1][$rec_id][$dty_id] = array();
                            }
                            $results[1][$rec_id][$dty_id][] = $url;
                        }
                    }
                }
            }
        }
        $res->close();
    }

    // Check external URLs in use
    $query = 'SELECT dtl_RecID, ulf_ExternalFileReference, dtl_DetailTypeID '
        . 'FROM recDetails '
        . 'INNER JOIN defDetailTypes ON dty_ID = dtl_DetailTypeID '
        . 'INNER JOIN recUploadedFiles ON ulf_ID = dtl_UploadedFileID '
        . 'WHERE dty_Type = "file" AND ulf_ExternalFileReference != ""';

    $res = $mysqli->query($query);
    if($res){

        while ($row = $res->fetch_row()) {

            $rec_id = $row[0];
            $url = $row[1];
            $dty_id = $row[2];

            /*if($return_output){
                $results[2]['count'] ++;
            }*/

            $data = loadRemoteURLContentWithRange($url, "0-1000", true, 10);

            if($data){
                $passed_cnt++;
            }else{
                if(!array_key_exists($rec_id, $broken_field_urls)){
                    $rec_cnt ++;
                    $broken_field_urls[$rec_id] = array();
                }
                if(!array_key_exists($dty_id, $broken_field_urls[$rec_id])){
                    $broken_field_urls[$rec_id][$dty_id] = '';
                }

                $broken_cnt ++;
                $broken_field_urls[$rec_id][$dty_id] .= '<div>'.$url.' '.$glb_curl_error.'</div>';

                if($return_output){
                    if(!array_key_exists($rec_id, $results[2])){
                        $results[2][$rec_id] = array();
                    }
                    if(!array_key_exists($dty_id, $results[2][$rec_id])){
                        $results[2][$rec_id][$dty_id] = array();
                    }
                    $results[2][$rec_id][$dty_id][] = $url;
                }
            }
        }
        $res->close();
    }

    // Report broken freetext/blocktext and file fields values
    if(!$return_output && $rec_cnt > 0){

        print '<p>Processed: '. $rec_cnt .' records, ' . $passed_cnt . ' urls passed</p>';

        $fld_query = 'SELECT rst_DisplayName FROM Records INNER JOIN defRecStructure ON rst_RecTypeID = rec_RecTypeID WHERE rec_ID = RECID AND rst_DetailTypeID IN (DTYID)';
        $def_name_query = 'SELECT dty_Name FROM defDetailTypes WHERE dty_ID IN (DTYID)';

        if(count($broken_field_urls) > 0){

            foreach ($broken_field_urls as $recid => $flds) {
                if(count($flds) == 0){
                    continue;
                }

                $dtyids = implode(',', array_keys($flds));

                $fld_names = mysql__select_list2($mysqli, str_replace(array('RECID', 'DTYID'), array($recid, $dtyids), $fld_query));
                if(empty($fld_names)){
                     $fld_names = mysql__select_list2($mysqli, str_replace(array('DTYID'), array($dtyids), $def_name_query));
                }

                print '<div>' . $recid . ': ' . implode(' ;', array_values($flds))
                        . '[found in field(s): ' . htmlspecialchars( implode(',', $fld_names) ). ']<br>';
            }

            $results = true;
            print '<div style="padding-top:20px;color:red"><b>'.$broken_cnt.'</b> broken urls found within record fields.</div>';

        }else{
            echo '<div><h3 class="res-valid">OK: All URLs in record fields are valid</h3></div>';
        }
    }

    return $results;
}
?>
