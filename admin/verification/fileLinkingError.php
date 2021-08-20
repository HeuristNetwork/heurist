<?php

/**
* Lists orphaned and missed files, broken paths
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

define('OWNER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');

$mysqli = $system->get_mysqli();
?>
<html>
    <head>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
        <style type="text/css">
            h3, h3 span {
                display: inline-block;
                padding:0 0 10px 0;
            }
            Table tr td {
                line-height:2em;
            }
        </style>

    </head>


    <body class="popup">

        <div class="banner">
            <h2>Check for missing and orphaned files and incorrect paths</h2>
        </div>

        <div><br/><br/>
            These checks look for errors in uploaded file records.
        </div>
        <hr>

        <div id="page-inner">

            <?php

    $log_filename = HEURIST_HTML_DIR.'missed_files.log';
    if(file_exists($log_filename)){
        unlink($log_filename);
    }

    $counter = 0;
            
    $dbs = mysql__getdatabases4($mysqli, true);            
    foreach ($dbs as $db){
        
        //if($counter>50) break;
        $counter++;
        
        print "<h2>".$db."</h2>";
            
    $query1 = "SELECT * from ".$db.".recUploadedFiles"; // get a list of all the files
    $res1 = $mysqli->query($query1);
    if (!$res1 || $res1->num_rows == 0) {
        print "<p><b>This database does not have uploaded files</p>";
        if ($res1) $res1->close();
        continue;
    }
    else {
        print "<p>Number of files to process: ".$res1->num_rows."</p><br>";
    }
    
    $files_orphaned = array();
    $files_notfound = array();
    $files_path_to_correct = array();
    $external_count = 0;
    $local_count = 0;

    while ($res = $res1->fetch_assoc()) {

            //verify path
            $res['db_fullpath'] = null;
        
            if(@$res['ulf_FilePath'] || @$res['ulf_FileName']){

                $res['db_fullpath'] = $res['ulf_FilePath'].@$res['ulf_FileName'];
                $res['res_fullpath'] = resolveFilePath(@$res['db_fullpath']);
            }
            
            //missed link from recDetails - orphaned files       
            $query2 = "SELECT dtl_RecID from ".$db.".recDetails where dtl_UploadedFileID=".$res['ulf_ID'];
            $res2 = $mysqli->query($query2);
            $currentRecID = null;
            if ($res2) {
                if($res2->num_rows == 0) {
                  $files_orphaned[$res['ulf_ID']] = array('ulf_ID'=>$res['ulf_ID'], 
                                            'res_fullpath'=>@$res['res_fullpath'],
                                            'isfound'=>0,
                                            'ulf_ExternalFileReference'=>@$res['ulf_ExternalFileReference']);
                }else{
                    $row = $res2->fetch_row();  
                    $currentRecID = $row[0];
                }
                $res2->close();
            }
            
            if( $res['db_fullpath']!=null && @$res['res_fullpath'] ){
            
                if($currentRecID==null){
                    $files_orphaned[$res['ulf_ID']]['isfound'] = file_exists($res['res_fullpath'])?1:0;
                }else
                if ( !file_exists($res['res_fullpath']) ){
                    //file not found
                    $files_notfound[$res['ulf_ID']] = array(
                                    'ulf_ID'=>$res['ulf_ID'], 
                                    'db_fullpath'=>$res['db_fullpath'], //failed path
                                    'rec_ID'=>$currentRecID,
                                    'is_remote'=>!@$res['ulf_ExternalFileReference'] );
                    
                }else{
                    
                    $dbName = substr($db,4);
                    //HEURIST_FILESTORE_DIR
                    $_HEURIST_FILESTORE_DIR = HEURIST_FILESTORE_ROOT . $dbName . '/';

                    chdir($_HEURIST_FILESTORE_DIR);  // relatively db root

                    $fpath = realpath($res['db_fullpath']);

                    if(!$fpath || !file_exists($fpath)){
                        chdir($_HEURIST_FILESTORE_DIR.'file_uploads/');  // relatively db/file_uploads $_HEURIST_FILES_DIR
                        $fpath = realpath($res['db_fullpath']);
                    }

                    //realpath gives real path on remote file server
                    if(strpos($fpath, '/srv/HEURIST_FILESTORE/')===0){
                        $fpath = str_replace('/srv/HEURIST_FILESTORE/', HEURIST_FILESTORE_ROOT, $fpath);
                    }else
                    if(strpos($fpath, '/misc/heur-filestore/')===0){
                        $fpath = str_replace('/misc/heur-filestore/', HEURIST_FILESTORE_ROOT, $fpath);
                    }
                    
                    //check that the relative path is correct
                    $path_parts = pathinfo($fpath);
                    $dirname = $path_parts['dirname'].'/';

                    $dirname = str_replace("\0", '', $dirname);
                    $dirname = str_replace('\\', '/', $dirname);
                    if(strpos($dirname, $_HEURIST_FILESTORE_DIR)===0){
                        
                    
                    $relative_path = getRelativePath($_HEURIST_FILESTORE_DIR, $dirname);   //db root folder
                    
                    if($relative_path!=@$res['ulf_FilePath']){
                        
                        $files_path_to_correct[$res['ulf_ID']] = array('ulf_ID'=>$res['ulf_ID'], 
                                    'db_fullpath'=>$res['db_fullpath'],
                                    'res_fullpath'=>$fpath,
                                    'ulf_FilePath'=>@$res['ulf_FilePath'],
                                    'res_relative'=>$relative_path
                                    );
                    }
                    }                    
                } 
            }
            
    }//while
            
            if (count(@$files_orphaned)>0 || count(@$files_notfound)>0 || count(@$files_path_to_correct)>0){
            if (false){ 
                ?>
                <script>
                    function markAllMissed(){
                                                
                        var cbs = document.getElementsByName('fnf');
                        if (!cbs  ||  ! cbs instanceof Array)
                            return false;
                        
                        var cball = document.getElementById('fnf_all');    
                        for (var i = 0; i < cbs.length; i++) {
                            cbs[i].checked = cball.checked;
                        }
                    }
                    function repairBrokenPaths(){
                        
                        function _callback(context){
                            if(window.hWin.HEURIST4.util.isempty(context) || window.hWin.HEURIST4.util.isempty(context['result'])){
                                window.hWin.HEURIST4.msg.showMsgErr(null);
                            }else{
                                window.hWin.HEURIST4.msg.showMsgDlg(context['result']);
                            }
                        }

                        var dt2 = {"orphaned":[
                            <?php
                            
                            $pref = '';
                            foreach ($files_orphaned as $row) { //to remove
                                print $pref.'['.$row['ulf_ID'].','.$row['isfound'].']';
                                $pref = ',';
                            }
                            
                            print '],"notfound":[';
                            $pref = '';
                            //to remove from recDetails and recUplodedFiles
                            foreach ($files_notfound as $row) { 
                                print $pref.$row['ulf_ID'];
                                $pref = ',';
                            }
                            print '],"fixpath":[';
                            $pref = '';
                            
                            foreach ($files_path_to_correct as $row) {
                                print $pref.$row['ulf_ID'];
                                $pref = ',';
                            }
                        ?>]};
                        
                        var dt = {orphaned:[],fixpath:[],notfound:[]};
                        if(document.getElementById('do_orphaned') 
                                && document.getElementById('do_orphaned').checked){
                            dt['orphaned'] = dt2['orphaned'];
                        }
                        if(document.getElementById('do_fixpath')
                                && document.getElementById('do_fixpath').checked){
                            dt['fixpath'] = dt2['fixpath'];
                        }
                        var i;
                        for (i=0;i<dt2['notfound'].length;i++){
                            if(document.getElementById('fnf'+dt2['notfound'][i]).checked)
                                    dt.notfound.push(dt2['notfound'][i]);
                        }
                        
                        //var str = JSON.stringify(dt);

                        var baseurl = "<?=HEURIST_BASE_URL?>admin/verification/repairUploadedFiles.php";
                        var callback = _callback;
                        var request = {db:'<?= HEURIST_DBNAME?>', data:dt};
                        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);       
                        
                        document.getElementById('page-inner').style.display = 'none';
                    }
                </script>

                <?php
                }   
                if(count($files_orphaned)>0){
                ?>
                    <h3>Orphaned files</h3>
                    <div><?php echo count($files_orphaned);?> entries</div>
                    <div>No reference to these files found in record details. These files will be removed from db and file system</div>
                    <br>
                <?php
                    /*
                    <input type=checkbox id="do_orphaned">&nbsp;Confirm the deletion of these entries
                    <br>
                    <br>
                    */
                foreach ($files_orphaned as $row) {
                    ?>
                    <div class="msgline"><b><?php echo $row['ulf_ID'];?></b> 
                            <?php echo @$row['res_fullpath']?$row['res_fullpath']:@$row['ulf_ExternalFileReference'];?>
                    </div>
                    <?php
                }//for
                print '<hr/>';
                }
                if(count($files_notfound)>0){
                ?>
                    <h3>Files not found</h3>
                    <div><?php echo count($files_notfound);?> entries</div>
                    <div>Path specified in database is wrong and file cannot be found. Entries will be removed from database</div>
                    <br>
                <?php
                
                    $log_data = '';
/*
                    <input type=checkbox id="fnf_all"
                        onclick="markAllMissed()">
                        &nbsp;Mark/unmark all
                    <br><br>
                            <input type=checkbox name="fnf" id="fnf<?php echo $row['ulf_ID'];?>" value=<?php echo $row['ulf_ID'];?>>
*/                
                    foreach ($files_notfound as $row) {
                        //DBName, ULF ID, path, filename
                        $log_data = $log_data.$db.','.$row['ulf_ID'].','.$row['db_fullpath'].','.$row['rec_ID']."\n";
                        ?>
                        <div class="msgline">
                                <b><?php echo $row['ulf_ID'];?></b> 
                                <?php echo $row['db_fullpath'];?>
                        </div>
                        <?php
                    }
                    print '<hr/>';
                
                    file_put_contents($log_filename, $log_data, FILE_APPEND);                

                }
                if(count($files_path_to_correct)>0){
                ?>             
                    <h3>Paths to be corrected</h3>
                    <div><?php echo count($files_path_to_correct);?> entries</div>
                    <div>These relative paths in database are wrong. They will be updated in database. Files retain untouched</div>
                    <br>
                <?php
                /*
                    <input type=checkbox id="do_fixpath">&nbsp;Confirm the correctiom of these entries
                    <br>
                    <br>
                */    
                    
                foreach ($files_path_to_correct as $row) {
                    ?>
                    
                    <div class="msgline"><b><?php echo $row['ulf_ID'];?></b> 
                            <?php echo $row['res_fullpath'].' &nbsp;&nbsp;&nbsp;&nbsp; '.$row['ulf_FilePath'].' -&gt; '.$row['res_relative']; ?>
                    </div>
                    <?php
                }
                print '<hr/>';
                }
                if(false){
                ?>
                To fix the inconsistencies, please click here: <button onclick="repairBrokenPaths()">Repair selected</button><br/>
                <?php
                }
            }else{
                print "<br/><p><br/></p><h3>All uploaded file entries are valid</h3>";
            }
    }//for dbs
    
    if(file_exists($log_filename)){
        echo '<a href="'.HEURIST_HTML_URL.'missed_files.log" target="_blank">Get log file with list of missed (not found) files</a><br><br>';
    }            
            ?>
        </div>
    </body>
</html>
