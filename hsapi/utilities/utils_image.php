<?php



// from resizeImage, db_files, rt_icon, captcha
//use Screen\Capture; //for micorweber

/* from db_files
* getImageFromFile - return image object for given file
* getPrevailBackgroundColor2  - not used
* getPrevailBackgroundColor 
*/

    /**
    * Image manipulation library
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
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
  
  
class UtilsImage {
  
    private function __construct() {}    
    
    /**
    * Creates image from given string
    *
    * @param mixed $desc - text to be inserted into resulted image
    * @return resource - image with the given text
    */
    public static function createFromString($desc) {
        $desc = preg_replace('/\\s+/', ' ', $desc);

        $font = 3; $fw = imagefontwidth($font); $fh = imagefontheight($font);
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


        $im = imagecreate(100, 100);
        $white = imagecolorallocate($im, 255, 255, 255);
        $grey = imagecolorallocate($im, 160, 160, 160);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, 100, 100, $white);

        //imageline($im, 35, 25, 65, 75, $grey);
        imageline($im, 33, 25, 33, 71, $grey);
        imageline($im, 33, 25, 62, 25, $grey);
        imageline($im, 62, 25, 67, 30, $grey);
        imageline($im, 67, 30, 62, 30, $grey);
        imageline($im, 62, 30, 62, 25, $grey);
        imageline($im, 67, 30, 67, 71, $grey);
        imageline($im, 67, 71, 33, 71, $grey);

        $y = intval((100 - count($desc_lines)*$fh) / 2);
        foreach ($desc_lines as $line) {
            $x = intval((100 - strlen($line)*$fw) / 2);
            imagestring($im, $font, $x, $y, $line, $black);
            $y += $fh;
        }

        return $im;
    }    


    /**
    * makes screenshot for given url
    * 
    * @param mixed $url
    */
    public static function makeURLScreenshot($siteURL){

        if(filter_var($siteURL, FILTER_VALIDATE_URL)){

            //$remote_path =  str_replace("[URL]", $sURL, WEBSITE_THUMBNAIL_SERVICE);
            $heurist_path = tempnam(HEURIST_SCRATCH_DIR, "_temp_");


            if(defined('WEBSITE_THUMBNAIL_SERVICE') && WEBSITE_THUMBNAIL_SERVICE!=''){
            
                $remote_path =  str_replace("[URL]", $siteURL, WEBSITE_THUMBNAIL_SERVICE);
                $filesize = saveURLasFile($remote_path, $heurist_path);

                //check the dimension of returned thumbanil in case it is less than 50 - consider it as error
                if(strpos($remote_path, substr(WEBSITE_THUMBNAIL_SERVICE,0,24))==0){

                    $image_info = getimagesize($heurist_path);
                    if($image_info[1]<50){
                        //remove temp file
                        unlink($heurist_path);
                        return array('error'=>'Thumbnail generator service can\'t create the image for specified URL');
                    }
                }

            }else 
            if(true){  

                //call Google PageSpeed Insights API
                $googlePagespeedData = file_get_contents(
                "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=$siteURL&screenshot=true");

                //decode json data
                $googlePagespeedData = json_decode($googlePagespeedData, true);

                //screenshot data
                
                //full-page-screenshot details screenshot data
                //screenshot-thumbnails details items[] data
                $screenshot = @$googlePagespeedData['lighthouseResult']['audits']['final-screenshot']['details']['data'];  
                
                //$screenshot = str_replace(array('_','-'),array('/','+'),$screenshot);
                
                $fp = fopen($heurist_path, "w+");
                fwrite($fp, base64_decode($screenshot));
                fclose($fp);            
                
                //display screenshot image
                //echo "<img src=\"data:image/jpeg;base64,".$screenshot."\" /-->";

            }else{ //microweber (https://github.com/microweber/screen) - it doesn't work
                $screenCapture = new Capture();
                $screenCapture->setUrl($siteURL);
                $screenCapture->setWidth(1200);
                $screenCapture->setHeight(800);

                // allowed types are 'jpg' and 'png', default is 'jpg'.
                //$screenCapture->setImageType(Screen\Image\Types\Png::FORMAT);
                // or $screenCapture->setImageType('png');        
                //$screenCapture->jobs->setLocation('/path/to/jobs/dir/');
                $screenCapture->save($heurist_path);
            }
            
            if(file_exists($heurist_path)){
                
                $filesize = filesize($heurist_path);
                
                $file = new \stdClass();
                $file->original_name = 'snapshot.jpg';
                $file->name = $heurist_path; //pathinfo($heurist_path, PATHINFO_BASENAME); //name with ext
                $file->fullpath = $heurist_path;
                $file->size = $filesize; //fix_integer_overflow
                $file->type = 'jpg';
                
                return $file;         

            }else{
                return array('error'=>'Cannot download image from thumbnail generator service. '.$siteURL.' to '.$heurist_path);
            }
            
        }else{
            return array('error'=>'URL to generate snapshot '.$siteURL.' is not valid');
        }
    }

    /**
    * download image from given url
    *
    * @param mixed $remote_url
    * @return resource
    */
    public static function getRemoteImage($remote_url){  //get_remote_image

        $img = null;
        
        $data = loadRemoteURLContent($remote_url, false); //from utils_file.php
        if($data){
            try{    
                $img = imagecreatefromstring($data);
            }catch(Exception  $e){
                $img = false;
            }
        }else{
            $img = false;
        }

        return $img;
    }
    
    
    private static function _parseColor($param_color){

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

    /**
    *     
    */
    public static function changeImageColor($filename, $filename_new, $color_new, $circle_color, $bg_circle_color){
      
        if(file_exists($filename)){
            
            
            $color_new = UtilsImage::_parseColor($color_new);
            $circle_color = UtilsImage::_parseColor($circle_color);
            $bg_circle_color = UtilsImage::_parseColor($bg_circle_color);
            
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

            if($bg_circle_color!=null || $circle_color!=null){
                
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
                
                $imageInfo = getimagesize($filename);
                if(is_array($imageInfo) && $imageInfo[0]==24  && $imageInfo[1]==24){
                    imagecopy($img, $img_icon, 1, 1, 0, 0, 24, 24); 
                }else{
                    imagecopy($img, $img_icon, 4, 4, 0, 0, 16, 16); 
                }
                
                // output
                if($filename_new){
                    imagepng($img, $filename_new);   //save to file     
                }else{
                    header("Content-type: image/png");        
                    imagepng($img);        
                }
                imagedestroy($img);
                //readfile($filename2);
            }else{
                // output
                if($filename_new){
                    imagepng($img_icon, $filename_new);   //save to file     
                }else{
                    header("Content-type: image/png");        
                    imagepng($img_icon);        
                }
            }
        
            imagedestroy($img_icon);    
            
        }
    }
    

}
?>
