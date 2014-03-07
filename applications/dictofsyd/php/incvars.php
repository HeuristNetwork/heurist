<?php
    /*
    * Copyright (C) 2005-2013 University of Sydney
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
    * Dictionary of Sydney Heurist web site generator: Global variables and constants
    *
    * IMPORTANT! Location must be specified explicitely in  $urlbase
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2013 University of Sydney
    * @link        http://sydney.edu.au/heurist
    * @version     3.1.0
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  applications
    */

    
    /*  FILE PATHS, PASSWORDS ETC. WHICH MAY REQUIRE CHANGING */
    
    define('DOMAIN','http://heuristscholar.org/');

    $g_title = "Dictionary of Sydney";
    $urlbase_absolute = DOMAIN."HEURIST/h3-ao/applications/dictofsyd/";
    $urlbase = DOMAIN."HEURIST/h3-ao/applications/dictofsyd/";
    $deploypath = "var/www/html/HEURIST/HEURIST_FILESTORE/dosh3-deploy/";
    
    $deployurl = DOMAIN."HEURIST/HEURIST_FILESTORE/dosh3-deploy/"; //it overwrites $urlbase for generated pages
    $startupurl = "http://dictionaryofsydney.org/"; //for logo icon
    
    // Login information for MySQL
    define('HEURIST_DBNAME', 'DoS3');
    define('READONLY_DBUSERNAME', 'root');
    include dirname(__FILE__).'/DoSPassword.inc'; // avoid including in repository
    define('READONLY_DBUSERPSWD', $DoSPassword);
   
    // Port 3306 is not open, everything is happening locally
    define('HEURIST_DBSERVER_NAME', 'heur-db-pro-1.ucc.usyd.edu.au:3306');  //NOT USED!!!
    
    define('HEURIST_THUMB_DIR', '/var/www/html/HEURIST/HEURIST_FILESTORE/' . HEURIST_DBNAME . '/filethumbs/');
    define('HEURIST_THUMB_BASE_URL', DOMAIN.'HEURIST_FILESTORE/' . HEURIST_DBNAME . '/filethumbs/');
    //define('DOS_FILES', DOMAIN.'HEURIST_FILESTORE/HEURIST_Vsn2_uploaded-heurist-files/dos/');  // Heurist Vsn2 file path
       
    /*  END FILE PATHS, PASSWORDS ETC. WHICH MAY REQUIRE CHANGING */
  
    ini_set('memory_limit', '256M'); //'128M');
  
    $is_generation = false;
    $use_pointer_cache = true;
    $media_filepath = ""; //media subfolder for generated pages
    $db_selected = null;

    $query_times = "";
 

    define('RT_RELATION', 1);
    define('RT_WEBLINK', 2);
    define('RT_MEDIA', 5);
    define('RT_TILEDIMAGE', 11);

    define('RT_CONTRIBUTOR', 24);
    define('RT_ENTITY', 25);
    define('RT_FACTOID', 26);
    define('RT_ROLE', 27);
    define('RT_MAP', 28);
    define('RT_TERM', 29); //TERM

    define('RT_ENTRY', 13);
    define('RT_ANNOTATION', 15);


    define('DT_NAME', 1);
    define('DT_NAME2', 2);
    define('DT_SHORTSUMMARY', 3);
    define('DT_DESCRIPTION', 4);
    define('DT_RELATION_TARGET', 5);
    define('DT_RELATION_TYPE', 6);
    define('DT_RELATION_SOURCE', 7);
    define('DT_DATE', 9);
    define('DT_DATE_START', 10);
    define('DT_DATE_END', 11);
    define('DT_GEO', 28);
    define('DT_FILE',38);
    define('DT_FILE_THUMBNAIL',39);

    define('DT_TYPE_MIME', 29);
    define('DT_MEDIA_REF',61);
    define('DT_TYPE_LICENSE', 94);

    define('DT_TILEDIMAGE_TYPE',30);
    define('DT_TILEDIMAGE_SCHEME',31);
    define('DT_TILEDIMAGE_ZOOM_MIN',32);
    define('DT_TILEDIMAGE_ZOOM_MAX',33);
    define('DT_TILEDIMAGE_URL',34);

    //588 93	67	Pointer to Map Image Layer record
    //590 94	584	Rights for Image layer  ENUM
    define('DT_COPYRIGHT_STATEMENT', 35);

    define('DT_ENTITY_TYPE', 75);

    define('DT_FACTOID_TYPE', 85);
    define('DT_FACTOID_TARGET', 86);
    define('DT_FACTOID_SOURCE', 87);
    define('DT_FACTOID_ROLE', 88);
    define('DT_FACTOID_DESCR', 97);

    define('DT_ROLE_TYPE', 95);

    define('DT_ENTRY_WML', 81);


    define('DT_ANNOTATION_ENTITY', 13);
    define('DT_ANNOTATION_ENTRY', 42);
    define('DT_ANNOTATION_START_ELEMENT', 46);
    define('DT_ANNOTATION_END_ELEMENT', 47);
    define('DT_ANNOTATION_START_WORD', 44);
    define('DT_ANNOTATION_END_WORD', 45);
    define('DT_ANNOTATION_TYPE', 52); //was 96

    define('DT_CONTRIBUTOR_REF', 90);
    define('DT_CONTRIBUTOR_TYPE', 74);
    define('DT_COPYRIGHTS', 94);

    define('DT_CONTRIBUTOR_ID', 84);
    define('DT_CONTRIBUTOR_LINK', 17);
    define('DT_CONTRIBUTOR_ITEM_URL', 82);
    define('DT_CONTRIBUTOR_FREETEXT', 83);
    define('DT_CONTRIBUTOR_ATTRIBUTION', 92);

    define('DT_MAP_KML_REF', 91);
    define('DT_MAP_TILEDIMAGE_REF', 93);



    /**
    * database connection
    */
    function mysql_connection_select(){

        $link = mysql_connect('localhost:3306', READONLY_DBUSERNAME, READONLY_DBUSERPSWD) or die('Could not connect to mysql server. '.mysql_error());
        $db_selected = mysql_select_db('hdb_'.HEURIST_DBNAME, $link);
        mysql_query('set character set "utf8"');
        mysql_query('set names "utf8"');

        return $db_selected;
    }
    
    function add_error_log($text){
        global $error_log;
        if(isset($error_log)){
            array_push($error_log, $text);
        }else{
            error_log($text);
        }
        
    }
?>
