<?php
/**
* UImage.php - Class UImage
*
* Image manipulation utilities.
*
* @project     Heurist academic knowledge management system
* @package Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
namespace hserv\utilities;
use hserv\utilities\USanitize;
use hserv\utilities\USystem;

//use Screen\Capture; //for micorweber

/**
* Class UImage
* 
* Image manipulation utilities.
*
* Provides static methods for various image operations including:
* - Creating images from text strings.
* - Generating screenshots of URLs.
* - Fetching remote images.
* - Loading images from files with memory safety checks.
* - Modifying image colors, particularly for icons.
* - Determining image type and orientation using EXIF data.
* - Resizing and scaling images (thumbnails) using GD or Imagick.
* - Generating thumbnails for PDF files and IIIF manifests.
* - Analyzing images to find the prevailing background color.
*
*/
class UImage {

    /**
     * Creates an image with the given text rendered on it.
     * The text is word-wrapped and centered on a 100x100 white background with a simple border.
     * Font size is adjusted if the text is too long.
     *
     * @param string $desc The text to be inserted into the image.
     * @return \GdImage|false The GD image resource on success (current implementation always returns a resource, never false).
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
     * Makes a screenshot of a given URL.
     * Uses either a configured WEBSITE_THUMBNAIL_SERVICE or Google PageSpeed Insights API.
     * Saves the screenshot to a temporary file.
     *
     * @param string $siteURL The URL to take a screenshot of.
     * @return object|array An object with file details (original_name, name, fullpath, size, type) on success,
     *                      or an array with an 'error' key on failure.
     */
    public static function makeURLScreenshot($siteURL){

        if(!filter_var($siteURL, FILTER_VALIDATE_URL)){
            return array('error'=>'URL to generate snapshot '.$siteURL.' is not valid');
        }

        //$remote_path =  str_replace("[URL]", $sURL, WEBSITE_THUMBNAIL_SERVICE);
        $heurist_path = tempnam(HEURIST_SCRATCH_DIR, "_temp_");


        if(defined('WEBSITE_THUMBNAIL_SERVICE') && WEBSITE_THUMBNAIL_SERVICE!=''){

            $remote_path =  str_replace("[URL]", $siteURL, WEBSITE_THUMBNAIL_SERVICE);
            $filesize = saveURLasFile($remote_path, $heurist_path);//save url screenshot in tep file

            //check the dimension of returned thumbanil in case it is less than 50 - consider it as error
            if(strpos($remote_path, substr(WEBSITE_THUMBNAIL_SERVICE,0,24))==0){

                $image_info = getimagesize($heurist_path);
                if($image_info[1]<50){
                    //remove temp file
                    unlink($heurist_path);
                    return array('error'=>'Thumbnail generator service can\'t create the image for specified URL');
                }
            }

        }else{

            //call Google PageSpeed Insights API
            $googlePagespeedData = file_get_contents( //loadRemoteURLContent
                'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url='.$siteURL.'&screenshot=true');

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
        }

        if(file_exists($heurist_path)){

            $filesize = getFileSize($heurist_path); //UFile

            $file = new \stdClass();
            $file->original_name = 'snapshot.jpg';
            $file->name = $heurist_path; //pathinfo($heurist_path, PATHINFO_BASENAME);//name with ext
            $file->fullpath = $heurist_path;
            $file->size = $filesize;
            $file->type = 'jpg';

            return $file;

        }else{
            return array('error'=>'Cannot download image from thumbnail generator service. '.$siteURL.' to '.$heurist_path);
        }

    }

    /**
     * Downloads an image from a given URL.
     * Optionally extracts EXIF orientation if the $orientation parameter is passed by reference.
     *
     * @param string $remote_url The URL of the image to download.
     * @param int|null &$orientation Optional. Passed by reference. If provided, will be populated with the image's EXIF orientation code (0 if none or error).
     * @return \GdImage|false The GD image resource on success, or false on failure (e.g., cURL error, invalid image data).
     */
    public static function getRemoteImage($remote_url, &$orientation=null){  //get_remote_image

        $img = null;

        $data = loadRemoteURLContent($remote_url, false);//get_remote_image as raw data
        if($data){

            if(isset($orientation)){
                //save into file
                $_tmp = tempnam(HEURIST_SCRATCHSPACE_DIR, 'img');
                //imagejpeg($data, $_tmp);
                file_put_contents($_tmp,  $data);
                $orientation = UImage::getImageOrientation($_tmp);
                unlink($_tmp);
            }

            try{
                $img = imagecreatefromstring($data);
            }catch(\Exception  $e){
                $img = false;
            }
        }else{
            $img = false;
        }

        return $img;
    }


