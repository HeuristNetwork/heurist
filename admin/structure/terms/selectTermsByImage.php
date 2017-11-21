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
    * selectTermsByImage.php
    * select term by image
    *
    * @author      Tom Murtagh
    * @author      Kim Jackson
    * @author      Ian Johnson   <ian.johnson@sydney.edu.au>
    * @author      Stephen White   
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2016 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1.0
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */
    define('SAVE_URI', 'disabled');

    require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
    $ids = explode(',',@$_REQUEST['ids']);
?>
<html>
    <head>

        <title>Choose term by image</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="<?=HEURIST_BASE_URL?>common/css/global.css">
        <link rel="stylesheet" type="text/css" href="<?=HEURIST_BASE_URL?>common/css/edit.css">
        <link rel="stylesheet" type="text/css" href="<?=HEURIST_BASE_URL?>common/css/admin.css">

        <style type="text/css">
            .success { font-weight: bold; color: green; margin-left: 3px; }
            .failure { font-weight: bold; color: red; margin-left: 3px; }
            .input-row div.input-header-cell {width:110px; vertical-align:baseline; min-width:110px;}
            
            .itemlist{
                height:140px;
                width:130px;
                display:inline-block;
                text-align:center;
                padding:4px;margin:4px;
                border:gray; 
                border-radius: 3px; box-shadow: 0 1px 3px RGBA(0,0,0,0.5);
            }
            .itemimage{
                height:120px;
                width:120px;
                background-size: contain;
                background-position: center;
                background-repeat: no-repeat;
            }
        </style>
        <script>
            function closewin(context){
                window.close(context);
            }
        </script>
    </head>

    <body class="popup">
        <script>
            function onImageSelect(name){
                 window.close(name);
            }
        </script>
        <!-- div class="actionButtons" style="position:absolute; right:10px; top:4px">
                <input type="button" value="Close window" onClick="closewin()">
        </div -->
        <div style="margin-bottom:30px">
<?php
//get list of files
    $dim = 120; //always show icons  ($mode!=1) ?16 :64;
    $lib_dir = HEURIST_FILESTORE_DIR . 'term-images/';

    $files = array();
    foreach ($ids as $id){
        $filename = $lib_dir.$id.'.png';
        if(file_exists($filename)){
            array_push($files, $id);
        }
    }
    if(count($files)>0){
        
        $term_labels = mysql__select_assoc('defTerms', 'trm_ID', 'trm_Label', 'trm_ID in ('.implode(',',$files).')');
        
        
    foreach ($files as $id){
        print '<div class="itemlist" style="">';
        print '<a href="#" onclick="onImageSelect('.$id.')">';
                           // height="'.$dim.'" max-height:'.$dim.';
        print '<img class="itemimage" style="background-image:url('
                .HEURIST_BASE_URL.'hserver/dbaccess/rt_icon.php?db='.HEURIST_DBNAME.'&ent=term&id='.$id.')" '
                .'src="'.HEURIST_BASE_URL.'common/images/200.gif"/></a>'
                .'<br><label>'.$term_labels[$id].'</label></div>';
//to avoid issues with access   print '<img height="'.$dim.'" src="'.HEURIST_FILESTORE_URL.'term-images/'.$id.'.png"/></a></div>';
    }
    }else{
        print 'No terms images defined<script>setTimeout("closewin()", 2000);</script>';   
    }
?>
          </div>
    </body>
</html>