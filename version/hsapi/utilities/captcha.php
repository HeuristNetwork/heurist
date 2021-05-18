<?php
    /**
    * Capture
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
session_name('heurist-sessionid');
session_start();
/*
if(@$_REQUEST['captcha_code']){
    //CAPTCHA Matching code

    if ($_SESSION["captcha_code"] == $_POST["captcha_code"]) {
          $response = array('status'=>HEURIST_OK, 'data'=>'ok');
    } else {
          $response = array('status'=>HEURIST_OK, 'data'=>'no');
    }

    header('Content-type: text/javascript');
    print json_encode($response);
    exit();

}else */
if(@$_REQUEST['img']){ //IMAGE CAPTCHA
    $captchanumber = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxyz'; // Initializing PHP variable with string
    $captcha_code = substr(str_shuffle($captchanumber), 0, 4); // Getting first 6 word after shuffle.

    $_SESSION["captcha_code"] = $captcha_code;
    $target_layer = imagecreatetruecolor(50,24);
    $captcha_background = imagecolorallocate($target_layer, 255, 160, 119);//#dbdfe6  219, 223, 230);
    imagefill($target_layer,0,0,$captcha_background);
    $captcha_text_color = imagecolorallocate($target_layer, 0, 0, 0);
    imagestring($target_layer, 5, 5, 5, $captcha_code, $captcha_text_color);
    header("Content-type: image/jpeg");
    imagejpeg($target_layer);
}else{  //TRIVIA CAPTCHA
    $planets = array('Sun','Jupiter','Saturn','Uranus','Neptune','Earth','Venus','Mars','Titan','Mercury','Moon','Europa','Triton','Pluto');
    $ran0 = rand(0,13);
    $ran1 = rand(1,9);
    $ran2 = rand(1,9);
    // $captcha_code = strtolower($planets[$ran0]).($ran1+$ran2);
    $captcha_code = ($ran1+$ran2) + 1;
    $_SESSION["captcha_code"] = $captcha_code;
    // print "Answer: the word '".strtolower($planets[$ran0])."' followed by the sum of ".$ran1." and ".$ran2;
    print $ran1." + ".$ran2." + 1 = ";
}
?>