    /**
     * Returns a GD image resource from a given image file.
     * Determines the image type and uses a memory-safe loading function.
     *
     * @param string $filename The path to the image file.
     * @return \GdImage|false|null A GD image resource on success, false if loading fails or type is unsupported,
     *                             null if the file does not exist or is not a recognized image type.
     */
    public static function getImageFromFile($filename){
        $mimeExt = UImage::getImageType($filename);
        $image = null;
        if($mimeExt){
            $image = UImage::safeLoadImage($filename, $mimeExt);
        }
        return $image;
    }

    /**
     * Changes black color in a source PNG image to a new specified color,
     * optionally adding a circular border and background. Saves or outputs the modified image.
     * Primarily used for Heurist icons.
     *
     * @param string $filename Path to the source PNG image file.
     * @param string|null $filename_new Path to save the new image. If null, outputs image directly to browser.
     * @param string|array|null $color_new The new color to replace black. Can be hex string (e.g., "#FF0000") or RGB array. Defaults to red if null.
     * @param string|array|null $circle_color Color for the circle border. Null for no border.
     * @param string|array|null $bg_circle_color Color for the filled circle background. Null for no background fill.
     * @return void
     */
    public static function changeImageColor($filename, $filename_new, $color_new, $circle_color, $bg_circle_color){

        if(file_exists($filename)){


            $color_new = UImage::_parseColor($color_new);
            $circle_color = UImage::_parseColor($circle_color);
            $bg_circle_color = UImage::_parseColor($bg_circle_color);

            // load icon
            $img_icon = @imagecreatefrompng($filename);

            if($img_icon===false) {return;}

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

                $img = imagecreatetruecolor(25, 25);//truecolor image 25x25 pix
                imagealphablending( $img, false );
                imagesavealpha( $img, true );

                //fill the background color
                //$bg = imagecolorallocate($img, 200, 200, 200);
                //imagecolortransparent($img, $bg);
                // make the background transparent
                $bg = imagecolorallocatealpha($img, 200, 200, 200, 127);

                //draw transparent rectangle
                imagefilledrectangle($img, 0, 0, 25, 25, $bg);//fill bg rectangle

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
                    imagepng($img, $filename_new);//save to file
                }else{
                    header("Content-type: image/png");
                    imagepng($img);
                }
                imagedestroy($img);
                //readfile($filename2);
            }else{
                // output
                if($filename_new){
                    imagepng($img_icon, $filename_new);//save to file
                }else{
                    header("Content-type: image/png");
                    imagepng($img_icon);
                }
            }

