<?php

/**
* listUploadedFilesErrors.php: Lists orphaned and missed files, broken paths
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../records/files/uploadFile.php');
require_once(dirname(__FILE__).'/../../import/fieldhelper/harvestLib.php');

if (isForAdminOnly()) exit();
?>
<html>
    <head>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
        <script type="text/javascript" src="../../ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
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
            <h2>Check for missed and orphaned files and wrong paths</h2>
        </div>

        <div><br/><br/>
            These checks look for errors in record uploaded files.
        </div>
        <hr>

        <div id="page-inner">

            <?php
            
    $query1 = "SELECT * from recUploadedFiles"; // get a list of all the files
    $res1 = mysql_query($query1);
    if (!$res1 || mysql_num_rows($res1) == 0) {
        die ("<p><b>This database does not have uploaded files");
    }
    else {
        print "<p>Number of files to process: ".mysql_num_rows($res1)."<br>";
    }
    
    $files_orphaned = array();
    $files_notfound = array();
    $files_path_to_correct = array();
    $external_count = 0;
    $local_count = 0;

    while ($res = mysql_fetch_assoc($res1)) {

            //verify path
            $res['db_fullpath'] = null;
        
            if(@$res['ulf_FilePath'] || @$res['ulf_FileName']){

                $res['db_fullpath'] = $res['ulf_FilePath'].@$res['ulf_FileName'];
                $res['res_fullpath'] = resolveFilePath(@$res['db_fullpath']);
            }
            
            //missed link from recDetails - orphaned files       
            $query2 = "SELECT dtl_RecID from recDetails where dtl_UploadedFileID=".$res['ulf_ID'];
            $res2 = mysql_query($query2);
            $currentRecID = null;
            if ($res2) {
                if(mysql_num_rows($res2) == 0) {
                  $files_orphaned[$res['ulf_ID']] = array('ulf_ID'=>$res['ulf_ID'], 
                                            'res_fullpath'=>@$res['res_fullpath'],
                                            'isfound'=>0,
                                            'ulf_ExternalFileReference'=>@$res['ulf_ExternalFileReference']);
                }else{
                    $row = mysql_fetch_row($res2);  
                    $currentRecID = $row[0];
                }
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

                    chdir(HEURIST_FILESTORE_DIR);  // relatively db root

                    $fpath = realpath($res['db_fullpath']);

                    if(!$fpath || !file_exists($fpath)){
                        chdir(HEURIST_FILES_DIR);  // relatively db root
                        $fpath = realpath($res['db_fullpath']);
                    }

                    //realpath gives real path on remote file server
                    if(strpos($fpath, '/srv/HEURIST_FILESTORE/')===0){
                        $fpath = str_replace('/srv/HEURIST_FILESTORE/', HEURIST_UPLOAD_ROOT, $fpath);
                    }else
                    if(strpos($fpath, '/misc/heur-filestore/')===0){
                        $fpath = str_replace('/misc/heur-filestore/', HEURIST_UPLOAD_ROOT, $fpath);
                    }
                    
                    //check that the relative path is correct
                    $path_parts = pathinfo($fpath);
                    $dirname = $path_parts['dirname'].'/';

                    $dirname = str_replace("\0", '', $dirname);
                    $dirname = str_replace('\\', '/', $dirname);
                    if(strpos($dirname, HEURIST_FILESTORE_DIR)===0){
                        
                    
                    $relative_path = getRelativePath(HEURIST_FILESTORE_DIR, $dirname);   //db root folder
                    
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
                            if(top.HEURIST.util.isnull(context) || top.HEURIST.util.isnull(context['result'])){
                                top.HEURIST.util.showError(null);
                            }else{
                                top.HEURIST.util.showMessage(context['result']);
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
                        
                        var str = JSON.stringify(dt);

                        var baseurl = top.HEURIST.baseURL + "admin/verification/repairUploadedFiles.php";
                        var callback = _callback;
                        var params = "db=<?= HEURIST_DBNAME?>&data=" + encodeURIComponent(str);
                        top.HEURIST.util.getJsonData(baseurl, callback, params);
                        
                        document.getElementById('page-inner').style.display = 'none';
                    }
                    
                    //
                    //
                    //
                    function removeUnlinkedFiles(){
                        
                        function _callback(context){
                            document.getElementById('page-inner').style.display = 'block';
                            
                            if(top.HEURIST.util.isnull(context) || top.HEURIST.util.isnull(context['result'])){
                                top.HEURIST.util.showError(null);
                            }else{
                                
                                var ft = $('input.file_to_clear:checked');
                                var i, j, cnt=0, fdeleted = context['result'];
                                
                                if($('input.file_to_clear').length==fdeleted.length){
                                    cnt = fdeleted.length;
                                    //all removed
                                    $('#nonreg').remove();
                                }else{
                                
                                    for (i=0; i<fdeleted.length; i++){
                                        for (j=0; j<ft.length; j++){
                                            if($(ft[j]).parent().text()==fdeleted[i]){
                                                //remove div 
                                                $(ft[j]).parents('.msgline').remove();
                                                cnt++;
                                                break;
                                            }
                                        }
                                    }
                                }
                                top.HEURIST.util.showMessage(cnt+' nonregistered/unlinked files have been removed from media folders');
                            }
                        }

                        
                        var res = [];
                        $.each($('input.file_to_clear:checked'), function(idx, item){
                            var filename = $(item).parent().text();
                            res.push(filename);
                        });
                        
                        if(res.length==0){
                            alert('Mark at least on file to delete');
                            return;
                        }
                        
                        var dt = {"unlinked":res};
                        var str = JSON.stringify(dt);
                       

                        var baseurl = top.HEURIST.baseURL + "admin/verification/repairUploadedFiles.php";
                        var callback = _callback;
                        var params = "db=<?= HEURIST_DBNAME?>&data=" + encodeURIComponent(str);
                        top.HEURIST.util.getJsonData(baseurl, callback, params);
                        
                        document.getElementById('page-inner').style.display = 'none';

                    }
                    
                </script>

                <?php
                if(count($files_orphaned)>0){
                ?>
                    <h3>Orphaned files</h3>
                    <div><?php echo count($files_orphaned);?> entries</div>
                    <div>No reference to these files found in record details. These files will be removed from db and file system</div>
                    <br>
                    <input type=checkbox id="do_orphaned">&nbsp;Confirm the deletion of these entries
                    <br>
                    <br>
                <?php
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
                    <input type=checkbox id="fnf_all"
                        onclick="markAllMissed()">
                        &nbsp;Mark/unmark all
                    <br><br>
                    
                <?php
                
                foreach ($files_notfound as $row) {
                    ?>
                    <div class="msgline">
                            <input type=checkbox name="fnf" id="fnf<?php echo $row['ulf_ID'];?>"
                                     value=<?php echo $row['ulf_ID'];?>>
                            <b><?php echo $row['ulf_ID'];?></b> 
                            <?php echo $row['db_fullpath'];?>
                    </div>
                    <?php
                }
                print '<hr/>';
                }
                if(count($files_path_to_correct)>0){
                ?>             
                    <h3>Paths to be corrected</h3>
                    <div><?php echo count($files_path_to_correct);?> entries</div>
                    <div>These relative paths in database are wrong. They will be updated in database. Files retain untouched</div>
                    <br>
                    <input type=checkbox id="do_fixpath">&nbsp;Confirm the correctiom of these entries
                    <br>
                    <br>
                <?php
                
                foreach ($files_path_to_correct as $row) {
                    ?>
                    
                    <div class="msgline"><b><?php echo $row['ulf_ID'];?></b> 
                            <?php echo $row['res_fullpath'].' &nbsp;&nbsp;&nbsp;&nbsp; '.$row['ulf_FilePath'].' -&gt; '.$row['res_relative']; ?>
                    </div>
                    <?php
                }
                print '<hr/>';
                }
                ?>
                <br>To fix the inconsistencies, please click here: <button onclick="repairBrokenPaths()">Repair selected</button><br/>
                <?php
            }else{
                print "<br><br><p><h3>All uploaded file entries are valid</h3></p>";
            }
            
            
    //check for non-revistered files in mediafolders
    $reg_info = array('reg'=>array(), 'nonreg'=>array());
    $dirs_and_exts = getMediaFolders();

    doHarvest($dirs_and_exts, false, 1);
    
    //$reg_info = getRegInfoResult();
   
//print print_r($reg_info,true);
               
    if(count($reg_info['nonreg'])>0){
        ?>
            <div id="nonreg">
                    <hr>
                    <h3>Nonregistered files</h3>
                    <div><?php echo count($reg_info['nonreg']);?> entries</div>
                    <br>
                    <input type=checkbox id="do_clean_nonreg" 
                        onchange="{$('.file_to_clear').attr('checked', $(event.target).is(':checked'));}">&nbsp;Select/unselect all
                    <br>
                    <br>
                <?php
                foreach ($reg_info['nonreg'] as $row) {
                    print '<div class="msgline"><label><input type=checkbox class="file_to_clear">'.$row.'</label></div>';
                }//for

        print '<br><p>To remove nonregistered files in media folders, please click here: <button onclick="removeUnlinkedFiles()">Delete selected</button></p></div>'; 
    }else{
        print '<br><br><br><p><h3>There are nonregistered files in media folders: '.implode(';', $dirs_and_exts['dirs']).' </h3></p>';
    }
            
            
            ?>
        </div>
    </body>
</html>
