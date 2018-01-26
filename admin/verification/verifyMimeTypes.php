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
    * Verifies that important mimetypes exist. Add if missed
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
    require_once(dirname(__FILE__).'/../../common/php/utilsMail.php');

    if (! is_logged_in()) {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
        return;
    }

    if (!is_admin()) {
        print "Sorry, you need to be a database owner to be able to modify the database structure";
        return;
    }
    
    //1. find all database
    $query = 'show databases';

    $res = mysql_query($query);
    if (!$res) {  print $query.'  '.mysql_error();  return; }
    $databases = array();
    while (($row = mysql_fetch_row($res))) {
        if( strpos($row[0], 'hdb_')===0 ){
            //if($row[0] == 'hdb_osmak_1')
                $databases[] = $row[0];
        }
    }
    
    foreach ($databases as $idx=>$db_name){
        
        print $db_name.'<br>';
/* 
$query = 'ALTER TABLE `'.$db_name.'`.`defDetailTypes` '
.' CHANGE COLUMN `dty_PtrTargetRectypeIDs` `dty_PtrTargetRectypeIDs` VARCHAR(250) NULL DEFAULT NULL COMMENT "CSVlist of target Rectype IDs, null = any"';
        $res = mysql_query($query);
        if(!$res){
            print mysql_error();
            break;
        }
        continue;
*/

        $query = 'USE '.$db_name;
        $res = mysql_query($query);
        $query = 'SET FOREIGN_KEY_CHECKS=0';
        $res = mysql_query($query);
        $query = 'DELETE FROM defFileExtToMimetype WHERE fxm_Extension in ("mp3","mp4","ogg","ogv","soundcloud","vimeo","webm","youtube")';
        $res = mysql_query($query);
        
        $query = 'INSERT INTO defFileExtToMimetype '
        .'(`fxm_Extension`,`fxm_MimeType`, `fxm_OpenNewWindow`,`fxm_IconFileName`,`fxm_FiletypeName`,`fxm_ImagePlaceholder`) '
        .'VALUES '
."('mp3','audio/mp3', '0','audio.gif','MP3 audio',''),"   //was mpeg
."('mp4','video/mp4', '0','movie.gif','MP4 video',''),"
."('ogg','video/ogg', '0','','Ogg Vorbis',''),"   //was application
."('ogv','video/ogg', '0','','Ogg Vorbis Video',''),"
."('soundcloud','audio/soundcloud', '0','','Soundcloud',''),"
."('vimeo','video/vimeo', '0','','Vimeo Video',''),"
."('webm','video/webm', '0','','WEBM video',''),"
."('youtube','video/youtube', '0','','Youtube Video','')";
        $res = mysql_query($query);

        $query = 'SET FOREIGN_KEY_CHECKS=1';
        $res = mysql_query($query);
        
    }//while  databases
?>
