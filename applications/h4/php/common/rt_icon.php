<?php

    /** 
    *  Proxy for rectype icons
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
    require_once (dirname(__FILE__).'/../System.php');

    $system = new System();   
    
    $db = @$_REQUEST['db'];
    $rectype_id = @$_REQUEST['id'];
    
    $system->initPathConstants($db);
    
    if(substr($rectype_id,-4,4) != ".png") $rectype_id = $rectype_id . ".png";
    
    $filename = HEURIST_ICON_DIR . $rectype_id;

//print $filename;    
    
    if(file_exists($filename)){
        ob_start();    
        header('Content-type: image/png');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        ob_clean();
        flush();        
        readfile($filename);
    }else{
        create_rt_icon_with_bg( $rectype_id );
    }
    

function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct){ 
    // creating a cut resource 
    $cut = imagecreatetruecolor($src_w, $src_h); 

    // copying relevant section from background to the cut resource 
    imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h); 

    // copying relevant section from watermark to the cut resource 
    imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h); 

    // insert cut resource to destination image 
    imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct); 
}     
    
//
//
//    
function create_rt_icon_with_bg( $rectype_id ){ //}, $bg_color ) {

    if(substr($rectype_id,-5,5) == "m.png") {
        $rectype_id = substr($rectype_id, 0, -5);
        $bg_color = array(200,200,200);
        $filename2 = HEURIST_ICON_DIR . $rectype_id . "m.png";
    }else if(substr($rectype_id,-5,5) == "s.png") {
        $rectype_id = substr($rectype_id, 0, -5);
        $bg_color = array(190,228,248);  //#bee4f8
        $filename2 = HEURIST_ICON_DIR . $rectype_id . "s.png";
    }else{
        return;
    }
    
    $filename = HEURIST_ICON_DIR . $rectype_id . ".png";
    
    
//error_log($filename);
//error_log($filename2);
    
    if(file_exists($filename)){
        
        $img = imagecreatetruecolor(25, 25);
        
        // fill the background color
        $bg = imagecolorallocate($img, 255, 127, 0);
        // make the background transparent
        imagecolortransparent($img, $bg);
        
        //draw transparent rectangle
        imagefilledrectangle($img, 0, 0, 25, 25, $bg);
        
        // draw circle
        $col_ellipse = imagecolorallocate($img, $bg_color[0], $bg_color[1], $bg_color[2]);
        imagefilledellipse($img, 12, 12 , 24, 24, $col_ellipse);        
        
        // load icon
        $img_icon = @imagecreatefrompng($filename);
        
        // merge icon
        imagecopymerge_alpha($img, $img_icon, 4, 4, 0, 0, 16, 16, 75);
        
        // output
        header("Content-type: image/png");
        
//error_log("ke");        
        imagepng($img);        
        //imagepng($img, $filename2);        
        
        //readfile($filename2);
    
    
        imagedestroy($img);
        imagedestroy($img_icon);    
    }
    
}    
    
?>
