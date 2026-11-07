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
    /**
     * The one rule of template file names, new and existing (without ".tpl"): letters of any
     * language, digits, spaces and - _ ( ) . , @; the first character is a letter, a digit or "(".
     * Refused: path and file system characters, and ' & # % ; + which break URLs, HTML and SQL text.
     */
    public const NAME_PATTERN = '/^[\p{L}\p{N}(][\p{L}\p{M}\p{N} _().,@-]*$/u';

    /** Characters a name made from a title may not keep: they become "_". */
    private const NAME_REFUSED = '/[^\p{L}\p{M}\p{N} _().,@-]+/u';

    /** Longest name, without ".tpl". */
    public const NAME_MAX = 100;

    /** The rule in words, for messages. */
    public const NAME_RULE_TEXT = 'A template file name may contain letters, digits, spaces and - _ ( ) . , @, and starts with a letter, a digit or "("';

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
            // a legacy name that breaks the rule is for legacy code only
            if(!preg_match(self::NAME_PATTERN, substr($name, 0, -4))){ continue; }
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

    /** True when the template file exists. False for a name that breaks the rule (a legacy name). */
    public function exists(string $file): bool
    {
        try{
            return $this->directory !== '' && is_file($this->directory.$this->normalize($file));
        }catch(InvalidArgumentException $error){
            return false;
        }
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
     * Check a template file name by the one rule (NAME_PATTERN) and add the ".tpl" extension.
     * Used for new names and for names already stored, so a legacy file with other characters
     * (e.g. "&") can not be reached through the new API.
     *
     * @throws InvalidArgumentException The name breaks the rule.
     */
    public function normalize(string $file): string
    {
        return self::checkName($file);
    }

    /** Static form of normalize(), for code without a store. */
    public static function checkName(string $file): string
    {
        $file = trim($file);
        if(strtolower(substr($file, -4)) === '.tpl'){ $file = substr($file, 0, -4); }
        if($file === '' || mb_strlen($file) > self::NAME_MAX || !preg_match(self::NAME_PATTERN, $file)){
            throw new InvalidArgumentException(self::NAME_RULE_TEXT);
        }
        return $file.'.tpl';
    }

    /**
     * A template file name made from a title: transliterated to Latin letters where the server
     * can (iconv), other refused characters become "_"; "report.tpl" when nothing is left.
     * The result follows NAME_PATTERN. Not unique: see uniqueName().
     */
    public static function nameFromTitle(string $title): string
    {
        $ascii = function_exists('iconv') ? (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) : $title;
        if($ascii === ''){ $ascii = $title; }
        $name = (string)preg_replace(self::NAME_REFUSED, '_', $ascii);
        $name = (string)preg_replace('/\s+/u', ' ', $name);
        $name = (string)preg_replace('/^[^\p{L}\p{N}(]+/u', '', $name);
        $name = trim(mb_substr($name, 0, self::NAME_MAX), " _");
        return $name === '' ? 'report.tpl' : self::checkName($name);
    }
}
