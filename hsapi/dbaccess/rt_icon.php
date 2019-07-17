<?php

    /** 
    *  Proxy for rectype icons
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
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
    
/*
parameters

db - database
ent = term (only) @todo make the same

color - convert to given color
checkmode - return json  {"res":"ok"} or {"res":"notfound"}

id - record id    
*/ 
    require_once (dirname(__FILE__).'/../System.php');

    $system = new System();   
    
    $db = @$_REQUEST['db'];
    
    $system->initPathConstants($db);
    
    //$path = HEURIST_ICON_DIR; 
    /*if(substr($rectype_id,-5,5) == "m.png") {
        if(substr($rectype_id,0,4)=='term'){
            $rectype_id = substr($rectype_id, 4);
            $path = HEURIST_TERM_ICON_DIR;
        }
        $rectype_id = substr($rectype_id,0,-5) . ".png";   
    }
    else */
    
    if(@$_REQUEST['ent']=='term'){    //FOR H3 UI
    
        $term_id = @$_REQUEST['id'];
        
        //if(substr($term_id,-4,4) != ".png") $term_id = $term_id . ".png";  
    
        $filename = HEURIST_FILESTORE_DIR . 'term-images/'.$term_id;
        
        $exts = array('png','jpg','jpeg','gif');
        foreach ($exts as $ext){
            if(file_exists($filename.'.'.$ext)){
                $content_type = 'image/'.$ext;
                $filename = $filename.'.'.$ext;
                break;
            }
        }
    
        if (@$_REQUEST['checkmode']=='1'){
            header('Content-type: application/json;charset=UTF-8');
            if(file_exists($filename)){
                print '{"res":"ok"}';
            }else{
                print '{"res":"notfound"}';
            }
        }else if (@$_REQUEST['deletemode']=='1'){
            header('Content-type: text/javascript');
            //header('Content-type: text/html');
            if (@$_REQUEST['deletemode']=='1'){
                if(file_exists($filename)){
                    unlink($filename);
                }
                print '{"res":"ok"}';
            }else{
                print '{"res":"notfound"}';
            }
        }else 
        if(file_exists($filename)){
            download_file($filename);
        }else if (@$_REQUEST['editmode']=='1'){ //show invitation to add image
            download_file(dirname(__FILE__).'/../../hclient/assets/100x100click.png');
        }else {
            download_file(dirname(__FILE__).'/../../hclient/assets/100x100.gif');
        }
        exit();
    }
  
    //record types - @todo as other entities
  
    $rectype_id = @$_REQUEST['id'];
    if(substr($rectype_id,-4,4) != ".png") $rectype_id = $rectype_id . ".png";
    $filename = HEURIST_ICON_DIR . $rectype_id;

    $color_new = _parseColor(@$_REQUEST['color']);
    $circle_color = _parseColor(@$_REQUEST['circle']);
    $bg_circle_color = _parseColor(@$_REQUEST['bg']);
   
    
//print $filename;    
    if(file_exists($filename)){
        download_file($filename);
    }else{
        create_rt_icon_with_bg( $rectype_id, $color_new, $circle_color, $bg_circle_color);
    }
    
//
//
//     
function _parseColor($param_color){

    if($param_color!=null){

        if(strpos($param_color,'rgb')===0){
            $clr = substr($param_color,4,-1);
            $color_new = explode(',',$clr);
        }else{
            //1st way list($r,$g,$b) = array_map('hexdec',str_split($colorName,2));
            //2d way
            $hexcolor = $param_color;
            $shorthand = (strlen($hexcolor) == 4);
            list($r, $g, $b) = $shorthand? sscanf($hexcolor, "#%1s%1s%1s") : sscanf($hexcolor, "#%2s%2s%2s");
            $color_new = $shorthand?array(hexdec("$r$r"), hexdec("$g$g"), hexdec("$b$b")) 
            :array(hexdec($r), hexdec($g), hexdec($b));
        }

    }else{
        $color_new = null; //array(255, 0, 0);    
    }  
    return $color_new;
}
    
 
function download_file($filename){
        ob_start();    
        header('Content-type: image/png');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        @ob_clean();
        flush();        
        readfile($filename);
}    

function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct){ 
    // creating a cut resource 
    $cut = imagecreatetruecolor($src_w, $src_h);  //imagecreatetruecolor

    // copying relevant section from background to the cut resource 
    imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h); 

    // insert cut resource to destination image 
    imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct); 
}  

