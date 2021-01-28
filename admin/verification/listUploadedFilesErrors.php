<?php

/**
* listUploadedFilesErrors.php: Lists orphaned and missed files, broken paths
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

define('OWNER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_files.php');
require_once(dirname(__FILE__).'/../../import/fieldhelper/harvestLib.php');

$mysqli = $system->get_mysqli();

?>
<html>
    <head>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>
        
        <!-- CSS -->
        <?php include dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php'; ?>
        
        <style type="text/css">
            h3, h3 span {
                display: inline-block;
                padding:0 0 10px 0;
            }
            table tr td {
                line-height:2em;
            }
            .msgline{
                line-height: 3ex;
            }
            A:link, A:visited {color: #6A7C99;}
            /*
            div#in_porgress{
                background-color:#FFF;
                background-image: url(../../common/images/loading-animation-white.gif);
                background-repeat: no-repeat;
                background-position:50%;
                cursor: wait;
                width:100%;
                height:100%;
                z-index:999999;
                display:none;
            }
            */
        </style>
        <script>
            $(document).ready(function() {
               $('button').button();
            });
        </script>

    </head>


    <body class="popup" style="overflow:auto">

        <div id="in_porgress" class="coverall-div" style="display:none;"><h2>Repairing....</h2></div>    
    
        <div class="banner">
            <h2>Check for missing and orphaned files and incorrect paths</h2>
        </div>

        <div><br/><br/>
            These checks look for errors in uploaded file records.
            <br><br><hr><br><br>
            <div id="linkbar"></div>
        </div>

        <div id="page-inner" style="top:72px;">

            <?php

    $files_duplicates = array();
    $files_duplicates_all_ids = array();

    $files_orphaned = array();
    $files_unused_local = array();
    $files_unused_remote = array();

    
    $files_notfound = array(); //missed files
    $files_path_to_correct = array();
    $external_count = 0;
    $local_count = 0;
    
    $autoRepair = true;
    
    if($autoRepair){
        
    //search for duplicates
    //local
    $query2 = 'SELECT ulf_FilePath, ulf_FileName, count(*) as cnt FROM recUploadedFiles '
                .'where ulf_FileName is not null GROUP BY ulf_FilePath, ulf_FileName HAVING cnt>1'; //ulf_ID<1000 AND 
    $res2 = $mysqli->query($query2);
       
    if ($res2 && $res2->num_rows > 0) {

            $fix_dupes = 0;    
            //find id with duplicated path+filename 
            while ($res = $res2->fetch_assoc()) {
                
                $query3 = 'SELECT ulf_ID FROM recUploadedFiles '
                    .'where ulf_FilePath'.(@$res['ulf_FilePath']!=null 
                            ?'="'.$mysqli->real_escape_string($res['ulf_FilePath']).'"'
                            :' IS NULL ') 
                    .' and ulf_FileName="'.$mysqli->real_escape_string($res['ulf_FileName']).'" ORDER BY ulf_ID DESC';
                $res3 = $mysqli->query($query3);
                $dups_ids = array();
                
                while ($res4 = $res3->fetch_row()) {
                    array_push($files_duplicates_all_ids, $res4[0]);
                    array_push($dups_ids, $res4[0]);
                }
                $res3->close();
                
                if(@$res['ulf_FilePath']==null){
                    $res_fullpath = $res['ulf_FileName'];
                }else{
                    $res_fullpath = resolveFilePath( $res['ulf_FilePath'].$res['ulf_FileName'] ); //see db_files.php
                }
                $files_duplicates[$res_fullpath] = $dups_ids;
                
                //FIX duplicates at once
                $max_ulf_id = array_shift($dups_ids);
                $upd_query = 'UPDATE recDetails set dtl_UploadedFileID='.$max_ulf_id.' WHERE dtl_UploadedFileID in ('.implode(',',$dups_ids).')';
                $del_query = 'DELETE FROM recUploadedFiles where ulf_ID in ('.implode(',',$dups_ids).')';
//print $upd_query.'<br>';                
//print $del_query.'<br>';
                $mysqli->query($upd_query);
                $mysqli->query($del_query);
                $fix_dupes = $fix_dupes + count($dups_ids); 
            }
            
        if($fix_dupes){
            print '<div>Autorepair: '.$fix_dupes.' multiple registrations removed for '.count($files_duplicates).' files. Pointed all details referencing them to the one retained</div>';
        }
        
        $res2->close();
    }
    
    //search for duplicated remotes
    $query2 = 'SELECT ulf_ExternalFileReference, count(*) as cnt FROM recUploadedFiles '
                .'where ulf_ExternalFileReference is not null GROUP BY ulf_ExternalFileReference HAVING cnt>1';
    $res2 = $mysqli->query($query2);
    
    if ($res2 && $res2->num_rows > 0) {

            $fix_dupes = 0;
            $fix_url = 0;
            //find id with duplicated path+filename 
            while ($res = $res2->fetch_row()) {
                $query3 = 'SELECT ulf_ID FROM recUploadedFiles '
                    .'where ulf_ExternalFileReference="'.$mysqli->real_escape_string($res[0]).'"';
                $res3 = $mysqli->query($query3);
                $dups_ids = array();
                
                while ($res4 = $res3->fetch_row()) {
                    array_push($files_duplicates_all_ids, $res4[0]);
                    array_push($dups_ids, $res4[0]);
                }
                $res3->close();
                $files_duplicates[$res[0]] = $dups_ids;
                
                //FIX duplicates at once
                $max_ulf_id = array_shift($dups_ids);
                $upd_query = 'UPDATE recDetails set dtl_UploadedFileID='.$max_ulf_id.' WHERE dtl_UploadedFileID in ('.implode(',',$dups_ids).')';
                $del_query = 'DELETE FROM recUploadedFiles where ulf_ID in ('.implode(',',$dups_ids).')';
                $mysqli->query($upd_query);
                $mysqli->query($del_query);
                $fix_dupes = $fix_dupes + count($dups_ids); 
                $fix_url++;
            }
            
            if($fix_dupes){
                print '<div>System info: cleared '.$fix_dupes.' duplicated registration for '.$fix_url.' URL</div>';
            }
            $res2->close();
    }
    
  
    
    //search for duplicated files (identical files in different folders)
    $query2 = 'SELECT ulf_OrigFileName, count(*) as cnt FROM recUploadedFiles ' 
.' where ulf_OrigFileName is not null and ulf_OrigFileName<>"_remote" GROUP BY ulf_OrigFileName HAVING cnt>1';
    $res2 = $mysqli->query($query2);
    
    if ($res2 && $res2->num_rows > 0) {
    

            $cnt_dupes = 0;
            $cnt_unique = 0;
            //find id with duplicated path+filename 
            while ($res = $res2->fetch_row()) {
                $query3 = 'SELECT ulf_ID, ulf_FilePath, ulf_FileName  FROM recUploadedFiles '
                    .' where ulf_OrigFileName="'.$mysqli->real_escape_string($res[0]).'"'
                    .' ORDER BY ulf_ID DESC';
                $res3 = $mysqli->query($query3);
        
                $dups_files = array(); //id=>path,size,md,array(dup_ids)
                
                while ($res4 = $res3->fetch_assoc()) {
                    
                    //compare files 
                    if(@$res4['ulf_FilePath']==null){
                        $res_fullpath = $res4['ulf_FileName'];
                    }else{
                        $res_fullpath = resolveFilePath( $res4['ulf_FilePath'].$res4['ulf_FileName'] ); //see db_files.php
                    }
                   
                    
                    $f_size = filesize($res_fullpath);
                    $f_md5 = md5_file($res_fullpath);
                    $is_unique = true;
                    foreach ($dups_files as $id=>$file_a){

                        if ($file_a['size'] == $f_size && $file_a['md5'] == $f_md5){
                            //files are the same    
                            $is_unique = false;
                            $dups_files[$id]['dupes'][ $res4['ulf_ID'] ] = $res_fullpath;
                            //array_push($file_a['dupes'], array($res4['ulf_ID'] => $res_fullpath));
                            break;
                        }
                    }
                    if($is_unique){
                        $dups_files[$res4['ulf_ID']] = array('path'=>$res_fullpath,
                                                    'md5'=>$f_md5,
                                                    'size'=>$f_size,
                                                    'dupes'=>array());
                    }
                }//while
                $res3->close();
  
                //FIX duplicates at once
                foreach ($dups_files as $ulf_ID=>$file_a){
                    if(count($file_a['dupes'])>0){
                        
                        $dup_ids = implode(',',array_keys($file_a['dupes']));
                        $upd_query = 'UPDATE recDetails set dtl_UploadedFileID='
                                .$ulf_ID.' WHERE dtl_UploadedFileID in ('.$dup_ids.')';
                        $del_query = 'DELETE FROM recUploadedFiles where ulf_ID in ('.$dup_ids.')';
        //print $upd_query.'<br>';                
        //print $del_query.'<br>';
                        $mysqli->query($upd_query);
                        $mysqli->query($del_query);
                        $cnt_dupes = $cnt_dupes + count($file_a['dupes']); 
                        $cnt_unique++;
                        
                        /* report
                        foreach($file_a['dupes'] as $id=>$path){
                            print '<div>'.$id.' '.$path.'</div>';
                        }
                        print '<div style="padding:0 0 10px 60px">removed in favour of '.$ulf_ID.' '.$file_a['path'].'</div>';
                        */
                    }
                }//foreach
                
            }//while
            
        if($cnt_unique>0){
            print '<div>Autorepair: '.$cnt_dupes.' registration for identical files are removed in favour.'
                        .$cnt_unique.' unique ones. Pointed all details referencing them to the one retained</div>';
        }
        
        $res2->close();
    }
       
    }//autoRepair
   

    $query1 = 'SELECT ulf_ID, ulf_ExternalFileReference, ulf_FilePath, ulf_FileName from recUploadedFiles'; // where ulf_ID=5188 
    $res1 = $mysqli->query($query1);
    if (!$res1 || $res1->num_rows == 0) {
        die ("<p><b>This database does not have uploaded files</b></p>");
    }
    else {
        print "<p><br>Number of files processed: ".$res1->num_rows."<br></p>";
    }
    
    //
    //
    //
    while ( $res = $res1->fetch_assoc() ) {

            //if(in_array($res['ulf_ID'], $files_duplicates_all_ids)) continue;
        
            //verify path
            $res['db_fullpath'] = null;
        
            if(@$res['ulf_FilePath'] || @$res['ulf_FileName']){

                $res['db_fullpath'] = $res['ulf_FilePath'].@$res['ulf_FileName'];
                $res['res_fullpath'] = resolveFilePath(@$res['db_fullpath']);
            }
            
            //missed link from recDetails - orphaned files       
            $query2 = "SELECT dtl_RecID from recDetails where dtl_UploadedFileID=".$res['ulf_ID'];
            $res2 = $mysqli->query($query2);
            $currentRecID = null;
            if ($res2) {
                if($res2->num_rows == 0) {
                    
                    if(@$res['ulf_ExternalFileReference']!=null){
                        $files_unused_remote[$res['ulf_ID']] = array('ulf_ID'=>$res['ulf_ID'],
                                            'ulf_ExternalFileReference'=>@$res['ulf_ExternalFileReference']);
                    }else{
                        $files_unused_local[$res['ulf_ID']] = array('ulf_ID'=>$res['ulf_ID'], 
                                            'res_fullpath'=>@$res['res_fullpath'],
                                            'isfound'=>file_exists($res['res_fullpath'])?1:0);    
                    }
                    
                    $files_orphaned[$res['ulf_ID']] = array('ulf_ID'=>$res['ulf_ID'], 
                                            'res_fullpath'=>@$res['res_fullpath'],
                                            'isfound'=>file_exists(@$res['res_fullpath'])?1:0,
                                            'ulf_ExternalFileReference'=>@$res['ulf_ExternalFileReference']);
                }else{
                    $row = $res2->fetch_row();  
                    $currentRecID = $row[0];
                }
                $res2->close();
            }
            
            if( $res['db_fullpath']!=null && @$res['res_fullpath'] ){
            
                $is_local = (strpos($res['db_fullpath'],'http://')===false && strpos($res['db_fullpath'],'https://')===false);
      
                if($currentRecID==null){
                    
                }else
                if ( $is_local && !file_exists($res['res_fullpath']) ){
                    //file not found
                    $files_notfound[$res['ulf_ID']] = array(
                                    'ulf_ID'=>$res['ulf_ID'], 
                                    'db_fullpath'=>$res['db_fullpath'], //failed path
                                    'rec_ID'=>$currentRecID,
                                    'is_remote'=>!@$res['ulf_ExternalFileReference'] );
                    
                }else if($is_local) {

                    chdir(HEURIST_FILESTORE_DIR);  // relatively db root

                    $fpath = realpath($res['db_fullpath']);

                    if(!$fpath || !file_exists($fpath)){
                        chdir(HEURIST_FILES_DIR);  // relatively file_uploads
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
                    if(!@$path_parts['dirname']){
                       error_log($fpath.'  '.$res['db_fullpath']);
                       continue;
                    }else{
                        $dirname = $path_parts['dirname'].'/';
                        $filename = $path_parts['basename'];
                    }

                    $dirname = str_replace("\0", '', $dirname);
                    $dirname = str_replace('\\', '/', $dirname);
                    
                    if(strpos($dirname, HEURIST_FILESTORE_DIR)===0){
                        
                    
                        $relative_path = getRelativePath(HEURIST_FILESTORE_DIR, $dirname);   //db root folder
                    
                        if($relative_path!=@$res['ulf_FilePath']){
                            
                            $files_path_to_correct[$res['ulf_ID']] = array('ulf_ID'=>$res['ulf_ID'], 
                                        'db_fullpath'=>$res['db_fullpath'],
                                        'res_fullpath'=>$fpath,
                                        'ulf_FilePath'=>@$res['ulf_FilePath'],
                                        'res_relative'=>$relative_path,
                                        'filename'=>$filename
                                        );
                        }
                    }                    
                }else{
                            
                            $files_path_to_correct[$res['ulf_ID']] = array('ulf_ID'=>$res['ulf_ID'], 
                                        'clear_remote'=>$res['db_fullpath']
                                        );
                            
                } 
            }
            
    }//while
    
    //AUTO FIX PATH at once
    foreach ($files_path_to_correct as $row){
      
        $ulf_ID = $row['ulf_ID'];
        if(@$row['clear_remote']){ //remove url from ulf_FilePath
                $query = 'update recUploadedFiles set ulf_ExternalFileReference="'
                                .$mysqli->real_escape_string($row['clear_remote'])
                                .'", ulf_FilePath=NULL, ulf_FileName=NULL where ulf_ID = '.$ulf_ID;
        }else{
                    $query = 'update recUploadedFiles set ulf_FilePath="'
                                    .$mysqli->real_escape_string($row['res_relative'])
                                    .'", ulf_FileName="'
                                    .$mysqli->real_escape_string($row['filename']).'" where ulf_ID = '.$ulf_ID;
        }
       $mysqli->query($query);            
//DEBUG       print '<div>'.$ulf_ID.'  rem '.@$row['clear_remote'].'   path='.$row['res_relative'].'  file='.$row['filename'].'</div>';
    }
    if(count($files_path_to_correct)>0){
            print '<div>Autorepair: corrected '.count($files_path_to_correct).' paths</div>';
            $files_path_to_correct = array();
    }
    
      
            
    //check for non-registered files in mediafolders
    // $reg_info - global array to be filled in doHarvest
    $reg_info = array('reg'=>array(), 'nonreg'=>array());
    $dirs_and_exts = getMediaFolders( $mysqli );
    doHarvest($dirs_and_exts, false, 1);
    
    $files_notreg = $reg_info['nonreg'];
    
    //count($files_duplicates)+
    $is_found = (count($files_unused_remote)+count($files_unused_local)+count($files_notfound)+count($files_notreg)>0);  
      
            if ($is_found) {
                ?>
                <script>
                    
                    //NOT USED
                    function repairBrokenPaths(){
                        
                        function _callbackRepair(context){
                            
                            $('#in_porgress').hide();
                            
                            if(top.HEURIST.util.isnull(context) || top.HEURIST.util.isnull(context['result'])){
                                top.HEURIST.util.showError(null);
                            }else{
                                //top.HEURIST.util.showMessage(context['result']);
                                var url = top.HEURIST.baseURL + 'admin/verification/listDatabaseErrorsInit.php?type=files&db='+top.HEURIST.database.name;
                                console.log(window.parent.addDataMenu);
                                if(window.parent.parent.addDataMenu)
                                    window.parent.parent.addDataMenu.doAction('menulink-verify-files');
                                //$(top.document).find('#frame_container2').attr('src', url); 
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
                        var callback = _callbackRepair;
                        var params = "db=<?= HEURIST_DBNAME?>&data=" + encodeURIComponent(str);
                        
                        $('#in_porgress').show();
                        top.HEURIST.util.getJsonData(baseurl, callback, params);
                        
                        document.getElementById('page-inner').style.display = 'none';
                    }
                    
                    //
                    //
                    //
                    function removeUnlinkedFiles(){
                        
                        function _callback(context){
                            document.getElementById('page-inner').style.display = 'block';
                            
                            if(top.HEURIST.util.isnull(context) || context['status']!='ok'){
                                top.HEURIST.util.showError(context || context['message']);
                            }else{
                                
                                var ft = $('input.file_to_clear:checked');
                                var i, j, cnt=0, fdeleted = context['data'];
                                
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
                                top.HEURIST.util.showMessage(cnt+' non-registered/unlinked files have been removed from media folders');
                            }
                        }

                        
                        var res = [];
                        $.each($('input.file_to_clear:checked'), function(idx, item){
                            var filename = $(item).parent().text();
                            res.push(filename);
                        });
                        
                        if(res.length==0){
                            alert('Mark at least one file');
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
                    
                    //
                    //
                    //
                    function doRepairAction(action_name){
                        
                        function _callback(context){
                            document.getElementById('page-inner').style.display = 'block'; //restore visibility
                            
                            if(top.HEURIST.util.isnull(context) || context['status']!='ok'){
                                top.HEURIST.util.showError(context || context['message']);
                            }else{
                                
                                var ft = $('input.'+action_name+':checked');
                                var i, j, cnt=0, fdeleted = context['data'];
                                
                                if($('input.'+action_name).length==fdeleted.length){
                                    cnt = fdeleted.length;
                                    //all removed - remove entire div
                                    $('#'+action_name).remove();
                                }else{
                                
                                    for (i=0; i<fdeleted.length; i++){
                                        for (j=0; j<ft.length; j++){
                                            if($(ft[j]).attr('data-id')==fdeleted[i]){
                                                //remove div 
                                                $(ft[j]).parents('.msgline').remove();
                                                cnt++;
                                                break;
                                            }
                                        }
                                    }
                                }
                                top.HEURIST.util.showMessage(cnt+' entries have been fixed');
                            }
                        }

                        
                        var res = [];
                        $.each($('input.'+action_name+':checked'), function(idx, item){
                            var ulf_id = $(item).attr('data-id');//parent().text();
                            res.push(ulf_id);
                        });
                        
                        if(res.length==0){
                            alert('Mark at least one file');
                            return;
                        }else if(res.length>2000){
                            alert('You can only process 2000 files at a time due to server side limitations. '+
                            'Please repeat the operation for this number of files as many times as needed.');
                            return;
                        }
                        
                        var dt = {}; dt[action_name] = res;
                        var str = JSON.stringify(dt);
                       

                        var baseurl = top.HEURIST.baseURL + "admin/verification/repairUploadedFiles.php";
                        var callback = _callback;
                        var params = "db=<?= HEURIST_DBNAME?>&data=" + encodeURIComponent(str);
                        top.HEURIST.util.getJsonData(baseurl, callback, params);
                        
                        document.getElementById('page-inner').style.display = 'none'; //hide all
                        
                    }//doRepairAction
                <?php
                    $smsg='';
                    
                if($is_found){

                    $smsg = 'Go to: ';
                /*if(count($files_duplicates)>0){
                    print '\'<a href="#duplicates" style="white-space: nowrap;padding-right:10px">Duplicated entries</a>\'+';
                }
                if(count($files_orphaned)>0){
                    print '\'<a href="#orphaned" style="white-space: nowrap;padding-right:10px">Orphaned files</a>\'+';
                }
                */
                if(count($files_unused_local)>0){
                    $smsg = $smsg.'<a href="#unused_local" style="white-space: nowrap;padding-right:20px">Unused local files</a>';
                }
                if(count($files_unused_remote)>0){
                    $smsg = $smsg.'<a href="#unused_remote" style="white-space: nowrap;padding-right:20px">Unused remote files</a>';
                }
                if(count($files_notfound)>0){
                    $smsg = $smsg.'<a href="#files_notfound" style="white-space: nowrap;padding-right:20px">Files not found</a>';
                }
                if(count($files_notreg)>0){
                    $smsg = $smsg.'<a href="#files_notreg" style="white-space: nowrap;padding-right:20px">Non-registered files</a>';
                }
                }
                
                print "document.getElementById('linkbar').innerHTML='".$smsg."'";
                ?>
                    
                </script>
                
                <?php
                if(count($files_unused_local)>0){
                ?>
                <div id="unused_file_local" style="padding-top:20px">
                    <a name="unused_local"></a>    
                    <h3>Unused local files</h3>
                    <div style="padding-bottom:10px;font-weight:bold"><?php echo count($files_unused_local);?> entries</div>
                    <div>These files are not referenced by any record in the database. 
                    Select all or some entries and click the button 
                    <button onclick="doRepairAction('unused_file_local')">Remove selected unused local files</button>
                    to remove registrations from the database. Files remain untouched</div>
                    <br>
                    <label><input type=checkbox
                        onchange="{$('.unused_file_local').prop('checked', $(event.target).is(':checked'));}">&nbsp;Select/unselect all</label>
                    <br>
                    <br>
                <?php
                foreach ($files_unused_local as $row) {
                    print '<div class="msgline"><label><input type=checkbox class="unused_file_local" data-id="'.$row['ulf_ID'].'">&nbsp;'
                            .'<b>'.$row['ulf_ID'].'</b> '.$row['res_fullpath'].( $row['isfound']?'':' ( file not found )' ).'</label></div>';
                                    //@$row['ulf_ExternalFileReference'];
                }//for
                if(count($files_unused_local)>10){
                    print '<div><br><button onclick="doRepairAction(\'unused_file_local\')">Remove selected unused local files</button></div>';
                }
                print '<br><br><hr/></div>';
                }
                //------------------------------------------
                if(count($files_unused_remote)>0){
                ?>
                <div id="unused_file_remote" style="padding-top:20px">
                    <a name="unused_remote"></a>    
                    <h3>Unused remote files</h3>
                    <div style="padding-bottom:10px;font-weight:bold"><?php echo count($files_unused_remote);?> entries</div>
                    <div>These URLs are not referenced by any record in the database. 
                    Select all or some entries and click the button 
                    <button onclick="doRepairAction('unused_file_remote')">Remove selected unused URLs</button>
                    to remove registrations from the database.</div>
                    
                    <br>
                    <label><input type=checkbox
                        onchange="{$('.unused_file_remote').prop('checked', $(event.target).is(':checked'));}">&nbsp;Select/unselect all</label>
                    <br>
                    <br>
                <?php
                foreach ($files_unused_remote as $row) {
                    print '<div class="msgline"><label><input type=checkbox class="unused_file_remote" data-id="'.$row['ulf_ID'].'">&nbsp;'
                            .'<b>'.$row['ulf_ID'].'</b> '.$row['ulf_ExternalFileReference'].'</label></div>';
                }//for
                if(count($files_unused_remote)>10){
                    print '<div><br><button onclick="doRepairAction(\'unused_file_remote\')">Remove selected unused URLs</button></div>';
                }
                print '<br><br><hr/></div>';
                }//if
                
                //------------------------------------------
                if(count($files_notfound)>0){
                ?>
                <div id="files_notfound" style="padding-top:20px">
                    <a name="files_notfound"></a>    
                    <h3>Missing registered files </h3>
                    <div style="padding-bottom:10px;font-weight:bold"><?php echo count($files_notfound);?> entries</div>
                    <div>Path specified in database is wrong and file cannot be found.
                    Select all or some entries and click the button 
                    <button onclick="doRepairAction('files_notfound')">Remove entries for missing files</button>
                    to remove registrations from the database.</div>
                    
                    <br>
                    <label><input type=checkbox
                        onchange="{$('.files_notfound').prop('checked', $(event.target).is(':checked'));}">&nbsp;Select/unselect all</label>
                    <br>
                    <br>
                <?php
                foreach ($files_notfound as $row) {
                    print '<div class="msgline"><label><input type=checkbox class="files_notfound" data-id="'.$row['ulf_ID'].'">&nbsp;'
                            .'<b>'.$row['ulf_ID'].'</b> '.$row['db_fullpath'].'</label></div>';
                }//for
                if(count($files_notfound)>10){
                    print '<div><br><button onclick="doRepairAction(\'files_notfound\')">Remove entries for missing files</button></div>';
                }
                print '<br><br><hr/></div>';
                }//if                
                
                //------------------------------------------
                if(count($files_notreg)>0){
                ?>
                <div id="files_notreg" style="padding-top:20px">
                    <a name="files_notreg"></a>    
                    <h3>Non-registered files</h3>
                    <div style="padding-bottom:10px;font-weight:bold"><?php echo count($files_notreg);?> entries</div>
                    <div>
                    Use Import > Index media to add these to the database as multimedia records. Or
                    select all or some entries and click the button 
                    <button onclick="doRepairAction('files_notreg')">Remove non-registered files</button>
                    to delete files from system.</div>
                    
                    <br>
                    <label><input type=checkbox
                        onchange="{$('.files_notreg').prop('checked', $(event.target).is(':checked'));}">&nbsp;Select/unselect all</label>
                    <br>
                    <br>
                <?php
                foreach ($files_notreg as $row) {
                    print '<div class="msgline"><label><input type=checkbox class="files_notreg" data-id="'.$row.'">&nbsp;'
                            .$row.'</label></div>';
                }//for
                if(count($files_notreg)>10){
                    print '<div><br><button onclick="doRepairAction(\'files_notreg\')">Remove non-registered files</button></div>';
                }
                print '<br><br><hr/></div>';
                }//if                
                
                //------------------------------------------
            }else{
                print "<br><br><p><h3>All uploaded file entries are valid</h3></p>";
            }
            ?>            
    
            
        </div>
<script>
/*
    var parent = $(window.parent.document);
    parent.find('#verification_output').css({width:'100%',height:'100%'}).show(); 
    parent.find('#in_porgress').hide();
*/    
</script>        
    </body>
</html>
