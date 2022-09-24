<?php

/**
* listUploadedFilesMissed.php - light weight version of listUploadedFilesErrors.php: 
* Lists missed files that are listed in recUploadedFiles
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

$is_included = (defined('PDIR'));
$has_broken_url = false;

if($is_included){

    print '<div style="padding:10px"><h3 id="recordfiles_missed_msg">Check missed registered files</h3><br>';
    
}else{
    define('PDIR','../../');
    
    require_once (dirname(__FILE__).'/../../hsapi/System.php');
    
    $system = new System();
    if( ! $system->init(@$_REQUEST['db']) ){
        //get error and response
        print $system->getError()['message'];
        return;
    }
    
    if( @$_REQUEST['all']==1 ){
        if($system->verifyActionPassword(@$_REQUEST['pwd'], $passwordForServerFunctions)){
        ?>
        
        <form action="listUploadedFilesMissed.php" method="POST">
            <div style="padding:20px 0px">
                Only an administrator (server manager) can carry out this action.<br />
                This action requires a special system administrator password (not a normal login password)
            </div>
        
            <span style="display: inline-block;padding: 10px 0px;">Enter password:&nbsp;</span>
            <input type="password" name="pwd" autocomplete="off" />
            <input type="hidden" name="db" value="<?php  echo htmlspecialchars($_REQUEST['db']);?>"/>
            <input type="hidden" name="all" value="1"/>

            <input type="submit" value="OK" />
        </form>

        <?php
        exit();
        }
    }else if(!$system->is_admin()){ //  $system->is_dbowner()
        print '<span>You must be logged in as Database Administrator to perform this operation</span>';
        exit();
    }
?>    
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>
    <body class="popup">
        <div class="banner">
            <h3>Missed registered files</h3>
        </div>
        <div id="page-inner">
<?php    
}

$mysqli = $system->get_mysqli();

$is_all_databases = false;
if(@$_REQUEST['all']==1){
    //scan all databases
    $is_all_databases = true;
    $databases = mysql__getdatabases4($mysqli, false);   
}else{
    $databases = array($_REQUEST['db']);
}

$total_count = 0;
$missed = array();
$missed_folders = array();

foreach ($databases as $idx=>$db_name){

    //mysql__usedatabase($mysqli, $db_name);

    $query2 = 'SELECT ulf_FilePath, ulf_FileName FROM hdb_'.$mysqli->real_escape_string($db_name).'.recUploadedFiles '
                    .'WHERE ulf_FileName is not null ORDER BY ulf_FilePath';
                    
    $res2 = $mysqli->query($query2);
    
    if($res2){

        while ($row = $res2->fetch_assoc()) {

            if(@$row['ulf_FilePath'] || @$row['ulf_FileName']){

                $full_path = (@$row['ulf_FilePath']==null?'':$row['ulf_FilePath']).@$row['ulf_FileName'];
                $res_fullpath = resolveFilePath($full_path, $db_name);
                if(!file_exists($res_fullpath)){
                    
                    $missed[] = array($db_name, @$row['ulf_FilePath'], $row['ulf_FileName']);    
                    
                    $key = $db_name.','.@$row['ulf_FilePath'];
                    if(!@$missed_folders[$key]){
                        $missed_folders[$key] = 0;
                    }
                    $missed_folders[$key]++;                  
                }
                $total_count++;
            }
            
        }//while
        
        $res2->close();
        
    }else{
        print htmlspecialchars($db_name).' Cannot execute query. Error: '.$mysqli->error;
    }

}//for databases

if(count($missed)==0){        
    echo '<div><h3 class="res-valid">OK: All records have valid URL</h3></div>';        
}else{
    
    print 'Summary:<br>';
    foreach($missed_folders as $key=>$cnt){
        print $key.",".$cnt.'<br>';
    }

    print '<br><br>Detail:<br>';
    print 'Database name,Directory name,File name<br>';
    foreach($missed as $data){
        print implode(',',$data).'<br>';
    }
    
    print '<div style="padding-top:20px;color:red">There are <b>'.count($missed).' of '.$total_count
         .'</b> registered files are missed</div>';
    //print '<div><a href="#">Download report as CSV</a></div>';
}

if(!$is_included){    
    print '</div></body></html>';
}else{
    
    if($has_broken_url){
        echo '<script>$(".recordfiles_missed").css("background-color", "#E60000");</script>';
    }else{
        echo '<script>$(".recordfiles_missed").css("background-color", "#6AA84F");</script>';        
    }
    print '<br /></div>';
}
?>
