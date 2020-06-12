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
    * uploadRectypeIcon.php
    * uploads icons and thumbnails for record types
    *
    * @author      Tom Murtagh
    * @author      Kim Jackson
    * @author      Ian Johnson   <ian.johnson@sydney.edu.au>
    * @author      Stephen White   
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2020 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1.0
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */

define('MANAGER_REQUIRED',1);   
define('PDIR','../../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../../common/php/imageLibrary.php');

$mysqli = $system->get_mysqli();

    $rt_id = intval(@$_REQUEST['trm_ID']);
    if($rt_id>0){
        
        if( @$_FILES['new_term']['size']>0 ){
            list($success_msg, $failure_msg) = upload_file($rt_id, 'new_term');
            header('Content-type: application/json'); //'text/javascript');
            print json_encode(array('sucess'=>$success_msg,'error'=>$failure_msg));
        }        
        exit();
    }
    
    $rt_id = intval($_REQUEST['rty_ID']);
    $mode = intval($_REQUEST['mode']);  //0 - icon, 1 - thumbnail

    if ($mode==4 && $rt_id>0) { // verification of icon existense
        $filename = HEURIST_ICON_DIR . $rt_id . '.png';
        if(file_exists($filename)){
            print 1;
        }else{
            print 0;
        }
        exit();
    
    }
    
    if ($mode!=3 && !$rt_id) { // no ID set, hopefully this should not occur
        return;
    }
    $rt_name = @$_REQUEST['rty_Name'];

    $image_icon      = getRectypeIconURL($rt_id);
    $image_thumbnail = getRectypeThumbURL($rt_id);
    $success_msg = null;
    $failure_msg = null;


    if(@$_REQUEST['libicon']){
        //take from  library
        if(copy_IconAndThumb_FromLibrary($rt_id, $_REQUEST['libicon'])){
            //error
            list($success_msg, $failure_msg) = array('', "Library file: $filename couldn't be saved to upload path defined for db = "
            .HEURIST_DBNAME." (".HEURIST_ICON_DIR."). Please ask your system administrator to correct the path and/or permissions for this directory");
        }else{
            list($success_msg, $failure_msg) = array('Icon and thumbnail have been set successfully', '');
        }

    }else{
        //upload new one

        if( @$_FILES['new_thumb']['size']>0 ){
            list($success_msg, $failure_msg) = upload_file($rt_id, 'new_thumb');
        }else if( @$_FILES['new_icon']['size']>0 ){
            list($success_msg, $failure_msg) = upload_file($rt_id, 'new_icon');
        }
    }

?>
<html>
    <head>

        <title>Choose record type icon<?=(($mode==3)?"":" for ".$rt_id." : ".htmlspecialchars($rt_name))?></title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>
        
        <!-- CSS -->
        <?php include dirname(__FILE__).'/../../../hclient/framecontent/initPageCss.php'; ?>

        <style type="text/css">
            .success { font-weight: bold; color: green; margin-left: 3px; }
            .failure { font-weight: bold; color: red; margin-left: 3px; }
            .input-row div.input-header-cell {width:110px; vertical-align:baseline; min-width:110px;}
        </style>
        <script>
            function closewin(context){
                window.close(context);
            }
        </script>
    </head>

    <body class="popup">
        <script>
            function onLibIconSelect(name){
                <?php if ($mode==3) {
                    print "window.close(name);";
                } else { ?>
                    var ele = document.getElementById('libicon');
                    ele.value = name;
                    document.forms[0].submit();
                <?php } ?>
            }
            
            $(document).ready(function() {
               $('button').button();
            });                
            
        </script>
        

<?php if($mode!=3){ ?>
        <fieldset>
            <div class="input-cell">
                <div class="header"><label>Current thumbnail:</label></div>
                <img src="<?=$image_thumbnail?>?<?= time() ?>" style="vertical-align: middle; height:64px;">
                <div class="header"><label>Current icon:</label></div>
                <img src="<?=$image_icon?>?<?= time() ?>" style="vertical-align: middle; height:16px;">
            </div>
        </fieldset>
<?php } ?>
        <div class="actionButtons" style="position:absolute; right:10px; top:10px">
                <!-- input type="button" onClick="window.document.forms[0].submit();" value="Upload" style="margin-right:10px" -->
                <button onClick="closewin()">Close window</button>
        </div>

        <form action="uploadRectypeIcon.php?db=<?= HEURIST_DBNAME?>" method="post" enctype="multipart/form-data" border="0">
            <input type="hidden" name="rty_ID" value="<?= $rt_id ?>">
            <input type="hidden" name="libicon" id="libicon" value="" />
            <input type="hidden" name="mode" value="<?=$mode?>">

