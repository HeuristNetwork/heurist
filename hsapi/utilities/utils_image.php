<?php
//@todo move all image manipulation here 
// from resizeImage, db_files, rt_icon, captcha

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
?>
