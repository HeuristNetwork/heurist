<?php
/**
* Stores Capture value in session "captcha_code"
* Returns either string or image with challenge
*
* @project     Heurist academic knowledge management system
* @package Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
* 
* @todo move controller
*/

if (session_status() !== PHP_SESSION_ACTIVE) {

    require_once 'USystem.php';

    session_name('heurist-sessionid');
    session_cache_limiter('none');

    /*
    //get session id from cookes
    if (@$_COOKIE['heurist-sessionid']) {
            session_id($_COOKIE['heurist-sessionid']);
    }
    }*/

    @session_start();
    if (!@$_COOKIE['heurist-sessionid']) {
        hserv\utilities\USystem::sessionUpdateCookies(0);
    }
}

if(@$_REQUEST['img']){ //IMAGE CAPTCHA
    $captchanumber = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxyz';// Initializing PHP variable with string
    $captcha_code = substr(str_shuffle($captchanumber), 0, 4);// Getting first 6 word after shuffle.

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
    $ran0 = random_int(0,13);
    $ran1 = random_int(1,9);
    $ran2 = random_int(1,9);
    // $captcha_code = strtolower($planets[$ran0]).($ran1+$ran2);
    $captcha_code = ($ran1+$ran2) + 1;
    $_SESSION["captcha_code"] = $captcha_code;
    // print "Answer: the word '".strtolower($planets[$ran0])."' followed by the sum of $ran1 and $ran2";
    $value = $ran1." + ".$ran2." + 1 = ";

    if(array_key_exists('json',$_REQUEST)){ //returns both session id and value
        $value = array('id'=>session_id(),'value'=>$value);
        header('Content-type: application/json;charset=UTF-8');
        print json_encode($value);
    }else{
        print $value;
    }
}
?>
