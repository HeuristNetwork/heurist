<?php
    /*
    * Copyright (C) 2005-2016 University of Sydney
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
    * Recreate thumbnails for given MimeExt (uses ImageMagic)
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2016 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
    require_once(dirname(__FILE__).'/../../records/files/uploadFile.php');

    if (isForAdminOnly()) exit();
    
    $query1 = 'SELECT ulf_ID, ulf_ExternalFileReference, ulf_FilePath, ulf_FileName, ulf_ObfuscatedFileID'
    .' from recUploadedFiles where ulf_ExternalFileReference is null AND ulf_MimeExt="pdf" limit 4000 offset 10'; // where ulf_ID=5188 
    $res1 = mysql_query($query1);
    if (!$res1 || mysql_num_rows($res1) == 0) {
        die ("<p><b>This database does not have uploaded files");
    }
    else {
        print "<p>Number of files to process: ".mysql_num_rows($res1)."<br>";
    }
    
    $k = 0;
    $m = 0;
    //
    //
    //
    while ($res = mysql_fetch_assoc($res1)) {

            $k++;    
        
            $res['db_fullpath'] = null;
        
            if(@$res['ulf_FilePath'] || @$res['ulf_FileName']){

                $res['db_fullpath'] = $res['ulf_FilePath'].@$res['ulf_FileName'];
                $res['res_fullpath'] = resolveFilePath(@$res['db_fullpath']);
            }
            if($res['db_fullpath'] && file_exists($res['res_fullpath'])){

//http://heurist.sydney.edu.au/h4-ao/admin/verification/thumbUploadedFiles.php?db=ExpertNation                
                if(strpos($res['res_fullpath'],'pdfs_from_BOR')!==false) continue;
                
                print $k.'   '.$res['ulf_ID'].'  '.$res['res_fullpath'].' ';         

                $thumbnail_file = HEURIST_THUMB_DIR."ulf_".$res['ulf_ObfuscatedFileID'].".png";
                //$thumbnail_file = '/misc/heur-filestore/artem_19/file_uploads/ulf_'.$res['ulf_ObfuscatedFileID'].".png";

                if(file_exists($thumbnail_file)){
                    unlink($thumbnail_file);
                }
                
                $cmdline = 'convert -thumbnail x300 -background white -alpha off "'.$res['res_fullpath'].'"[0] '
                .$thumbnail_file;
     
//$cmdline = 'convert -thumbnail x300 -background white -alpha off "/misc/heur-filestore/artem_19/file_uploads/ulf_7_19200424 SMH p. 9.3.pdf"[0] /misc/heur-filestore/artem_19/filethumbs/ulf_bdeb8d5518ba35828bc9ef522f5e8da402838ff8.png';
//$cmdline = 'convert -thumbnail x300 -background white -alpha off "/misc/heur-filestore/artem_19/file_uploads/ulf_7_19200424 SMH p. 9.3.pdf"[0] /var/www/html/HEURIST/HEURIST_FILESTORE/artem_19/filethumbs/ulf_AAA.png';

//print '<p>'.$cmdline.'</p>';

                $res2 = 0;
                $output1 = exec($cmdline, $output, $res2);
                if ($res2 != 0 ) {
                    echo ("<p class='error'>Exec error code $res2: Unable to create png file $thumbnail_file&nbsp;<br>");
                    echo $output;
                    echo "</p> Directory may be non-writeable or imagemagic function is not installed on server (error code 127) - please consult system adminstrator<br>";
                }else{
                    $query2 = 'update recUploadedFiles set ulf_Thumbnail=NULL where ulf_ID='.$res['ulf_ID']; 
                    mysql_query($query2);
                    print " OK<br>";
                    $m++;
                }


       
            }else{
                print $res['ulf_ID'].'  '.@$res['res_fullpath'].' not found<br>';                
            }
    }//while
    print 'Converted '.$m;
?>
