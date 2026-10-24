<?php
/**
* EntityImageStorage.php - The one image of a user, group (later record type, term...) on disk
*
* Plan 20, Phase 4d (§10.13, agreed 2026-10-23): the legacy storage model stays for now,
* behind this class: one image per entity in HEURIST_FILESTORE/<db>/entity/<legacy table>/
* as <id>.<ext>, its thumbnail in thumbnail/<id>.png (as legacy DbEntityBase::renameEntityImage
* and fileGet.php?entity=...). No other srv code names the legacy folders. With the switch at
* the end of plan 20 an image becomes a registered file referred to by an Image field.
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

use RuntimeException;

/** Entity images in the legacy entity folders. */
final class EntityImageStorage
{
    /** Entity type => legacy folder (the legacy table name). */
    public const FOLDERS = array('user' => 'sysUsers', 'group' => 'sysGroups');

    /** Image extensions, in the order legacy looks for them. */
    public const EXTENSIONS = array('png', 'jpg', 'svg', 'jpeg', 'jpe', 'jfif', 'gif', 'webp');

    private string $root;

    /** @param string $entityRoot HEURIST_FILESTORE/<db>/entity/ */
    public function __construct(string $entityRoot)
    {
        $this->root = rtrim(str_replace('\\', '/', $entityRoot), '/').'/';
    }

    /** Whether a type has images. */
    public static function supports(string $type): bool
    {
        return isset(self::FOLDERS[$type]);
    }

    /**
     * The file of an entity's image or thumbnail; null when there is none.
     *
     * @param string $version 'full' or 'thumbnail'
     */
    public function path(string $type, int $id, string $version = 'full'): ?string
    {
        $folder = $this->folder($type, $version);
        if($id < 1){ return null; }
        foreach(self::EXTENSIONS as $extension){
            if(is_file($folder.$id.'.'.$extension)){ return $folder.$id.'.'.$extension; }
        }
        return null;
    }

    /**
     * Put a new image (a finished upload): the former image and thumbnail go; a thumbnail
     * is made of a raster image.
     *
     * @param string $source File to move (removed).
     * @param string $extension Its extension (one of EXTENSIONS).
     * @throws RuntimeException When the file is not an image or cannot be placed.
     */
    public function place(string $type, int $id, string $source, string $extension): void
    {
        $extension = strtolower($extension);
        if(!in_array($extension, self::EXTENSIONS, true)){
            throw new RuntimeException('An image is one of '.implode(', ', self::EXTENSIONS));
        }
        $this->remove($type, $id);
        $folder = $this->folder($type, 'full');
        $thumbnails = $this->folder($type, 'thumbnail');
        foreach(array($folder, $thumbnails) as $path){
            if(!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)){ throw new RuntimeException('Cannot create '.$path); }
        }
        $target = $folder.$id.'.'.$extension;
        if(!@rename($source, $target)){
            if(!@copy($source, $target)){ throw new RuntimeException('The image cannot be stored'); }
            @unlink($source);
        }
        if($extension === 'svg'){
            @copy($target, $thumbnails.$id.'.svg');
        }else{
            ImageThumbnail::create($target, $thumbnails.$id.'.png');
        }
    }

    /** Remove the image and the thumbnail of an entity. */
    public function remove(string $type, int $id): void
    {
        foreach(array('full', 'thumbnail') as $version){
            $folder = $this->folder($type, $version);
            foreach(self::EXTENSIONS as $extension){
                if(is_file($folder.$id.'.'.$extension)){ @unlink($folder.$id.'.'.$extension); }
            }
        }
    }

    /**
     * Legacy URL of the image (fileGet.php, as the legacy UI shows it).
     *
     * @param string $version 'full' or 'thumbnail'
     */
    public static function url(string $baseUrl, string $database, string $type, int $id, string $version = 'thumbnail'): string
    {
        return rtrim($baseUrl, '/').'/hserv/controller/fileGet.php?db='.rawurlencode($database)
            .'&entity='.rawurlencode(self::FOLDERS[$type] ?? '').'&id='.$id.'&version='.($version === 'full' ? 'full' : 'thumb');
    }

    /** @throws RuntimeException For a type without images. */
    private function folder(string $type, string $version): string
    {
        if(!self::supports($type)){ throw new RuntimeException('No images for '.$type); }
        return $this->root.self::FOLDERS[$type].'/'.($version === 'thumbnail' ? 'thumbnail/' : '');
    }
}