/*
        //set the image to 256 colours
        imagetruecolortopalette($img_icon,0,256);
        //Find the Chroma colour
        $RemChroma = imagecolorexact( $img_icon,  0,0,255 );        
        //Replace Chroma Colour
        imagecolorset($img_icon, $RemChroma,0,0,254);
        //Use function to convert back to true colour
        imagepalettetotruecolor($img_icon);        

function imagepalettetotruecolor(&$img)
{
        if (!imageistruecolor($img))
        {
            $w = imagesx($img);
            $h = imagesy($img);
            $img1 = imagecreatetruecolor($w,$h);
            imagecopy($img1,$img,0,0,0,0,$w,$h);
            $img = $img1;
        }
}   
*/    
//
//
//    
function create_rt_icon_with_bg( $rectype_id,  $color_new, $circle_color, $bg_circle_color ){ //}, $bg_color ) {

    if(substr($rectype_id,0,4)=='term'){
        $rectype_id = substr($rectype_id, 4);
        $path = HEURIST_TERM_ICON_DIR;
    }else{
        $path = HEURIST_ICON_DIR; 
    }
    
    $alpha = 0;

    if(substr($rectype_id,-5,5) == "m.png") { //for mapping
        $rectype_id = substr($rectype_id, 0, -5);
        //$bg_color = array(0,0,0);   //black
        //$bg_color = array(255,255,255);  //white
        $filename2 = $path . $rectype_id . "m.png";
        $alpha = 127; //0-127
        //$alpha = 60; //semi-trnasparnet bg
    }else if(substr($rectype_id,-5,5) == "s.png") { //for selection
        $rectype_id = substr($rectype_id, 0, -5);
        if(!$bg_circle_color) $bg_circle_color = array(255,255,255); //array(190,228,248);  //#bee4f8   364050  
        $filename2 = $path . $rectype_id . "s.png";
        $alpha = 10;
    }else{
        $rectype_id = substr($rectype_id, 0, -4);
        $filename2 = $path . $rectype_id . ".png";
        //$bg_color = array(200,200,200);   //gray
        $alpha = 127; //0-127
        
        /*if(file_exists($filename2)){
            download_file($filename2);
            return;
        }*/
    }
    
    $filename = $path . $rectype_id . ".png"; //original
    
    if(!file_exists($filename)){  //if term icon does not exist - take default icon
        $filename = HEURIST_ICON_DIR . "3.png";
    }
    
    if(file_exists($filename)){
        
        if($color_new==null){
            download_file($filename);
            return;
        }
        
        
        /*if($alpha==127){
            download_file($filename);
        }*/
       
        
        // load icon
        $img_icon = @imagecreatefrompng($filename);

        $color_old = array(0,0,0);//???? 54,100,139);      
        $color_new = (!$color_new)?array(255, 0, 0):$color_new;  //array(54,64,80);     
        /* RGB of your inside color */
        $rgb = $color_new; //array(0,0,255);
        
        // Negative values, don't edit
        $rgb = array(($color_old[0]-$rgb[0]),($color_old[1]-$rgb[1]),($color_old[2]-$rgb[2]));
        imagefilter($img_icon, IMG_FILTER_NEGATE); 
        imagefilter($img_icon, IMG_FILTER_COLORIZE, $rgb[0], $rgb[1], $rgb[2]); 
        imagefilter($img_icon, IMG_FILTER_NEGATE); 
        //imagealphablending( $img_icon, false );
        //imagesavealpha( $img_icon, true );

        if(true && ($bg_circle_color!=null || $circle_color!=null)){
            
            $img = imagecreatetruecolor(25, 25);      //truecolor image 25x25 pix
            imagealphablending( $img, false );
            imagesavealpha( $img, true );
            
            //fill the background color
            //$bg = imagecolorallocate($img, 200, 200, 200);
            //imagecolortransparent($img, $bg);
            // make the background transparent
            $bg = imagecolorallocatealpha($img, 200, 200, 200, 127);
            
            //draw transparent rectangle
            imagefilledrectangle($img, 0, 0, 25, 25, $bg); //fill bg rectangle
            
            // draw filled circle
            if($bg_circle_color!=null){
                //$col_ellipse = imagecolorallocate($img, $bg_circle_color[0], $bg_circle_color[1], $bg_circle_color[2]);
                $col_ellipse = imagecolorallocatealpha($img, $bg_circle_color[0], $bg_circle_color[1], $bg_circle_color[2], 80);
                imagefilledellipse($img, 12, 12 , 24, 24, $col_ellipse);        
            }
            // draw circle
            if($circle_color!=null){
                $col_ellipse = imagecolorallocate($img, $circle_color[0], $circle_color[1], $circle_color[2]);
                imagearc($img, 12, 12 , 24, 24,  0, 360, $col_ellipse);
                //imagearc($img, 12, 12 , 23, 23,  0, 360, $col_ellipse);
            }        
        
            /*if($alpha==127){
                imagecopy($img, $img_icon, 4, 4, 0, 0, 16, 16); //keep bg of icon - transparent hole
            }*/
            // merge icon and background
            //VER1
            /*
            $imageInfo = getimagesize($filename); 
            if(is_array($imageInfo) && $imageInfo[0]==24  && $imageInfo[1]==24){
                imagecopymerge_alpha($img, $img_icon, 1, 1, 0, 0, 24, 24, 70);  //mix background to dark
            }else{
                //imagecopymerge_alpha($img, $img_icon, 4, 4, 0, 0, 16, 16, 100);  //mix background to dark
                imagecopymerge_alpha($img, $img_icon, 4, 4, 0, 0, 16, 16, 70);  //mix background to dark
            }
            */
            
            //VER2
            
            $imageInfo = getimagesize($filename);
            if(is_array($imageInfo) && $imageInfo[0]==24  && $imageInfo[1]==24){
                imagecopy($img, $img_icon, 1, 1, 0, 0, 24, 24); 
            }else{
                imagecopy($img, $img_icon, 4, 4, 0, 0, 16, 16); 
            }
            
            // output
            header("Content-type: image/png");        
            imagepng($img);        
            imagedestroy($img);
            //imagepng($img, $filename2);   //save to file     
            //readfile($filename2);
        }else{
            // output
            header("Content-type: image/png");        
            imagepng($img_icon);        
        }
    
        imagedestroy($img_icon);    
    }
    
}    
    
?>
