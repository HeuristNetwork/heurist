<?php
/**
* ReportTemplateStore.php - Smarty template files
*
* Lists, reads, writes and deletes the .tpl files of one database. Only plain
* file names inside the template folder are accepted. Files starting with "_"
* are temporary files of the engine and are never listed.
*
* @project     Heurist academic knowledge management system
* @package     Reports
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports;

use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;

/** File access to the smarty-templates folder. */
final class ReportTemplateStore
{
    private string $directory;

    /** @param string $directory Absolute path of the smarty-templates folder. */
    public function __construct(string $directory)
    {
        $this->directory = $directory === '' ? '' : rtrim($directory, '/\\').'/';
    }

    /**
     * Return the template file names, sorted case-insensitively.
     *
     * @return array<int,array{file:string,size:int,modified:string}>
     */
    public function listFiles(): array
    {
        if($this->directory === '' || !is_dir($this->directory)){ return array(); }
        $files = array();
        foreach(scandir($this->directory) ?: array() as $name){
            if($name[0] === '_' || $name[0] === '.' || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'tpl'){
                continue;
            }
            $path = $this->directory.$name;
            if(!is_file($path)){ continue; }
            $files[] = array(
                'file' => $name,
                'size' => intval(filesize($path)),
                'modified' => gmdate('Y-m-d\TH:i:s\Z', intval(filemtime($path)))
            );
        }
        usort($files, static function(array $a, array $b): int {
            return strcasecmp($a['file'], $b['file']);
        });
        return $files;
    }

    /** True when the template file exists. */
    public function exists(string $file): bool
    {
        return $this->directory !== '' && is_file($this->directory.$this->normalize($file));
    }

    /** Return the template body. */
    public function read(string $file): string
    {
        $file = $this->normalize($file);
        if(!$this->exists($file)){
            throw new OutOfBoundsException('Template file '.$file.' does not exist');
        }
        $body = file_get_contents($this->directory.$file);
        if($body === false){
            throw new RuntimeException('Cannot read template file '.$file);
        }
        return $body;
    }

    /**
     * Save a template body and return the normalized file name.
     *
     * @param string $file Requested file name; ".tpl" is added when missing.
     * @param string $body Template body.
     */
    public function write(string $file, string $body): string
    {
        $file = $this->normalize($file);
        if($this->directory === ''){
            throw new RuntimeException('Smarty template folder is not defined');
        }
        if(!is_dir($this->directory) && !mkdir($this->directory, 0775, true)){
            throw new RuntimeException('Cannot create the Smarty template folder');
        }
        if(file_put_contents($this->directory.$file, $body, LOCK_EX) === false){
            throw new RuntimeException('Cannot write file. Check permissions for the Smarty template folder');
        }
        return $file;
    }

    /** Delete a template file. Missing files are ignored. */
    public function delete(string $file): void
    {
        $file = $this->normalize($file);
        if($this->exists($file) && !unlink($this->directory.$file)){
            throw new RuntimeException('Cannot delete template file '.$file);
        }
    }

    /** Return a file name that does not exist yet ("name.tpl", "name_1.tpl", ...). */
    public function uniqueName(string $file): string
    {
        $file = $this->normalize($file);
        $base = substr($file, 0, -4);
        $candidate = $file;
        for($index = 1; $this->exists($candidate); $index++){
            $candidate = $base.'_'.$index.'.tpl';
        }
        return $candidate;
    }

    /**
     * Validate a plain template file name and add the ".tpl" extension.
     *
     * Legacy file names are kept as they are, so only path separators, characters
     * reserved by file systems, control characters and a leading "_" or "." are refused.
     */
    public function normalize(string $file): string
    {
        $file = trim($file);
        if(strtolower(substr($file, -4)) === '.tpl'){ $file = substr($file, 0, -4); }
        if($file === '' || mb_strlen($file) > 120
            || !preg_match('/^[^\/\\\\:*?"<>|\x00-\x1F._][^\/\\\\:*?"<>|\x00-\x1F]*$/u', $file)){
            throw new InvalidArgumentException('Invalid template file name');
        }
        return $file.'.tpl';
    }
}