            imagedestroy($img_icon);

        }
    }

    /**
     * Determines the image type (extension) of a file using EXIF data if available,
     * otherwise falls back to pathinfo.
     *
     * @param string $filename The path to the image file.
     * @return string|null The image type extension (e.g., 'jpg', 'png', 'gif') or null if not determinable or file doesn't exist.
     */
    public static function getImageType($filename){

        $mimeExt = null;

        if(file_exists($filename)){

            if (function_exists('exif_imagetype')) {
                switch(@exif_imagetype($filename)){
                    case IMAGETYPE_JPEG:
                        $mimeExt = 'jpg';
                        break;
                    case IMAGETYPE_PNG:
                        $mimeExt = 'png';
                        break;
                    case IMAGETYPE_GIF:
                        $mimeExt = 'gif';
                        break;
                    default;
                }
            }else{
                $path_parts = pathinfo($filename);
                switch($path_parts['extension']) {
                    case 'jpeg':
                    case 'jfif':
                    case 'jpg':
                    case 'jpe':
                        $mimeExt = 'jpg';
                        break;
                    case 'gif':
                        $mimeExt = 'gif';
                        break;
                    case 'png':
                        $mimeExt = 'png';
                        break;
                    default;
                }
            }
        }

        return  $mimeExt;
    }


    /**
     * Retrieves the EXIF orientation code from an image file.
     *
     * @param string $file_path The path to the image file.
     * @return int The EXIF orientation code (1-8). Returns 0 if no orientation data,
     *             EXIF extension not available, or file error.
     */
    public static function getImageOrientation($file_path){

        if (!function_exists('exif_read_data')) {
            return 0;
        }
        $exif = @exif_read_data($file_path);
        if ($exif === false) {
            return 0;
        }
        $orientation = (int)@$exif['Orientation'];
        if ($orientation < 2 || $orientation > 8) {
            return 0;
        }else{
            return $orientation;
        }

    }

    /**
     * Estimates memory needed to load an image and checks if it's within allowed limits.
     *
     * @param string $filename The path to the image file.
     * @param string $mimeExt The MIME type or extension of the image (e.g., 'jpg', 'image/png').
     * @return string|null An error message string if memory check fails (memory needed exceeds limits),
     *                     or null if memory requirements are acceptable or type is not checked.
     */
    public static function checkMemoryForImage($filename, $mimeExt){

        $errorMsg = null;

        //if img check memory to be allocated
        switch($mimeExt) {
            case 'image/jpeg':
            case 'jpeg':
            case 'jfif':
            case 'jpg':
            case 'jpe':
            case 'image/gif':
            case 'gif':
            case 'image/png':
            case 'png':

                $imageInfo = getimagesize($filename);
                if(is_array($imageInfo)){
                    if(array_key_exists('channels', $imageInfo) && array_key_exists('bits', $imageInfo)){
                        $memoryNeeded = round(($imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $imageInfo['channels'] / 8 + Pow(2,16)) * 1.65);
                    }else{ //width x height
                        $memoryNeeded = round($imageInfo[0] * $imageInfo[1]*3);
                    }

                    $error_msg = USystem::isMemoryAllowed( $memoryNeeded );
                    if($error_msg!==true){
                        $errorMsg = $error_msg;
                    }
                }
                break;
            default:
                break;
        }

        return $errorMsg;

    }

    /**
     * Safely loads an image from a file into a GD image resource.
     * Handles potential errors during image creation from various formats and sets up an error handler
     * to catch GD library warnings/errors, sending an email to admin if issues occur.
     *
     * @param string $filename The path to the image file.
     * @param string $mimeExt The MIME type or extension of the image (e.g., 'jpg', 'image/png', 'gif').
     * @return \GdImage|false A GD image resource on success, or false if loading fails or the image type is unsupported.
     */
    public static function safeLoadImage($filename, $mimeExt){
        
        global $system;

        $img = null;

        $errline_prev = 0;

        set_error_handler(function($errno, $errstr, $errfile, $errline=null, array $errcontext=null) {
            global $errline_prev, $filename, $file;

            //it may report error several times with different messages - send for the first one
            if($errline_prev!=$errline){

                $errline_prev=$errline;
                //database, record ID and name of bad image
                sendEmail(HEURIST_MAIL_TO_ADMIN, 'Cannot load image file. DB:'.$system->dbname(),
                    'File :'.$filename.' is corrupted. System message: '.$errstr);
                //ID#'.$file['ulf_ID'].'

            }
            return false;
        });//, E_WARNING);

        switch($mimeExt) {
            case 'image/jpeg':
            case 'jpeg':
            case 'jfif':
            case 'jpg':
            case 'jpe':
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
                $img = false; //not image
                break;
        }

        restore_error_handler();

        return $img;
    }

    /**
     * Creates a scaled thumbnail for a given image file.
     * Uses Imagick if available, otherwise falls back to GD.
     * If thumbnail creation fails and $create_error_thumb is true, an error image is generated.
     *
     * @param string $filename Path to the source image file.
     * @param string $scaled_file Path to save the scaled thumbnail.
     * @param int $max_width Optional. Maximum width of the thumbnail. Defaults to 200.
     * @param int $max_height Optional. Maximum height of the thumbnail. Defaults to 200.
     * @param bool $create_error_thumb Optional. If true, creates an image with an error message if scaling fails. Defaults to true.
     * @param string $force_type Optional. Force output type ('png' or 'jpg'). Defaults to 'png'.
     * @return bool|string True on success, or an error message string on failure.
     */
    public static function createScaledImageFile($filename, $scaled_file, $max_width = 200, $max_height = 200, $create_error_thumb=true, $force_type='png'){

        $mimeExt = UImage::getImageType($filename);

        if(!$mimeExt){
            return '';
        }

        $errorMsg = UImage::checkMemoryForImage($filename, $mimeExt);

        if(!$errorMsg){

            if(extension_loaded('imagick')){

                $res = UImage::_resizeImageImagic($filename, $scaled_file, $max_width, $max_height, $force_type);

                if($res!==true || !file_exists($scaled_file)){
                    $errorMsg = 'Cannot resize image.';
                }
            }else{

                $img = UImage::safeLoadImage($filename, $mimeExt);
                if($img){

                    UImage::_resizeImageGD($img, $scaled_file, $max_width, $max_height);

                    if(!file_exists($scaled_file)){
                        $errorMsg = 'Cannot resize image';
                    }
                }else{
                    $errorMsg = 'Cannot load image file';
                }
            }

        }

        if($errorMsg && $create_error_thumb){

            $img = UImage::createFromString($errorMsg);
            imagepng($img, $scaled_file);
            imagedestroy($img);
            return $errorMsg;

        }

        return file_exists($scaled_file)?true:$errorMsg;
    }


    /**
     * Resizes a given GD image resource to specified dimensions and saves it as a PNG file.
     * Handles EXIF orientation if provided.
     * Used primarily by recordFile.php's fileCreateThumbnail function.
     *
     * @param \GdImage $img The source GD image resource.
     * @param string|null $thumbnail_file Optional. Path to save the resized PNG image. If null, the image is created but not saved (and then destroyed).
     * @param int $x Optional. Target width for the resized image. Defaults to 200.
     * @param int $y Optional. Target height for the resized image. Defaults to 200.
     * @param int $orientation Optional. EXIF orientation code (1-8) to apply before resizing. Defaults to 0 (no orientation change).
     * @return bool True on successful resizing and saving (if $thumbnail_file is provided), false otherwise (though current path always returns true).
     */
    public static function resizeImage($img, $thumbnail_file=null, $x = 200, $y = 200, $orientation=0){

        if($orientation>0){
            $img = UImage::gd_orient_image($img, $orientation);
        }

        $no_enlarge = false;
        // calculate image size
        // note - we never change the aspect ratio of the image!
        $orig_x = imagesx($img);
        $orig_y = imagesy($img);

        $rx = $x / $orig_x;
        $ry = $y / $orig_y;

        $scale = $rx ? ($ry ? min($rx, $ry) : $rx) : $ry;

        if ($no_enlarge  &&  $scale > 1) {
            $scale = 1;
        }

        $new_x = ceil($orig_x * $scale);
        $new_y = ceil($orig_y * $scale);

        $img_resized = imagecreatetruecolor($new_x, $new_y) or die;

        // Handle transparency
        imagecolortransparent($img_resized, imagecolorallocate($img_resized, 0, 0, 0));
        imagealphablending($img_resized, false);
        imagesavealpha($img_resized, true);

        imagecopyresampled($img_resized, $img, 0, 0, 0, 0, $new_x, $new_y, $orig_x, $orig_y)  or die;

        if ($thumbnail_file) {
            $resized_file = $thumbnail_file;
        }else{
            //?????
            $resized_file = tempnam(HEURIST_SCRATCHSPACE_DIR, 'resized');
        }

        imagepng($img_resized, $resized_file);//save into file
        imagedestroy($img);
        imagedestroy($img_resized);

        if($thumbnail_file==null){
            //remove themp file
            unlink($resized_file);
        }

        return true;
    }

    private static function gd_imageflip($image, $mode) {
        if (function_exists('imageflip')) {
            return imageflip($image, $mode);
        }
        $new_width = $src_width = imagesx($image);
        $new_height = $src_height = imagesy($image);
        $new_img = imagecreatetruecolor($new_width, $new_height);
        $src_x = 0;
        $src_y = 0;
        switch ($mode) {
            case '1': // flip on the horizontal axis
                $src_y = $new_height - 1;
                $src_height = -$new_height;
                break;
            case '2': // flip on the vertical axis
                $src_x  = $new_width - 1;
                $src_width = -$new_width;
                break;
            case '3': // flip on both axes
                $src_y = $new_height - 1;
                $src_height = -$new_height;
                $src_x  = $new_width - 1;
                $src_width = -$new_width;
                break;
            default:
                return $image;
        }
        imagecopyresampled(
            $new_img,
            $image,
            0,
            0,
            $src_x,
            $src_y,
            $new_width,
            $new_height,
            $src_width,
            $src_height
        );
        return $new_img;
    }

    private static function gd_orient_image($src_img, $orientation) {
        if ($orientation < 2 || $orientation > 8) {
            return $src_img;
        }
        switch ($orientation) {
            case 2:
                $new_img = UImage::gd_imageflip(
                    $src_img,
                    defined('IMG_FLIP_VERTICAL') ? IMG_FLIP_VERTICAL : 2
                );
                break;
            case 3:
                $new_img = imagerotate($src_img, 180, 0);
                break;
            case 4:
                $new_img = UImage::gd_imageflip(
                    $src_img,
                    defined('IMG_FLIP_HORIZONTAL') ? IMG_FLIP_HORIZONTAL : 1
                );
                break;
            case 5:
                $tmp_img = UImage::gd_imageflip(
                    $src_img,
                    defined('IMG_FLIP_HORIZONTAL') ? IMG_FLIP_HORIZONTAL : 1
                );
                $new_img = imagerotate($tmp_img, 270, 0);
                imagedestroy($tmp_img);
                break;
            case 6:
                $new_img = imagerotate($src_img, 270, 0);
                break;
            case 7:
                $tmp_img = UImage::gd_imageflip(
                    $src_img,
                    defined('IMG_FLIP_VERTICAL') ? IMG_FLIP_VERTICAL : 2
                );
                $new_img = imagerotate($tmp_img, 270, 0);
                imagedestroy($tmp_img);
                break;
            case 8:
                $new_img = imagerotate($src_img, 90, 0);
                break;
            default:
                return false;
        }

        return $new_img;
    }

    
    //
    //
    //
    private static function composeThumbnailIIIF($image_url, $width, $height)
    {
        $x = intval($width);
        $y = intval($height);
        if(!($x>0)){
            $x = 200;
        }
        if(!($y>0)){
            $y = 200;
        }

        $rx = 200 / $x;
        $ry = 200 / $y;

        $scale = $rx ? ($ry ? min($rx, $ry) : $rx) : $ry;

        if ($scale > 1) { //no enlarge
            $scale = 1;
        }

        $new_x = ceil($x * $scale);
        $new_y = ceil($y * $scale);

        //https://gallica.bnf.fr/iiif/ark:/12148/bpt6k9604118j/f25/full/90,120/0/default.jpg
        //https://fragmentarium.ms/metadata/iiif/F-hsd6/manifest.json  or info.json
        //https://purl.stanford.edu/sn904cj3429/iiif/manifest
        //https://fragmentarium.ms:443/loris/F-hsd6/fol_2r.jp2/full/full/0/default.jpg

        if(strpos($image_url,'/full/full/')>0){
            $thumb_url = str_replace('/full/full/', '/full/'.$new_x.','.$new_y.'/', $image_url);
        }else{
            $thumb_url = $image_url.'/full/'.$new_x.','.$new_y.'/0/default.jpg';
        }

        return $thumb_url;
    }
    
    
    /**
     * Downloads or constructs a thumbnail URL from a IIIF manifest or IIIF image URL,
     * then downloads and saves it as a scaled image file.
     *
     * @param string $iiif_url The URL of the IIIF manifest or IIIF image info.json.
     * @param array|null $iiif_manifest Optional. A pre-parsed IIIF manifest array. If null, it will be fetched from $iiif_url.
     * @param string $thumbnail_file Path to save the generated thumbnail image.
     * @return string|null The URL of the thumbnail image used, or null if processing fails or no suitable thumbnail is found.
     */
    public static function getIiifThumbnail( $iiif_url, $iiif_manifest, $thumbnail_file ){

        $thumbUrl = null;
        
        if($iiif_manifest==null){
            $iiif_manifest = loadRemoteURLContent($iiif_url);//check that json is iiif manifest
            $iiif_manifest = json_decode($iiif_manifest, true);
        }

        //verify that this is valid iiif manifest
        if($iiif_manifest!==false && is_array($iiif_manifest))
        {
            $type = array_key_exists('@type', $iiif_manifest) ? $iiif_manifest['@type'] : '';
            $type = array_key_exists('type', $iiif_manifest) ? $iiif_manifest['type'] : $type;
            $type = strtolower($type);

            if($type === 'sc:manifest' || //v2
               $type === 'manifest'){ //v3

                if(@$iiif_manifest['thumbnail']){

                    if(@$iiif_manifest['thumbnail']['@id']){  //v2
                        $thumbUrl = @$iiif_manifest['thumbnail']['@id'];
                    }elseif(@$iiif_manifest['thumbnail']['id']){  //v3
                        $thumbUrl = @$iiif_manifest['thumbnail']['id'];
                    }
                }else{
                    //sequences -> canvases[0] -> images[0] -> resource -> @id or service -> @id

                    $thumb_url = @$iiif_manifest['sequences'][0]['canvases'][0];
                    if($thumb_url){

                        if(@$thumb_url['thumbnail']['@id']){
                            $thumbUrl = @$thumb_url['thumbnail']['@id'];
                        }else{
                            if(@$thumb_url['images'][0]['resource']['service']['@id']){
                                $image_url = $thumb_url['images'][0]['resource']['service']['@id'];
                            }else{
                                $image_url = @$thumb_url['images'][0]['resource']['@id'];
                            }

                            if($image_url!=null){
                                $thumbUrl = UImage::composeThumbnailIIIF(
                                    $image_url,
                                    @$thumb_url['images'][0]['resource']['width'],
                                    @$thumb_url['images'][0]['resource']['height']
                                );
                            }
                        }
                    }else{
                        // ver 3, use first available item as thumbnail

                        $thumb_url = @$iiif_manifest['items'][0];
                        if($thumb_url && @$thumb_url['id']){

                            $image_url = $thumb_url['id'];

                            $thumbUrl = UImage::composeThumbnailIIIF($image_url, @$thumb_url['width'], @$thumb_url['height']);
                        }
                    }
                }

            }elseif(@$iiif_manifest['@context'] && (@$iiif_manifest['@id'] || @$iiif_manifest['id'])
            && substr($iiif_url, 0, -9) == 'info.json' )
            {   //IIIF image

                //create url for thumbnail
                //remove info.json
                $thumb_url = substr($iiif_url, 0, -9).'full/full/0/default.jpg';
                $thumbUrl = UImage::composeThumbnailIIIF($thumb_url,
                    @$iiif_manifest['width'],
                    @$iiif_manifest['height']);
            }

        }
        
        //download
        if($thumbUrl && $thumbnail_file){
            $temp_path = tempnam(HEURIST_SCRATCH_DIR, "_temp_");
            if(saveURLasFile($thumbUrl, $temp_path)){ //save to temp in scratch folder
                UImage::createScaledImageFile($temp_path, $thumbnail_file);//create thumbnail for iiif image
                unlink($temp_path);
            }
        }

        return $thumbUrl;
        
    }

    /**
     * Creates a thumbnail image from the first page of a PDF file.
     * Uses ImageMagick's `convert` command if Imagick extension is not loaded, otherwise uses Imagick.
     *
     * @param string $filename Path to the source PDF file.
     * @param string $thumbnail_file Path to save the generated thumbnail image (typically PNG).
     * @return bool True on success, false on failure.
     */
    public static function getPdfThumbnail( $filename, $thumbnail_file ){
        global $system;

        if(!extension_loaded('imagick')){

            $cmd = 'convert -thumbnail x200 -flatten ';//-background white -alpha remove
            $cmd .= ' '.escapeshellarg($filename.'[0]');
            $cmd .= ' '.escapeshellarg($thumbnail_file);
            exec($cmd, $output, $error);

            if ($error) {
                USanitize::errorLog('ERROR on pdf thumbnail creation: '.$filename.'  '.$cmd.'   '.implode('\n', $output));
                return false;
            }

        }else{
            //Imagic
            try {

                $im =  new \Imagick($filename.'[0]');
                $im->setImageFormat('png');
                $im->thumbnailImage(200,200);

                if(file_exists($thumbnail_file)){
                    unlink($thumbnail_file);
                }
                $im->writeImage($thumbnail_file);

            } catch(\ImagickException $e) {
                USanitize::errorLog($e . ', From Database: ' . $system->dbname());
                return false;
            }

        }
        return true;
        //$resized = file_get_contents($thumbnail_file);
        //return $resized;
    }

    /**
     * Finds the prevailing background color of an image by scaling it to 1x1 pixel.
     * Requires PHP version >5.5 for `imagescale`.
     * NOTE: This method is marked as NOT USED in the original source.
     *
     * @param string $filename Path to the image file.
     * @return string A hex color string (e.g., "#RRGGBB"), or "#FFFFFF" (white) if image processing fails.
     */
    public static function getPrevailBackgroundColor2($filename){

        $image = UImage::getImageFromFile($filename);
        if($image){

            $scaled = imagescale($image, 1, 1, IMG_BICUBIC);//since v5.5
            $index = imagecolorat($scaled, 0, 0);
            $rgb = imagecolorsforindex($scaled, $index);
            /* $red = round(round(($rgb['red'] / 0x33)) * 0x33);
            $green = round(round(($rgb['green'] / 0x33)) * 0x33);
            $blue = round(round(($rgb['blue'] / 0x33)) * 0x33);
            return sprintf('#%02X%02X%02X', $red, $green, $blue);
            */
            return sprintf('#%02X%02X%02X', $rgb['red'], $rgb['green'], $rgb['blue']);
        }else{
            return '#FFFFFF';
        }

    }


    /**
     * Finds the prevailing background color of an image using a histogram method.
     * It analyzes the color distribution to determine the most dominant color.
     *
     * @param string $filename Path to the image file.
     * @return string A hex color string (e.g., "#RRGGBB") representing the prevailing color,
     *                or "#FFFFFF" (white) if image processing fails.
     */
    public static function getPrevailBackgroundColor($filename){
        // histogram options

        $maxheight = 300;
        $barwidth = 2;

        $image = UImage::getImageFromFile($filename);
        if($image){

            $im = $image;

            $imgw = imagesx($im);
            $imgh = imagesy($im);

            // n = total number or pixels

            $n = $imgw*$imgh;

            $histo = array();
            for ($i=0; $i<256; $i++) {$histo[]=0;}

            for ($i=0; $i<$imgw; $i++)
            {
                for ($j=0; $j<$imgh; $j++)
                {

                    // get the rgb value for current pixel

                    $rgb = imagecolorat($im, $i, $j);

                    // extract each value for r, g, b

                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;

                    // get the Value from the RGB value

                    $V = round(($r + $g + $b) / 3);

                    // add the point to the histogram
                    $histo[$V] += $V / $n;
                    $histo_color[$V] = UImage::_rgb2hex(array($r,$g,$b));
                }
            }

            // find the maximum in the histogram in order to display a normated graph

            $max = 0;
            for ($i=0; $i<256; $i++)
            {
                if ($histo[$i] > $max)
                {
                    $max = $histo[$i];
                }
            }

            $key = array_search ($max, $histo);
            $col = $histo_color[$key];
            return $col;
            /*
            echo "<div style='width: ".(256*$barwidth)."px; border: 1px solid'>";
            for ($i=0; $i<255; $i++)
            {
            $val += $histo[$i];

            $h = ( $histo[$i]/$max )*$maxheight;

            echo "<img src=\"img.gif\" width=\"".$barwidth."\"
            height=\"".$h."\" border=\"0\">";
            }
            echo DIV_E;
            */
        }else{
            return '#FFFFFF';
        }
    }

    /**
     * Resizes an image using the Imagick extension and saves it.
     *
     * @param string $filename Path to the source image file.
     * @param string $scaled_file Path to save the scaled image.
     * @param int $max_width Optional. Maximum width of the scaled image. Defaults to 200.
     * @param int $max_height Optional. Maximum height of the scaled image. Defaults to 200.
     * @param string $force_type Optional. Force output image type ('png' or 'jpg'). Defaults to 'png'.
     * @return bool|string True on success, or an error message string on ImagickException.
     */
    private static function _resizeImageImagic($filename, $scaled_file, $max_width = 200, $max_height = 200, $force_type='png'){

        try{
            $image = new \Imagick($filename);
            $dims = ['height' => $image->getImageHeight(), 'width' => $image->getImageWidth()];

            // rescale if either dimension is greater than 1000 pixels
            if($dims['height'] > $max_height || $dims['width'] > $max_width){

                // scale by the bigger of height or width
                $scaleHeight = $dims['height'] > $dims['width'] ? $max_width : 0;
                $scaleWidth = $dims['width'] > $dims['height'] ? $max_height : 0;

                $image->scaleImage($scaleWidth, $scaleHeight);// scale image
            }

            // force jpeg output
            if($force_type=='png'){
                $image->setImageType('png');
            }
            if($force_type=='jpg'){
                $image->setImageType('jpeg');

                $image->setImageCompression(\Imagick::COMPRESSION_JPEG);
                $image->setImageCompressionQuality(75);
            }

            $success = $image->writeImage($scaled_file);

            $image->destroy();

            return $success;

        }catch(\ImagickException $e){
            return $e->getMessage();
        }
    }


    /**
     * Resizes an image using GD library functions and saves it.
     * Handles transparency for PNGs.
     *
     * @param \GdImage $src_img The source GD image resource.
     * @param string $scaled_file Path to save the scaled image.
     * @param int $max_width Optional. Maximum width of the scaled image. Defaults to 200.
     * @param int $max_height Optional. Maximum height of the scaled image. Defaults to 200.
     * @param string $scale_type Optional. Output image type ('png' or 'jpg'). Defaults to 'png'.
     * @return bool True on success, false on failure (e.g., if imagecreatetruecolor is not available or saving fails).
     */
    private static function _resizeImageGD($src_img, $scaled_file, $max_width = 200, $max_height = 200, $scale_type = 'png'){

        if (!function_exists('imagecreatetruecolor')) {
            USanitize::errorLog('Function not found: imagecreatetruecolor');
            return false;
        }

        $write_func = 'imagepng';
        $image_quality = $scale_type == 'jpg' ? 80 : 9;
        /*
        $image_oriented = false;
        if (!empty($options['auto_orient']) && $this->gd_orient_image(
        $file_path,
        $src_img
        )) {
        $image_oriented = true;
        $src_img = $this->gd_get_image_object(
        $file_path,
        $src_func
        );
        }*/
        $img_width = imagesx($src_img);
        $img_height = imagesy($src_img);
        $scale = min(
            $max_width / $img_width,
            $max_height / $img_height
        );
        if ($scale >= 1) {

            //save into file
            if(!$scale_type || $scale_type == 'png'){

                // retain transparent backgrounds
                imagealphablending($src_img, false);
                imagesavealpha($src_img, true);

                imagepng($src_img, $scaled_file);
            }elseif($scale_type == 'jpg'){
                imagejpeg($src_img, $scaled_file);
            }
            imagedestroy($src_img);
            return true;
        }

        $new_width = $img_width * $scale;
        $new_height = $img_height * $scale;
        $dst_x = 0;
        $dst_y = 0;
        $new_img = imagecreatetruecolor($new_width, $new_height);

        // Handle transparency
        imagecolortransparent($new_img, imagecolorallocate($new_img, 0, 0, 0));
        imagealphablending($new_img, false);
        imagesavealpha($new_img, true);

        $success = imagecopyresampled(
            $new_img,
            $src_img,
            $dst_x,
            $dst_y,
            0,
            0,
            $new_width,
            $new_height,
            $img_width,
            $img_height
        );

        if($success){

            if(!$scale_type || $scale_type == 'png'){
                $success = imagepng($new_img, $scaled_file, $image_quality);
            }elseif($scale_type == 'jpg'){
                $success = imagejpeg($new_img, $scaled_file, $image_quality);
            }
        }

        imagedestroy($src_img);
        if($new_img) {imagedestroy($new_img);}

        return $success;
    }


    /**
     * Parses a color string (hex or rgb) into an RGB array.
     *
     * @param string|null $param_color The color string (e.g., "#FF0000", "rgb(255,0,0)") or null.
     * @return array|null An array [R, G, B] or null if input is null. Defaults to red if parsing fails on non-null input.
     */
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
     * Converts an RGB color array to a hex color string.
     *
     * @param array $rgb An array with three integer elements [R, G, B].
     * @return string The hex color string (e.g., "#RRGGBB").
     */
    private static function _rgb2hex($rgb) {
        $hex = "#";
        $hex .= str_pad(dechex($rgb[0]), 2, '0', STR_PAD_LEFT);
        $hex .= str_pad(dechex($rgb[1]), 2, '0', STR_PAD_LEFT);
        $hex .= str_pad(dechex($rgb[2]), 2, '0', STR_PAD_LEFT);

        return $hex; // returns the hex value including the number sign (#)
    }


}
