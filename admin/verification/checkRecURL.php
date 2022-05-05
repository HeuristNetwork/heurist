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
* checkRecURL.php - checks all record URLs to see if they are valid
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

$is_included = (defined('PDIR'));
$has_broken_url = false;

if($is_included){

    print '<div style="padding:10px"><h3 id="records_url_msg">Check Records URL</h3><br>';
    
}else{
    define('PDIR','../../');
    set_time_limit(0);
    
    require_once (dirname(__FILE__).'/../../hsapi/System.php');
    
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
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>
    <body class="popup">
        <div class="banner">
            <h3>Check Records URL</h3>
        </div>
        <div id="page-inner">
<?php    
}

$mysqli = $system->get_mysqli();

$passed_cnt = 0;
$passed_rec_ids = array();
$broken_cnt = 0;


$query = 'SELECT rec_ID, rec_URL FROM Records WHERE(rec_URL!="") AND (rec_URL IS NOT NULL)';
    
$res = $mysqli->query($query);
if ($res){
    $result = array();
    while ($row = $res->fetch_row()){

        $rec_id = $row[0];
        $rec_url = $row[1];

        //timeout 10 seconds (default 30)
        $data = loadRemoteURLContentWithRange($rec_url, "0-1000", true, 10);

        if ($data){
            $passed_cnt++;
            $passed_rec_ids[] = $rec_id;
            if(count($passed_rec_ids)>1000){
                __updateRecords_lastverified();
            }
        }else{
            $broken_cnt++;
                //$glb_curl_error
            $upd_query = 'UPDATE Records set rec_URLLastVerified=?, rec_URLErrorMessage=? WHERE rec_ID='.$rec_id;
            $cnt = mysql__exec_param_query($mysqli, $upd_query, 
                    array('ss', date('Y-m-d H:i:s'), substr($glb_curl_error,0,255)), true);
                    
            print '<div>'.$rec_id.' : '.$rec_url.'  '.$glb_curl_error.'</div>';
        }

    }
    $res->close();

    if(count($passed_rec_ids)>0){
        __updateRecords_lastverified();
    }

    echo '<p>Processed: '.$passed_cnt.' records</p>';
    
    if($broken_cnt>0){
        $has_broken_url = true;
        print '<div style="padding-top:20px;color:red">There are <b>'.$broken_cnt
        .'</b> records with broken url. Search "_BROKEN_" for details</div>';

        //echo '<div><h3 class="error"> XXX Records have broken URL</h3></div>';        
    }else{
        echo '<div><h3 class="res-valid">OK: All records have valid URL</h3></div>';        
    }

}else{
    echo '<div><h3 class="error"> Cannot execute search query to retrieve records URL '.$mysqli->error.'</h3></div>';
}


    
if(!$is_included){    
    print '</div></body></html>';
}else{
    
    if($has_broken_url){
        echo '<script>$(".records_url").css("background-color", "#E60000");</script>';
    }else{
        echo '<script>$(".records_url").css("background-color", "#6AA84F");</script>';        
    }
    print '<br /></div>';
}

function __updateRecords_lastverified(){
    global $mysqli, $passed_rec_ids;
    $date = date('Y-m-d H:i:s');
    $query = 'UPDATE Records set rec_URLLastVerified="'.$date.'", rec_URLErrorMessage=null WHERE rec_ID in ('
            .implode(',',$passed_rec_ids) .')';    
    $mysqli->query($query);
    
    $passed_rec_ids = array(); //reset
}
?>
