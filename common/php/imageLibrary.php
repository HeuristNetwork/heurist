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
* Operations with rectype icons and thumnail images
* used in saveStructureLib.php and uploadRectypeIcon.php
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

/**
* Check the existense of file [databaseid-rectypeid.png]. If not found - creates stub image with text
* return URL to icon file
*
* @param mixed $rectypeID
*/
function getRectypeIconURL($rectypeID){

    $name = $rectypeID.".png";
    $filename = HEURIST_ICON_DIR.$name;

    if(!file_exists($filename)){
        //create the stub image with text - rectypeID
        $img = make_file_image($rectypeID, 16, 2);
        imagepng($img, $filename);
    }
    return HEURIST_ICON_URL.$name;
}

/**
* Check the existense of file [th_databaseid-rectypeid.png].
* If not found - creates stub image with text
*
* return URL to thumbnail file
*
* @param mixed $rectypeID
*/
function getRectypeThumbURL($rectypeID){

    $name = "th_".$rectypeID.".png";
    $filename = HEURIST_ICON_DIR."thumb/".$name;

    if(!file_exists($filename)){
        //create the stub image with text - rectypeID
        $img = make_file_image($rectypeID, 75, 48);
        imagepng($img, $filename);
    }
    return HEURIST_ICON_URL."thumb/".$name;
}

/**
* $rt_id - record type ID
* $filename - from icon library
* @param mixed $rt_id
* @param mixed $filename
*/
function copy_IconAndThumb_FromLibrary($rt_id, $filename) {

    $lib_dir1 = dirname(__FILE__).'/../../admin/setup/iconLibrary/16px/';
    $lib_dir2 = dirname(__FILE__).'/../../admin/setup/iconLibrary/64px/';
    
    if(strpos($filename,'icons8-')===0){
        $filename64 = substr($filename,0,strlen($filename)-6).'64.png';
    }else{
        $filename64 = $filename;
    }
    
    // actually save the file
    $res1 = copy($lib_dir1.$filename, HEURIST_ICON_DIR . $rt_id . '.png');
    $res2 = copy($lib_dir2.$filename64, HEURIST_ICON_DIR . 'thumb/th_' .$rt_id.'.png');

    return $res1 && $res2;
    
    //return false;
}





/**
* Creates the image with text
*
* @param mixed $desc - text (may be multiline)
* @param mixed $dim - dimension
* @return resource
*/
function make_file_image($desc, $dim, $font) {
    $desc = preg_replace('/\\s+/', ' ', $desc);

    if(!$font) $font = 2;
    $fw = imagefontwidth($font); $fh = imagefontheight($font);
    $desc_lines = explode("\n", wordwrap($desc, intval(100/$fw)-1, "\n", false));
    $longlines = false;
    if (count($desc_lines) > intval(100/$fh)) {
        $longlines = true;
    } else {
        foreach ($desc_lines as $line) {
            if (strlen($line) >= intval(100/$fw)) {
                $longlines = true;
                break;
            }
        }
    }
    if ($longlines) {
        $font = 1; $fw = imagefontwidth($font); $fh = imagefontheight($font);
        $desc_lines = explode("\n", wordwrap($desc, intval(100/$fw)-1, "\n", true));
    }

    if(!$dim){
        $dim = 100;
    }


    $im = imagecreate($dim, $dim);
    $white = imagecolorallocate($im, 255, 255, 255);
    $grey = imagecolorallocate($im, 160, 160, 160);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefilledrectangle($im, 0, 0, $dim, $dim, $white);

    //imageline($im, 35, 25, 65, 75, $grey);
    /*
    imageline($im, 33, 25, 33, 71, $grey);
    imageline($im, 33, 25, 62, 25, $grey);
    imageline($im, 62, 25, 67, 30, $grey);
    imageline($im, 67, 30, 62, 30, $grey);
    imageline($im, 62, 30, 62, 25, $grey);
    imageline($im, 67, 30, 67, 71, $grey);
    imageline($im, 67, 71, 33, 71, $grey);
    */

    $y = intval(($dim - count($desc_lines)*$fh) / 2);
    foreach ($desc_lines as $line) {
        $x = intval(($dim - strlen($line)*$fw) / 2);
        imagestring($im, $font, $x, $y, $line, $black);
        $y += $fh;
    }

    return $im;
}

/**
* resize and converto png
*
*
* @param mixed $img - image to convert
* @param mixed $dim - dimensions or max new dimension
* @param mixed $filename
*/
function convert_to_png($img, $dim, $filename){

    $error = null;

    $new_x = 16;
    $new_y = 16;
    $orig_x = imagesx($img);
    $orig_y = imagesy($img);

    if(is_array($dim) && count($dim)==2){ //in case of array - exact size
        $new_x = $dim[0];
        $new_y = $dim[1];
        $dim_x = $dim[0];
        $dim_y = $dim[1];

    }else if (is_numeric($dim)) { //proportional

        $dim_x = $dim;
        $dim_y = $dim;

        if($orig_x>$dim || $orig_y>$dim){

            if($orig_x>$orig_y){
                $new_x = $dim;
                $new_y = ceil($dim/$orig_x * $orig_y);
            } else {
                $new_x = ceil($dim/$orig_y * $orig_x);
                $new_y = $dim;
            }


        }else{
            $new_x = $orig_x;
            $new_y = $orig_y;
        }
    }

    $img_resized = imagecreatetruecolor($dim_x, $dim_y)  or die;
    imagesavealpha($img_resized, true);

    $transparent = imagecolorallocatealpha( $img_resized, 0, 0, 0, 127 );
    imagefill( $img_resized, 0, 0, $transparent );

    $left = ceil(($dim-$new_x)/2) ;
    $top = ceil(($dim-$new_y)/2) ;


    if(!imagecopyresampled($img_resized, $img, $left, $top, 0, 0, $new_x, $new_y, $orig_x, $orig_y)){
        $error = 'An error occurred while uploading the file - can\'t resize the image';
    }

    if(!imagepng($img_resized, $filename)){
        $error = 'An error occurred while uploading the file - check directory permissions';
    }
    imagedestroy($img);
    imagedestroy($img_resized);

    return $error;
}
?>
