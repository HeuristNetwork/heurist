<?php
/**
* ImageThumbnail.php - Thumbnails and EXIF orientation of uploaded images (GD)
*
* The image part of the legacy upload (UploadHandler): a PNG thumbnail of at
* most 200×200 pixels, and the rotation of a photo by its EXIF orientation.
*
* @project     Heurist academic knowledge management system
* @package     Files
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Files;

/** Image helpers on GD; every method returns false/does nothing when GD cannot read the image. */
final class ImageThumbnail
{
    /** Largest width and height of a thumbnail. */
    public const SIZE = 200;

    /** Images larger than this (pixels) are not loaded (memory). */
    private const MAX_PIXELS = 40000000;

    /**
     * Write a PNG thumbnail (at most SIZE×SIZE, proportions kept).
     *
     * @return bool Whether the thumbnail was written.
     */
    public static function create(string $source, string $target, int $size = self::SIZE): bool
    {
        $image = self::load($source);
        if($image === null){ return false; }
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $size / max($width, $height));
        $newWidth = max(1, (int)round($width * $scale));
        $newHeight = max(1, (int)round($height * $scale));
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 255, 255, 255, 127));
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        $written = imagepng($thumb, $target);
        imagedestroy($image);
        imagedestroy($thumb);
        return $written;
    }

    /** Rotate/flip a JPEG by its EXIF orientation and save it (legacy auto_orient). */
    public static function autoOrient(string $path): void
    {
        if(!function_exists('exif_read_data') || !in_array(self::type($path), array(IMAGETYPE_JPEG), true)){ return; }
        $exif = @exif_read_data($path);
        $orientation = intval($exif['Orientation'] ?? 1);
        if($orientation < 2 || $orientation > 8){ return; }
        $image = self::load($path);
        if($image === null){ return; }
        if(in_array($orientation, array(2, 4, 5, 7), true)){ imageflip($image, IMG_FLIP_HORIZONTAL); }
        $angle = array(3 => 180, 4 => 180, 5 => 270, 6 => 270, 7 => 90, 8 => 90)[$orientation] ?? 0;
        if($angle !== 0){
            $rotated = imagerotate($image, $angle, 0);
            imagedestroy($image);
            $image = $rotated;
        }
        if($image !== false){
            imagejpeg($image, $path, 95);
            imagedestroy($image);
        }
    }

    /** GD image of a file, or null (not an image GD reads, or too large). */
    private static function load(string $path)
    {
        if(!function_exists('imagecreatefromstring') || !is_file($path)){ return null; }
        $info = @getimagesize($path);
        if(!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS){ return null; }
        $image = @imagecreatefromstring((string)file_get_contents($path));
        return $image === false ? null : $image;
    }

    private static function type(string $path): int
    {
        $info = @getimagesize($path);
        return $info ? intval($info[2]) : 0;
    }
}