<?php if($mode!=3){ ?>

            <?php    if ($success_msg) { ?>
            <div class="success"><?= $success_msg ?></div>
            <?php    } else if ($failure_msg) { ?>
            <div class="failure"><?= $failure_msg ?></div>
            <?php    } else { ?>
            <div style="padding:10px 0">Uploaded image will be scaled to 16x16 for icon or 64x64 for thumbnail</div>
            <?php    } ?>
<!--
            <div class="input-row">
                <div class="input-header-cell">Select type</div>
                <div class="input-cell">
                    <input type="radio" name="mode" value="0" <?= $mode==0?"checked":"" ?>>&nbsp;Icon&nbsp;&nbsp;
                    <input type="radio" name="mode" value="1" <?= $mode==1?"checked":"" ?>>&nbsp;Thumbnail
                </div>
            </div>
-->
            <fieldset>
                <div class="input-cell">
                    <div class="header"><label>Select new thumbnail</label></div>
                    <input type="file" name="new_thumb" style="display:inline-block;" onchange="javascript:this.form.submit();">

                    <div class="header"><label>Select new icon</label></div>
                    <input type="file" name="new_icon" style="display:inline-block;" onchange="javascript:this.form.submit();">
                </div>
            </fieldset>
<?php
            $select_tip = "or select a pre-defined icon below<br> (this will automatically select 16x16 and 64x64 icons)";
      }else{
            $select_tip = "Select a pre-defined icon below<br> (this will automatically select 16x16 and 64x64 icons) <br> You may choose your own custom icons later by editing the record type definition";
      }
?>
            <div style="margin-bottom:30px;">
                <span class="help"><?=$select_tip?></span><br />
<?php
//get list of files
    $dim = 16; //always show icons  ($mode!=1) ?16 :64;
    $lib_path = 'setup/iconLibrary/'.$dim.'px/';
    $lib_dir = dirname(__FILE__).'/../../'.$lib_path;

    $files = scandir($lib_dir);
    $results = array();

    foreach ($files as $filename){

        $path_parts = pathinfo($filename);
        if(array_key_exists('extension', $path_parts))
        {
            $ext = strtolower($path_parts['extension']);

            if(file_exists($lib_dir.$filename) && $ext=="png")
            {
                //$path_parts['filename'] )); - does not work for nonlatin names
                $name = substr($filename, 0, -4);
                print '<div style="display:inline-block;padding:4px;width:40px;">';
                print '<a href="#" oncontextmenu="return false;"  onclick="onLibIconSelect(\''.$filename.'\')"><img height="'.$dim.'" width="'.$dim.'" src="'.HEURIST_BASE_URL.'admin/'.$lib_path.$filename.'"/></a></div>';
                //array_push($results, array( 'filename'=>$filename, 'name'=>$name));
            }
        }
    }
?>
            </div>

        </form>

        <?php    if ($success_msg) { ?>
            <script  type="text/javascript">
                //setTimeout(function(){closewin('ok');}, 1000);
            </script>
            <?php    } ?>
    </body>
</html>
<?php

    /***** END OF OUTPUT *****/
    //
    //
    //
    function upload_file($rt_id, $type) {

        if($type=='new_term'){
            $image_dir = HEURIST_FILESTORE_DIR . 'term-images/';
            $dim = 400;
        }else{
            $image_dir = HEURIST_ICON_DIR.(($type=='new_icon')?'':'thumb/th_');
            $dim = ($type=='new_icon')?16:64;
        }        

        if ( !$_FILES[$type]['size'] ) return array('', 'Error occurred during upload - file had zero size '.$type.'  '.$_FILES[$type]['size']);
        $mimeExt = $_FILES[$type]['type'];
        $filename = $_FILES[$type]['tmp_name'];
        $origfilename = $_FILES[$type]['name'];
        $img = null;

        switch($mimeExt) {
            case 'image/jpeg':
            case 'jpeg':
            case 'jpg':
                $img = @imagecreatefromjpeg($filename);
                break;
            case 'image/gif':
            case 'gif':
                $img = @imagecreatefromgif($filename);
                break;
            case 'image/png':
            case 'png':
                $img = @imagecreatefrompng($filename);
                break;
            default:
                break;
        }

        if (! $img) return array('', 'Uploaded file is not supported format');

        // check that the image is not mroe than trwice desired size to avoid scaling issues
        //if (imagesx($img) > ($dim*2)  ||  imagesy($img) > ($dim*2)) return array('','Uploaded file must be no larger than twice '.$dim.' pixels in any direction');

        $newfilename = $image_dir . $rt_id . '.png'; // tempnam(sys_get_temp_dir(), 'resized');

        $error = convert_to_png($img, $dim, $filename);

        if($error){
            return array('', $error);
        }else if (move_uploaded_file($filename, $newfilename)) { // actually save the file
            return array('File has been uploaded successfully', '');
        }
        /* something messed up ... make a note of it and move on */
        return array('', "upload file: $name couldn't be saved to upload path defined for db = "
            .HEURIST_DBNAME." (".$image_dir."). Please ask your system administrator to correct the path and/or permissions for this directory");
    }

    // not used
    function test_transparency($gifstring) {
        if (substr($gifstring, 0, 6) != 'GIF89a') return false;

        $gct_size = 2 << (ord($gifstring[10]) & 0x07);
        if (substr($gifstring, 13 + $gct_size*3, 2) != "\x21\xf9") return true;	// maybe transparency defined later

        $packed_fields = ord($gifstring[13 + $gct_size*3 + 3]);
        if ($packed_fields & 0x01) return true;
        else return false;
    }

?>
