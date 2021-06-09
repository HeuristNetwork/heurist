<?php
//@todo move all image manipulation here 
// from resizeImage, db_files, rt_icon, captcha
//use Screen\Capture; //for micorweber

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
  
  
/**
* Creates image from given string
*
* @param mixed $desc - text to be inserted into resulted image
* @return resource - image with the given text
*/
function image_CreateFromString($desc) {
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
function image_CreateFromURL($siteURL){

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
?>
